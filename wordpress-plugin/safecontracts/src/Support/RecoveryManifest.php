<?php

declare(strict_types=1);

namespace SafeContracts\Support;

final class RecoveryManifest
{
    /** @return list<string> */
    public static function tableSuffixes(): array
    {
        return [
            'safecontracts_meta',
            'safecontracts_customers',
            'safecontracts_payment_methods',
            'safecontracts_contracts',
            'safecontracts_contract_financial_items',
            'safecontracts_contract_adjustments',
            'safecontracts_contract_attachments',
            'safecontracts_contract_history',
            'safecontracts_scheduled_payments',
            'safecontracts_payment_collections',
            'safecontracts_payment_followups',
            'safecontracts_audit_log',
            'safecontracts_notification_rules',
            'safecontracts_notification_templates',
            'safecontracts_device_tokens',
            'safecontracts_notification_deliveries',
            'safecontracts_import_runs',
            'safecontracts_import_errors',
        ];
    }

    /** @return list<string> */
    public static function optionKeys(): array
    {
        return [
            'safecontracts_db_version',
            'safecontracts_db_migrated_at',
            'safecontracts_installed_at',
            'safecontracts_plugin_version',
            'safecontracts_general_settings',
            'safecontracts_mobile_configuration',
            'safecontracts_firebase_public_config',
            'safecontracts_firebase_credential_reference',
        ];
    }

    /** @return list<string> */
    public static function userMetaKeys(): array
    {
        return [
            'safecontracts_notification_read_ids',
        ];
    }

    /** @return list<string> */
    public static function externalDependencies(): array
    {
        return [
            'wordpress-media-library',
            'wordpress-users-and-role-assignments',
            'environment-secret-values-referenced-by-wordpress-options',
        ];
    }

    /** @return list<string> */
    public static function minimumRestoreOrder(): array
    {
        return [
            'restore-database-tables',
            'restore-wordpress-options-and-user-meta',
            'restore-wordpress-media-library',
            'restore-wordpress-users-and-role-assignments',
            'restore-environment-secret-values-by-reference',
            'activate-safecontracts-plugin',
            'run-safecontracts-migrations',
            'run-quality-and-uat-smoke-gates',
        ];
    }
}
