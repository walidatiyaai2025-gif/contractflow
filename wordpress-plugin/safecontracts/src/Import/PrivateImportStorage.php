<?php

declare(strict_types=1);

namespace SafeContracts\Import;

use Closure;
use RuntimeException;

final class PrivateImportStorage
{
    private string $baseDir;
    private Closure $mover;

    public function __construct(?string $baseDir = null, ?Closure $mover = null)
    {
        $privateRoot = defined('SAFECONTRACTS_PRIVATE_DIR')
            ? rtrim((string) constant('SAFECONTRACTS_PRIVATE_DIR'), '/\\')
            : rtrim(sys_get_temp_dir(), '/\\') . '/safecontracts-private';
        $default = $privateRoot . '/imports';
        $this->baseDir = rtrim($baseDir ?? (string) apply_filters('safecontracts_import_storage_dir', $default), '/\\');
        $this->mover = $mover ?? static function (string $source, string $destination): bool {
            return is_uploaded_file($source) && move_uploaded_file($source, $destination);
        };
    }

    public function store(string $source, string $sha256): string
    {
        if (! preg_match('/^[a-f0-9]{64}$/', $sha256)) {
            throw new RuntimeException('Import storage key must be a SHA-256 value.');
        }
        $this->ensureDirectory();
        $destination = $this->pathForKey($sha256);
        if (! is_file($destination) && ! ($this->mover)($source, $destination)) {
            throw new RuntimeException('Unable to move workbook into private SafeContracts storage.');
        }
        @chmod($destination, 0600);
        return $sha256;
    }

    public function pathForKey(string $storageKey): string
    {
        if (! preg_match('/^[a-f0-9]{64}$/', $storageKey)) {
            throw new RuntimeException('Invalid SafeContracts import storage key.');
        }
        return $this->baseDir . '/' . $storageKey . '.xlsx';
    }

    private function ensureDirectory(): void
    {
        if (! is_dir($this->baseDir)) {
            $created = function_exists('wp_mkdir_p') ? wp_mkdir_p($this->baseDir) : mkdir($this->baseDir, 0700, true);
            if (! $created && ! is_dir($this->baseDir)) {
                throw new RuntimeException('Unable to create private SafeContracts import directory.');
            }
        }
        @chmod($this->baseDir, 0700);
        $deny = $this->baseDir . '/.htaccess';
        if (! is_file($deny)) {
            @file_put_contents($deny, "Require all denied\nDeny from all\n", LOCK_EX);
        }
        $index = $this->baseDir . '/index.php';
        if (! is_file($index)) {
            @file_put_contents($index, "<?php\nhttp_response_code(404);\nexit;\n", LOCK_EX);
        }
    }
}
