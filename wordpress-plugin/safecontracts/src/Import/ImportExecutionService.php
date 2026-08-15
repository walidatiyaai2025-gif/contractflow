<?php

declare(strict_types=1);

namespace SafeContracts\Import;

use DomainException;
use InvalidArgumentException;
use SafeContracts\Contracts\ContractMoney;
use SafeContracts\Contracts\ContractService;
use SafeContracts\Customers\CustomerService;
use SafeContracts\Payments\PaymentService;
use SafeContracts\Roles\Capabilities;
use Throwable;

final class ImportExecutionService
{
    public function __construct(
        private ?ImportRunRepository $runs = null,
        private ?PrivateImportStorage $storage = null,
        private ?WorkbookReader $reader = null,
        private ?ImportRowValidator $validator = null,
        private ?ImportEntityLookup $lookup = null,
        private ?CustomerService $customers = null,
        private ?ContractService $contracts = null,
        private ?PaymentService $payments = null
    ) {
        $this->runs ??= new ImportRunRepository();
        $this->storage ??= new PrivateImportStorage();
        $this->reader ??= new WorkbookReader();
        $this->validator ??= new ImportRowValidator();
        $this->lookup ??= new ImportEntityLookup();
        $this->customers ??= new CustomerService();
        $this->contracts ??= new ContractService();
        $this->payments ??= new PaymentService();
    }

    /** @return array{status:string,total_rows:int,valid_rows:int,imported_rows:int,skipped_rows:int,error_rows:int} */
    public function execute(int $runId, mixed $duplicateStrategy): array
    {
        if (! current_user_can(Capabilities::RUN_IMPORTS)) {
            throw new DomainException('You do not have permission to run SafeContracts imports.');
        }
        if ($runId <= 0) {
            throw new InvalidArgumentException('Import run ID must be positive.');
        }
        $strategy = DuplicateStrategy::normalize($duplicateStrategy);
        $run = $this->runs->find($runId);
        if ($run === null || $run['mapping'] === [] || trim((string) ($run['selected_sheet'] ?? '')) === '') {
            throw new InvalidArgumentException('Import run must have a validated worksheet mapping before execution.');
        }
        if (! in_array((string) ($run['status'] ?? ''), ['mapped', 'validated'], true)) {
            throw new DomainException('Import run is not executable in its current state. Completed, running and failed runs are terminal.');
        }

        // A mapped/validated retry is a fresh validation attempt. Terminal runs cannot reach this path.
        $this->runs->clearErrors($runId);
        $sheet = ColumnMapping::sheet($run['discovery'], (string) $run['selected_sheet']);
        $path = $this->storage->pathForKey((string) $run['storage_key']);
        $sourceRows = $this->reader->rows($path, (string) $run['selected_sheet'], (int) $sheet['header_row'], 50000);
        $candidates = [];
        $validationErrorRows = [];
        foreach ($sourceRows as $sourceRow) {
            $mapped = $this->mapRow($sourceRow['cells'], $run['mapping']);
            $validation = $this->validator->validate($mapped);
            if (! $validation['valid']) {
                $validationErrorRows[(int) $sourceRow['row_number']] = true;
                foreach ($validation['errors'] as $error) {
                    $this->runs->addError($runId, (int) $sourceRow['row_number'], $error['field'], 'validation.' . $error['code'], $error['message']);
                }
                continue;
            }
            $candidates[] = ['row_number' => (int) $sourceRow['row_number'], 'data' => $validation['data']];
        }

        $counts = [
            'total_rows' => count($sourceRows),
            'valid_rows' => count($candidates),
            'imported_rows' => 0,
            'skipped_rows' => 0,
            'error_rows' => count($validationErrorRows),
        ];
        $this->runs->updateStatus($runId, 'validated', $counts, $strategy);
        do_action('safecontracts_import_validated', [
            'run_id' => $runId,
            'status' => 'validated',
            'duplicate_strategy' => $strategy,
            'counts' => $counts,
        ], get_current_user_id());

        // Fail closed: no business entity is mutated until every source row passes validation.
        if ($validationErrorRows !== []) {
            do_action('safecontracts_import_validation_failed', ['run_id' => $runId, 'counts' => $counts], get_current_user_id());
            return ['status' => 'validated', ...$counts];
        }

        $this->runs->updateStatus($runId, 'running', $counts, $strategy);
        foreach ($candidates as $candidate) {
            global $wpdb;
            $rowNumber = (int) $candidate['row_number'];
            try {
                $wpdb->query('START TRANSACTION');
                $result = $this->writeRow($candidate['data'], $strategy);
                $wpdb->query('COMMIT');
                if ($result === 'skipped') {
                    $counts['skipped_rows']++;
                } else {
                    $counts['imported_rows']++;
                }
            } catch (Throwable $error) {
                $wpdb->query('ROLLBACK');
                $counts['error_rows']++;
                $this->runs->addError($runId, $rowNumber, null, 'execution.' . $this->errorCode($error), $error->getMessage());
            }
        }

        $status = $counts['error_rows'] > 0 ? 'completed_with_errors' : 'completed';
        $this->runs->updateStatus($runId, $status, $counts, $strategy);
        do_action('safecontracts_import_completed', [
            'run_id' => $runId,
            'status' => $status,
            'duplicate_strategy' => $strategy,
            'counts' => $counts,
        ], get_current_user_id());

        return ['status' => $status, ...$counts];
    }

