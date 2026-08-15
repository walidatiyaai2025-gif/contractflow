<?php

declare(strict_types=1);

namespace SafeContracts;

use SafeContracts\Admin\AdminShell;
use SafeContracts\Admin\CollectionsPage;
use SafeContracts\Admin\ContractsPage;
use SafeContracts\Admin\CustomersPage;
use SafeContracts\Admin\FirebaseSettingsPage;
use SafeContracts\Admin\FollowUpsPage;
use SafeContracts\Admin\GeneralSettingsPage;
use SafeContracts\Admin\LoginBranding;
use SafeContracts\Admin\MobileConfigurationPage;
use SafeContracts\Admin\NavigationCleanup;
use SafeContracts\Admin\NotificationSettingsPage;
use SafeContracts\Admin\NotificationsPage;
use SafeContracts\Admin\PaymentMethodsPage;
use SafeContracts\Admin\PaymentsPage;
use SafeContracts\Admin\ReportsPage;
use SafeContracts\Admin\UsersRolesPage;
use SafeContracts\Audit\AuditRecorder;
use SafeContracts\Contracts\ContractHistoryRecorder;
use SafeContracts\Database\Migrator;
use SafeContracts\Rest\Router;

final class Plugin
{
    private static ?self $instance = null;
    private bool $booted = false;

    private function __construct()
    {
    }

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        $this->booted = true;

        (new Migrator())->maybeMigrate();
        ContractHistoryRecorder::register();
        AuditRecorder::register();
        LoginBranding::register();
        NavigationCleanup::register();

        add_action('rest_api_init', [Router::class, 'register']);
        add_action('admin_menu', [AdminShell::class, 'register'], 5);
        add_action('admin_menu', [CustomersPage::class, 'register'], 10);
        add_action('admin_menu', [ContractsPage::class, 'register'], 11);
        add_action('admin_menu', [PaymentsPage::class, 'register'], 12);
        add_action('admin_menu', [CollectionsPage::class, 'register'], 13);
        add_action('admin_menu', [FollowUpsPage::class, 'register'], 14);
        add_action('admin_menu', [NotificationsPage::class, 'register'], 15);
        add_action('admin_menu', [ReportsPage::class, 'register'], 16);
        add_action('admin_menu', [UsersRolesPage::class, 'register'], 17);
        add_action('admin_menu', [GeneralSettingsPage::class, 'register'], 30);
        add_action('admin_menu', [PaymentMethodsPage::class, 'register'], 31);
        add_action('admin_menu', [NotificationSettingsPage::class, 'register'], 32);
        add_action('admin_menu', [FirebaseSettingsPage::class, 'register'], 33);
        add_action('admin_menu', [MobileConfigurationPage::class, 'register'], 34);
        add_action('admin_enqueue_scripts', [AdminShell::class, 'enqueueAssets']);

        add_action('admin_post_' . CustomersPage::SAVE_ACTION, [CustomersPage::class, 'handleSave']);
        add_action('admin_post_' . ContractsPage::SAVE_ACTION, [ContractsPage::class, 'handleSave']);
        add_action('admin_post_' . PaymentsPage::SAVE_ACTION, [PaymentsPage::class, 'handleSave']);
        add_action('admin_post_' . CollectionsPage::SAVE_ACTION, [CollectionsPage::class, 'handleSave']);
        add_action('admin_post_' . FollowUpsPage::SAVE_ACTION, [FollowUpsPage::class, 'handleSave']);
        add_action('admin_post_' . GeneralSettingsPage::SAVE_ACTION, [GeneralSettingsPage::class, 'handleSave']);
        add_action('admin_post_' . PaymentMethodsPage::SAVE_ACTION, [PaymentMethodsPage::class, 'handleSave']);
        add_action('admin_post_' . NotificationSettingsPage::SAVE_ACTION, [NotificationSettingsPage::class, 'handleSave']);
        add_action('admin_post_' . FirebaseSettingsPage::SAVE_ACTION, [FirebaseSettingsPage::class, 'handleSave']);
        add_action('admin_post_' . MobileConfigurationPage::SAVE_ACTION, [MobileConfigurationPage::class, 'handleSave']);
        do_action('safecontracts_loaded');
    }
}
