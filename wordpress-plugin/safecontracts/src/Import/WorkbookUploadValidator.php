<?php

declare(strict_types=1);

namespace SafeContracts\Import;

use InvalidArgumentException;

final class WorkbookUploadValidator
{
    public const MAX_BYTES = 20971520; // 20 MiB

    /** @param array<string,mixed> $file @return array{name:string,tmp_name:string,size:int,sha256:string} */
    public function validate(array $file): array
    {
        foreach (['name', 'tmp_name', 'size', 'error'] as $key) {
            if (! array_key_exists($key, $file) || is_array($file[$key]) || is_object($file[$key])) {
                throw new InvalidArgumentException('Workbook upload metadata is malformed.');
            }
        }

        $error = filter_var($file['error'], FILTER_VALIDATE_INT);
        if ($error === false || $error !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException('Workbook upload did not complete successfully.');
        }

        $name = basename(trim((string) $file['name']));
        if ($name === '' || strlen($name) > 191 || strtolower((string) pathinfo($name, PATHINFO_EXTENSION)) !== 'xlsx') {
            throw new InvalidArgumentException('SafeContracts import accepts .xlsx workbooks only.');
        }

        $size = filter_var($file['size'], FILTER_VALIDATE_INT);
        if ($size === false || $size <= 0 || $size > self::MAX_BYTES) {
            throw new InvalidArgumentException('Workbook size is outside the allowed import limit.');
        }

        $tmp = trim((string) $file['tmp_name']);
        if ($tmp === '' || ! is_file($tmp) || ! is_readable($tmp)) {
            throw new InvalidArgumentException('Workbook temporary file is unavailable.');
        }
        $actualSize = filesize($tmp);
        if ($actualSize === false || $actualSize <= 0 || $actualSize > self::MAX_BYTES || abs($actualSize - $size) > 0) {
            throw new InvalidArgumentException('Workbook upload size does not match the received file.');
        }

        $handle = fopen($tmp, 'rb');
        $magic = is_resource($handle) ? fread($handle, 4) : false;
        if (is_resource($handle)) {
            fclose($handle);
        }
        if (! is_string($magic) || ! str_starts_with($magic, "PK\x03\x04")) {
            throw new InvalidArgumentException('Workbook is not a valid XLSX package.');
        }

        $sha = hash_file('sha256', $tmp);
        if (! is_string($sha) || ! preg_match('/^[a-f0-9]{64}$/', $sha)) {
            throw new InvalidArgumentException('Unable to fingerprint workbook upload.');
        }

        return ['name' => $name, 'tmp_name' => $tmp, 'size' => $size, 'sha256' => $sha];
    }
}
