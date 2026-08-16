<?php

declare(strict_types=1);

namespace SafeContracts\Import;

use Closure;
use RuntimeException;
use SafeContracts\Tenancy\NonCoreTenantScope;

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

        $tenantId = NonCoreTenantScope::tenantId();
        $storageKey = $tenantId === null ? $sha256 : 'tenant-' . $tenantId . '/' . $sha256;
        $destination = $this->pathForKey($storageKey);
        $this->ensureDirectory(dirname($destination));
        if (! is_file($destination) && ! ($this->mover)($source, $destination)) {
            throw new RuntimeException('Unable to move workbook into private SafeContracts storage.');
        }
        @chmod($destination, 0600);
        return $storageKey;
    }

    public function pathForKey(string $storageKey): string
    {
        if (preg_match('/^[a-f0-9]{64}$/', $storageKey) === 1) {
            // Legacy backfilled imports keep their historical key. Database tenant
            // ownership protects access; all new ESC writes use a tenant-qualified key.
            return $this->baseDir . '/' . $storageKey . '.xlsx';
        }

        if (preg_match('/^tenant-([1-9][0-9]*)\/([a-f0-9]{64})$/', $storageKey, $matches) !== 1) {
            throw new RuntimeException('Invalid SafeContracts import storage key.');
        }

        $keyTenantId = (int) $matches[1];
        $currentTenantId = NonCoreTenantScope::tenantId();
        if ($currentTenantId !== null && $currentTenantId !== $keyTenantId) {
            throw new RuntimeException('Enterprise import storage key belongs to another tenant.');
        }

        return $this->baseDir . '/tenant-' . $keyTenantId . '/' . $matches[2] . '.xlsx';
    }

    private function ensureDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            $created = function_exists('wp_mkdir_p') ? wp_mkdir_p($directory) : mkdir($directory, 0700, true);
            if (! $created && ! is_dir($directory)) {
                throw new RuntimeException('Unable to create private SafeContracts import directory.');
            }
        }
        @chmod($directory, 0700);

        // Keep deny/index guards at both the root and tenant subdirectory level.
        foreach (array_values(array_unique([$this->baseDir, $directory])) as $guardedDirectory) {
            if (! is_dir($guardedDirectory)) {
                continue;
            }
            @chmod($guardedDirectory, 0700);
            $deny = $guardedDirectory . '/.htaccess';
            if (! is_file($deny)) {
                @file_put_contents($deny, "Require all denied\nDeny from all\n", LOCK_EX);
            }
            $index = $guardedDirectory . '/index.php';
            if (! is_file($index)) {
                @file_put_contents($index, "<?php\nhttp_response_code(404);\nexit;\n", LOCK_EX);
            }
        }
    }
}
