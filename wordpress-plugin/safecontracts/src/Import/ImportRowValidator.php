<?php

declare(strict_types=1);

namespace SafeContracts\Import;

use DateTimeImmutable;
use SafeContracts\Contracts\ContractMoney;
use Throwable;

final class ImportRowValidator
{
    /** @param array<string,mixed> $input @return array{valid:bool,data:array<string,mixed>,errors:list<array{field:?string,code:string,message:string}>} */
    public function validate(array $input): array
    {
        $errors = [];
        $data = [];

        $data['customer_name'] = $this->text($input['customer_name'] ?? '', 191);
        if ($data['customer_name'] === '') {
            $errors[] = $this->error('customer_name', 'required', 'Customer name is required.');
        }
        $data['customer_code'] = $this->text($input['customer_code'] ?? '', 100);
        $data['customer_contact_name'] = $this->text($input['customer_contact_name'] ?? '', 191);

        $emailValue = $input['customer_email'] ?? null;
        if ($emailValue === null || $emailValue === '') {
            $data['customer_email'] = '';
        } elseif (! is_scalar($emailValue) || is_bool($emailValue)) {
            $data['customer_email'] = '';
            $errors[] = $this->error('customer_email', 'invalid_email', 'Customer email is invalid.');
        } else {
            $data['customer_email'] = trim((string) $emailValue);
            if (! filter_var($data['customer_email'], FILTER_VALIDATE_EMAIL) || strlen($data['customer_email']) > 191) {
                $errors[] = $this->error('customer_email', 'invalid_email', 'Customer email is invalid.');
            }
        }
        $data['customer_phone'] = $this->text($input['customer_phone'] ?? '', 64);

        $data['contract_number'] = $this->text($input['contract_number'] ?? '', 100);
        if ($data['contract_number'] === '') {
            $errors[] = $this->error('contract_number', 'required', 'Contract number is required.');
        }
        $data['accountant_user_id'] = $this->positiveIntOrNull($input['accountant_user_id'] ?? null, 'accountant_user_id', $errors);
        $data['contract_start_date'] = $this->dateOrNull($input['contract_start_date'] ?? null, 'contract_start_date', $errors);
        $data['contract_end_date'] = $this->dateOrNull($input['contract_end_date'] ?? null, 'contract_end_date', $errors);
        if ($data['contract_start_date'] !== null && $data['contract_end_date'] !== null && $data['contract_end_date'] < $data['contract_start_date']) {
            $errors[] = $this->error('contract_end_date', 'invalid_date_range', 'Contract end date cannot be earlier than start date.');
        }
        $data['contract_base_value'] = $this->moneyOrNull($input['contract_base_value'] ?? null, 'contract_base_value', false, $errors);

        $data['payment_reference'] = $this->text($input['payment_reference'] ?? '', 100);
        $paymentRaw = [
            $input['payment_sequence'] ?? '', $input['payment_reference'] ?? '', $input['payment_due_date'] ?? '',
            $input['payment_expected_date'] ?? '', $input['payment_amount'] ?? '',
        ];
        $hasPayment = false;
        foreach ($paymentRaw as $value) {
            if (is_scalar($value) && ! is_bool($value) && trim((string) $value) !== '') {
                $hasPayment = true;
                break;
            }
            if (is_array($value) || is_object($value) || is_resource($value) || is_bool($value)) {
                $hasPayment = true;
                break;
            }
        }
        $data['has_payment'] = $hasPayment;
        $data['payment_sequence'] = $hasPayment ? $this->positiveIntOrNull($input['payment_sequence'] ?? null, 'payment_sequence', $errors) : null;
        $data['payment_due_date'] = $hasPayment ? $this->dateOrNull($input['payment_due_date'] ?? null, 'payment_due_date', $errors) : null;
        $data['payment_expected_date'] = $hasPayment ? $this->dateOrNull($input['payment_expected_date'] ?? null, 'payment_expected_date', $errors) : null;
        $data['payment_amount'] = $hasPayment ? $this->moneyOrNull($input['payment_amount'] ?? null, 'payment_amount', true, $errors) : null;
        if ($hasPayment) {
            if ($data['payment_sequence'] === null) {
                $errors[] = $this->error('payment_sequence', 'required', 'Payment sequence is required when payment data is present.');
            }
            if ($data['payment_due_date'] === null) {
                $errors[] = $this->error('payment_due_date', 'required', 'Payment due date is required when payment data is present.');
            }
            if ($data['payment_amount'] === null) {
                $errors[] = $this->error('payment_amount', 'required', 'Payment amount is required when payment data is present.');
            }
        }

        return ['valid' => $errors === [], 'data' => $data, 'errors' => $errors];
    }

    private function text(mixed $value, int $max): string
    {
        if (! is_scalar($value) || is_bool($value)) {
            return '';
        }
        return substr(trim(strip_tags((string) $value)), 0, $max);
    }

    /** @param list<array{field:?string,code:string,message:string}> $errors */
    private function positiveIntOrNull(mixed $value, string $field, array &$errors): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (! is_scalar($value) || is_bool($value)) {
            $errors[] = $this->error($field, 'invalid_integer', 'Value must be a positive integer.');
            return null;
        }
        $filtered = filter_var((string) $value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($filtered === false) {
            $errors[] = $this->error($field, 'invalid_integer', 'Value must be a positive integer.');
            return null;
        }
        return (int) $filtered;
    }

    /** @param list<array{field:?string,code:string,message:string}> $errors */
    private function dateOrNull(mixed $value, string $field, array &$errors): ?string
    {
        if ($value === null) {
            return null;
        }
        if (! is_scalar($value) || is_bool($value)) {
            $errors[] = $this->error($field, 'invalid_date', 'Date must use YYYY-MM-DD.');
            return null;
        }
        $date = trim((string) $value);
        if ($date === '') {
            return null;
        }
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if (! $parsed || $parsed->format('Y-m-d') !== $date) {
            $errors[] = $this->error($field, 'invalid_date', 'Date must use YYYY-MM-DD and be a valid calendar date.');
            return null;
        }
        return $date;
    }

    /** @param list<array{field:?string,code:string,message:string}> $errors */
    private function moneyOrNull(mixed $value, string $field, bool $positive, array &$errors): ?string
    {
        if ($value === null) {
            return null;
        }
        if (! is_scalar($value) || is_bool($value)) {
            $errors[] = $this->error($field, 'invalid_amount', 'Amount must be a valid non-negative decimal value.');
            return null;
        }
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }
        try {
            $money = ContractMoney::normalizeNonNegative($raw);
            if ($positive && $money === '0.0000') {
                $errors[] = $this->error($field, 'invalid_amount', 'Amount must be greater than zero.');
                return null;
            }
            return $money;
        } catch (Throwable $error) {
            unset($error);
            $errors[] = $this->error($field, 'invalid_amount', 'Amount must be a valid non-negative decimal value.');
            return null;
        }
    }

    /** @return array{field:?string,code:string,message:string} */
    private function error(?string $field, string $code, string $message): array
    {
        return ['field' => $field, 'code' => $code, 'message' => $message];
    }
}