    /** @param array<string,string> $cells @param array<string,string> $mapping @return array<string,string> */
    private function mapRow(array $cells, array $mapping): array
    {
        $mapped = [];
        foreach ($mapping as $target => $column) {
            $mapped[$target] = (string) ($cells[$column] ?? '');
        }
        return $mapped;
    }

    /** @param array<string,mixed> $data */
    private function writeRow(array $data, string $strategy): string
    {
        $customer = $this->lookup->customer((string) $data['customer_code'], (string) $data['customer_name']);
        if ($customer === null) {
            $customerId = $this->customers->save([
                'internal_code' => $data['customer_code'],
                'name' => $data['customer_name'],
                'contact_name' => $data['customer_contact_name'],
                'email' => $data['customer_email'],
                'phone' => $data['customer_phone'],
                'notes' => '',
                'is_active' => true,
            ]);
        } else {
            $customerId = (int) ($customer['id'] ?? 0);
            if ($customerId <= 0 || empty($customer['is_active'])) {
                throw new DomainException('Matched customer is inactive or invalid.');
            }
            if ($strategy === DuplicateStrategy::UPDATE) {
                $this->customers->save([
                    'id' => $customerId,
                    'internal_code' => $data['customer_code'],
                    'name' => $data['customer_name'],
                    'contact_name' => $data['customer_contact_name'],
                    'email' => $data['customer_email'],
                    'phone' => $data['customer_phone'],
                    'notes' => '',
                    'is_active' => true,
                ]);
            }
        }

        $contract = $this->lookup->contract((string) $data['contract_number']);
        if ($contract === null) {
            $contractId = $this->contracts->create([
                'contract_number' => $data['contract_number'],
                'customer_id' => $customerId,
                'accountant_user_id' => $data['accountant_user_id'],
                'notes' => '',
            ]);
            $this->applyContractFields($contractId, $data, true);
        } else {
            $contractId = (int) ($contract['id'] ?? 0);
            if ((int) ($contract['customer_id'] ?? 0) !== $customerId) {
                throw new DomainException('Duplicate contract belongs to a different customer.');
            }
            if (! $data['has_payment']) {
                if ($strategy === DuplicateStrategy::FAIL) {
                    throw new DomainException('Duplicate contract detected.');
                }
                if ($strategy === DuplicateStrategy::SKIP) {
                    return 'skipped';
                }
            }
            if ($strategy === DuplicateStrategy::UPDATE) {
                $this->applyContractFields($contractId, $data, false);
            }
        }

        if (! $data['has_payment']) {
            return 'imported';
        }

        $payment = $this->lookup->payment($contractId, (int) $data['payment_sequence']);
        if ($payment === null) {
            $this->payments->create([
                'contract_id' => $contractId,
                'sequence_no' => $data['payment_sequence'],
                'reference' => $data['payment_reference'],
                'due_date' => $data['payment_due_date'],
                'expected_payment_date' => $data['payment_expected_date'],
                'original_amount' => $data['payment_amount'],
            ]);
            return 'imported';
        }
        if ($strategy === DuplicateStrategy::FAIL) {
            throw new DomainException('Duplicate payment detected for contract sequence.');
        }
        if ($strategy === DuplicateStrategy::SKIP) {
            return 'skipped';
        }

        $existingAmount = ContractMoney::normalizeNonNegative((string) ($payment['original_amount'] ?? '0'));
        if ($existingAmount !== (string) $data['payment_amount']) {
            throw new DomainException('Existing payment amount cannot be changed by import update.');
        }
        $existingReference = trim((string) ($payment['reference'] ?? ''));
        if ($existingReference !== trim((string) $data['payment_reference'])) {
            throw new DomainException('Existing payment reference cannot be changed by import update.');
        }
        $this->payments->updateDates((int) $payment['id'], $data['payment_due_date'], $data['payment_expected_date']);
        return 'imported';
    }

    /** @param array<string,mixed> $data */
    private function applyContractFields(int $contractId, array $data, bool $newContract): void
    {
        if ($data['contract_start_date'] !== null || $data['contract_end_date'] !== null) {
            $this->contracts->updateDates($contractId, $data['contract_start_date'], $data['contract_end_date']);
        }
        if ($data['contract_base_value'] !== null) {
            $this->contracts->updateBaseValue($contractId, $data['contract_base_value']);
        }
        if (! $newContract && $data['accountant_user_id'] !== null) {
            $this->contracts->assignAccountant($contractId, $data['accountant_user_id']);
        }
    }

    private function errorCode(Throwable $error): string
    {
        $name = (new \ReflectionClass($error))->getShortName();
        return substr(preg_replace('/[^a-z0-9_.-]/', '_', strtolower($name)) ?? 'error', 0, 40);
    }
}
