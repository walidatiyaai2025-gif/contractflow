<?php

declare(strict_types=1);

namespace SafeContracts\Import;

use InvalidArgumentException;

final class ColumnMapping
{
    /** @return array<string,array{label:string,required:bool}> */
    public static function fields(): array
    {
        return [
            'customer_name' => ['label' => 'Customer name', 'required' => true],
            'customer_code' => ['label' => 'Customer internal code', 'required' => false],
            'customer_contact_name' => ['label' => 'Customer contact name', 'required' => false],
            'customer_email' => ['label' => 'Customer email', 'required' => false],
            'customer_phone' => ['label' => 'Customer phone', 'required' => false],
            'contract_number' => ['label' => 'Contract number', 'required' => true],
            'accountant_user_id' => ['label' => 'Accountant user ID', 'required' => false],
            'contract_start_date' => ['label' => 'Contract start date', 'required' => false],
            'contract_end_date' => ['label' => 'Contract end date', 'required' => false],
            'contract_base_value' => ['label' => 'Contract base value', 'required' => false],
            'payment_sequence' => ['label' => 'Payment sequence', 'required' => false],
            'payment_reference' => ['label' => 'Payment reference', 'required' => false],
            'payment_due_date' => ['label' => 'Payment due date', 'required' => false],
            'payment_expected_date' => ['label' => 'Expected payment date', 'required' => false],
            'payment_amount' => ['label' => 'Payment amount', 'required' => false],
        ];
    }

    /**
     * @param array<string,mixed> $mapping target field => workbook column (A, B, ...)
     * @param array{name:string,path?:string,header_row:int,headers:list<array{column:string,original:string,normalized:string}>} $sheet
     * @return array<string,string>
     */
    public function validate(array $mapping, array $sheet): array
    {
        $available = [];
        foreach ($sheet['headers'] ?? [] as $header) {
            $column = strtoupper((string) ($header['column'] ?? ''));
            if (preg_match('/^[A-Z]{1,3}$/', $column)) {
                $available[$column] = true;
            }
        }
        if ($available === []) {
            throw new InvalidArgumentException('Selected sheet has no discoverable headers.');
        }

        $fields = self::fields();
        $normalized = [];
        $usedSources = [];
        foreach ($mapping as $target => $source) {
            if (! is_string($target) || ! isset($fields[$target])) {
                throw new InvalidArgumentException('Import mapping contains an unsupported SafeContracts field.');
            }
            if (is_array($source) || is_object($source)) {
                throw new InvalidArgumentException('Import mapping source must be a workbook column.');
            }
            $source = strtoupper(trim((string) $source));
            if ($source === '') {
                continue;
            }
            if (! isset($available[$source])) {
                throw new InvalidArgumentException('Import mapping references a workbook column that is not in the selected header row.');
            }
            if (isset($usedSources[$source])) {
                throw new InvalidArgumentException('A workbook column cannot map to multiple SafeContracts fields.');
            }
            $usedSources[$source] = true;
            $normalized[$target] = $source;
        }

        foreach ($fields as $field => $definition) {
            if ($definition['required'] && ! isset($normalized[$field])) {
                throw new InvalidArgumentException('Required import field is not mapped: ' . $field . '.');
            }
        }
        return $normalized;
    }

    /** @return array{name:string,path?:string,header_row:int,headers:list<array{column:string,original:string,normalized:string}>} */
    public static function sheet(array $discovery, string $sheetName): array
    {
        foreach ($discovery['sheets'] ?? [] as $sheet) {
            if (is_array($sheet) && (string) ($sheet['name'] ?? '') === $sheetName) {
                return $sheet;
            }
        }
        throw new InvalidArgumentException('Selected workbook sheet is not part of the discovered workbook.');
    }
}
