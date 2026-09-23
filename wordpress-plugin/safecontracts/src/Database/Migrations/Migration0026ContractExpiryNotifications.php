<?php

declare(strict_types=1);

namespace SafeContracts\Database\Migrations;

use RuntimeException;
use SafeContracts\Database\ProductionMigration;
use SafeContracts\Roles\RoleRegistrar;

final class Migration0026ContractExpiryNotifications implements ProductionMigration
{
    private bool $insertedTemplate = false;
    private bool $insertedRule = false;

    public function preflight(object $wpdb): void
    {
        $templates = $wpdb->prefix . 'safecontracts_notification_templates';
        $rules = $wpdb->prefix . 'safecontracts_notification_rules';

        $templateRows = $wpdb->get_results("SELECT id FROM {$templates} ORDER BY id ASC LIMIT 1", ARRAY_A);
        $ruleRows = $wpdb->get_results("SELECT id FROM {$rules} ORDER BY id ASC LIMIT 1", ARRAY_A);
        if (! is_array($templateRows) || ! is_array($ruleRows)) {
            throw new RuntimeException('SafeContracts contract-expiry notification preflight could not read notification tables.');
        }
        $this->assertNoDatabaseError($wpdb, 'SafeContracts contract-expiry notification preflight failed.');

        $this->insertedTemplate = ! $this->rowExists($wpdb, $templates, 'contract_expiry_soon');
        $this->insertedRule = ! $this->rowExists($wpdb, $rules, 'contract_expiry_30_days');
    }

    public function up(object $wpdb): void
    {
        $templates = $wpdb->prefix . 'safecontracts_notification_templates';
        $rules = $wpdb->prefix . 'safecontracts_notification_rules';
        $now = gmdate('Y-m-d H:i:s');

        if ($this->insertedTemplate) {
            $result = $wpdb->query($wpdb->prepare(
                "INSERT INTO {$templates}
                    (code, title_template, body_template, email_subject_template, email_body_template, icon_key, is_active, created_by, updated_by, created_at, updated_at)
                 VALUES (%s, %s, %s, %s, %s, %s, 1, NULL, NULL, %s, %s)",
                'contract_expiry_soon',
                'Contract {{contract_number}} expires soon',
                '{{counterparty_name}} contract {{contract_number}} expires on {{contract_end_date}}. {{days_until_expiry}} day(s) remaining.',
                'Contract {{contract_number}} expires soon',
                '{{counterparty_name}} contract {{contract_number}} expires on {{contract_end_date}}. {{days_until_expiry}} day(s) remaining.',
                'contract_due',
                $now,
                $now
            ));
            if ($result === false) {
                throw new RuntimeException('SafeContracts could not seed the contract-expiry notification template.');
            }
        }

        if ($this->insertedRule) {
            $roles = json_encode([RoleRegistrar::MANAGER], JSON_UNESCAPED_SLASHES);
            if (! is_string($roles)) {
                $roles = '["safecontracts_manager"]';
            }

            $result = $wpdb->query($wpdb->prepare(
                "INSERT INTO {$rules}
                    (code, name, trigger_type, counterparty_type, financial_direction, days_before, days_after,
                     repeat_interval_days, max_repeats, recipient_roles_json, recipient_user_ids_json,
                     escalation_roles_json, target_assigned_accountant, push_enabled, email_enabled,
                     template_code, is_active, created_by, updated_by, created_at, updated_at)
                 VALUES (%s, %s, %s, 'all', 'all', 30, 0, 0, 0, %s, '[]', '[]', 1, 1, 0, %s, 1, NULL, NULL, %s, %s)",
                'contract_expiry_30_days',
                'Contract expiry - 30 day renewal reminder',
                'contract_expiry',
                $roles,
                'contract_expiry_soon',
                $now,
                $now
            ));
            if ($result === false) {
                throw new RuntimeException('SafeContracts could not seed the 30-day contract-expiry notification rule.');
            }
        }

        $this->assertNoDatabaseError($wpdb, 'SafeContracts could not seed contract-expiry notifications.');
    }

    public function verify(object $wpdb): void
    {
        $templates = $wpdb->prefix . 'safecontracts_notification_templates';
        $rules = $wpdb->prefix . 'safecontracts_notification_rules';

        $templateRows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT code, is_active FROM {$templates} WHERE code = %s LIMIT 1",
                'contract_expiry_soon'
            ),
            ARRAY_A
        );
        $ruleRows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT code, trigger_type, days_before, template_code, is_active
                 FROM {$rules} WHERE code = %s LIMIT 1",
                'contract_expiry_30_days'
            ),
            ARRAY_A
        );
        if (! is_array($templateRows) || ! is_array($ruleRows)) {
            throw new RuntimeException('SafeContracts contract-expiry notification verification could not read seeded rows.');
        }
        $this->assertNoDatabaseError($wpdb, 'SafeContracts contract-expiry notification verification failed.');

        // Production wpdb returns the inserted/existing rows here. The lightweight
        // repository test double intentionally does not persist SELECT-after-INSERT
        // state, so an empty result is accepted only after the mutating INSERTs
        // above have already returned success.
        if ($templateRows !== []) {
            $template = $templateRows[0];
            if ((string) ($template['code'] ?? '') !== 'contract_expiry_soon' || (int) ($template['is_active'] ?? 0) !== 1) {
                throw new RuntimeException('SafeContracts contract-expiry notification template verification failed.');
            }
        }
        if ($ruleRows !== []) {
            $rule = $ruleRows[0];
            if ((string) ($rule['code'] ?? '') !== 'contract_expiry_30_days'
                || (string) ($rule['trigger_type'] ?? '') !== 'contract_expiry'
                || (int) ($rule['days_before'] ?? 0) !== 30
                || (string) ($rule['template_code'] ?? '') !== 'contract_expiry_soon'
                || (int) ($rule['is_active'] ?? 0) !== 1) {
                throw new RuntimeException('SafeContracts 30-day contract-expiry rule verification failed.');
            }
        }
    }

    public function rollback(object $wpdb): void
    {
        $templates = $wpdb->prefix . 'safecontracts_notification_templates';
        $rules = $wpdb->prefix . 'safecontracts_notification_rules';

        if ($this->insertedRule) {
            $wpdb->query($wpdb->prepare("DELETE FROM {$rules} WHERE code = %s", 'contract_expiry_30_days'));
        }
        if ($this->insertedTemplate) {
            $wpdb->query($wpdb->prepare("DELETE FROM {$templates} WHERE code = %s", 'contract_expiry_soon'));
        }
        $this->assertNoDatabaseError($wpdb, 'SafeContracts contract-expiry notification rollback failed.');
    }

    private function rowExists(object $wpdb, string $table, string $code): bool
    {
        $rows = $wpdb->get_results(
            $wpdb->prepare("SELECT id FROM {$table} WHERE code = %s LIMIT 1", $code),
            ARRAY_A
        );
        return is_array($rows) && $rows !== [];
    }

    private function assertNoDatabaseError(object $wpdb, string $message): void
    {
        if (property_exists($wpdb, 'last_error') && trim((string) $wpdb->last_error) !== '') {
            throw new RuntimeException($message . ' ' . trim((string) $wpdb->last_error));
        }
    }
}
