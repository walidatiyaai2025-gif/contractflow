<?php

declare(strict_types=1);

namespace SafeContracts\Import;

use DomainException;
use SafeContracts\Roles\Capabilities;
use Throwable;

final class ImportUploadService
{
    public function __construct(
        private ?WorkbookUploadValidator $validator = null,
        private ?PrivateImportStorage $storage = null,
        private ?WorkbookReader $reader = null,
        private ?ImportRunRepository $runs = null
    ) {
        $this->validator ??= new WorkbookUploadValidator();
        $this->storage ??= new PrivateImportStorage();
        $this->reader ??= new WorkbookReader();
        $this->runs ??= new ImportRunRepository();
    }

    /** @param array<string,mixed> $file @return array{run_id:int,discovery:array<string,mixed>} */
    public function accept(array $file): array
    {
        if (! current_user_can(Capabilities::RUN_IMPORTS)) {
            throw new DomainException('You do not have permission to run SafeContracts imports.');
        }

        $validated = $this->validator->validate($file);
        $storageKey = $this->storage->store($validated['tmp_name'], $validated['sha256']);
        $actorId = get_current_user_id();
        $runId = $this->runs->create(
            $validated['name'],
            $storageKey,
            $validated['sha256'],
            $validated['size'],
            $actorId
        );
        do_action('safecontracts_import_uploaded', [
            'run_id' => $runId,
            'status' => 'uploaded',
            'file_size' => $validated['size'],
        ], $actorId);

        try {
            $discovery = $this->reader->discover($this->storage->pathForKey($storageKey));
            $this->runs->saveDiscovery($runId, $discovery);
        } catch (Throwable $error) {
            $this->runs->updateStatus($runId, 'failed');
            throw $error;
        }

        do_action('safecontracts_import_discovered', [
            'run_id' => $runId,
            'status' => 'discovered',
            'sheet_count' => count($discovery['sheets'] ?? []),
        ], $actorId);

        return ['run_id' => $runId, 'discovery' => $discovery];
    }
}
