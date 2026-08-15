<?php

declare(strict_types=1);

namespace SafeContracts\Audit;

use Throwable;

final class ContractArchiveAuditRecorder
{
    private static bool $registered = false;

    public static function register(): void
    {
        if (self::$registered) {
            return;
        }
        self::$registered = true;

        add_action('safecontracts_contract_archived', static function (int $contractId, int $actorId): void {
            try {
                (new AuditRepository())->append(
                    'contract',
                    $contractId,
                    'contract_archived',
                    $actorId > 0 ? $actorId : null,
                    ['is_archived' => false],
                    ['is_archived' => true],
                    ['source' => 'admin_dashboard']
                );
            } catch (Throwable $error) {
                error_log('SafeContracts audit write failed for contract_archived: ' . $error->getMessage());
            }
        }, 10, 2);
    }
}
