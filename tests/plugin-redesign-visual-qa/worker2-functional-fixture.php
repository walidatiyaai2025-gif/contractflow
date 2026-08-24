<?php

use SafeContracts\Reports\XlsxWorkbook;

if (! defined('ABSPATH')) {
    fwrite(STDERR, "Worker #2 functional fixture must run through wp eval-file.\n");
    exit(1);
}

$path = '/tmp/worker2-functional.xlsx';
$content = (new XlsxWorkbook())->build([
    'Contracts' => [
        ['Customer Name', 'Contract Number'],
        ['QA Imported Customer', 'QA-IMPORT-2026-001'],
    ],
]);
if (file_put_contents($path, $content) === false) {
    throw new RuntimeException('Unable to write Worker #2 functional XLSX fixture.');
}
fwrite(STDOUT, "Worker #2 functional XLSX fixture ready: {$path}\n");
