<?php

declare(strict_types=1);

namespace SafeContracts;

use SafeContracts\Admin\ActiveUsersPage;
use SafeContracts\Admin\AdminFeedback;
use SafeContracts\Admin\AdminPageSummaryInjector;
use SafeContracts\Admin\AdminShell;
use SafeContracts\Admin\ArchivePage;
use SafeContracts\Admin\AttachmentAdminController;
use SafeContracts\Admin\CollectionsPage;
use SafeContracts\Admin\ContractPaymentTree;
use SafeContracts\Admin\ContractsPage;
use SafeContracts\Admin\CustomersPage;
use SafeContracts\Admin\DashboardPage;
use SafeContracts\Admin\DemoDataController;
use SafeContracts\Admin\EmailSettingsPage;
use SafeContracts\Admin\FinancePage;
use SafeContracts\Admin\FirebaseSettingsPage;
use SafeContracts\Admin\FollowUpsPage;
use SafeContracts\Admin\GeneralSettingsPage;
use SafeContracts\Admin\ImportPeriodNotice;
use SafeContracts\Admin\ImportsPage;
use SafeContracts\Admin\LoginBranding;
use SafeContracts\Admin\MigrationRecoveryPage;
use SafeContracts\Admin\MobileConfigurationPage;
use SafeContracts\Admin\NavigationCleanup;
use SafeContracts\Admin\NotificationCenterPage;
use SafeContracts\Admin\NotificationEmailTestControl;
use SafeContracts\Admin\NotificationSchedulePage;
use SafeContracts\Admin\NotificationSettingsPage;
use SafeContracts\Admin\NotificationsPage;
use SafeContracts\Admin\PaymentMethodsPage;
use SafeContracts\Admin\PaymentsPage;
use SafeContracts\Admin\ReportsPage;
use SafeContracts\Admin\RuntimeInspectorPage;
use SafeContracts\Admin\SuppliersPage;
use SafeContracts\Admin\TranslationsPage;
use SafeContracts\Admin\UserGuidePage;
use SafeContracts\Admin\UsersRolesPage;
use SafeContracts\Audit\AuditRecorder;
use SafeContracts\Audit\ContractArchiveAuditRecorder;
use SafeContracts\Audit\NotificationCenterAuditRecorder;
use SafeContracts\Audit\NotificationScheduleAuditRecorder;
use SafeContracts\Audit\SafeDeletionAuditRecorder;
use SafeContracts\Auth\MobileBearerAuthentication;
use SafeContracts\Contracts\ContractHistoryRecorder;
use SafeContracts\Database\MigrationGuard;
use SafeContracts\Database\Migrator;
use SafeContracts\Diagnostics\RuntimeInspector;
use SafeContracts\Notifications\FirebaseAccessTokenProvider;
use SafeContracts\Notifications\NotificationScheduler;
use SafeContracts\Presence\PresenceService;
use SafeContracts\Rest\Router;
use SafeContracts\Translations\AdminArabicDefaults;
use SafeContracts\Translations\ControlledInputArabicDefaults;
use SafeContracts\Translations\FeatureArabicDefaults;
use SafeContracts\Translations\MigrationRecoveryArabicDefaults;
use SafeContracts\Translations\NotificationCenterArabicDefaults;
use SafeContracts\Translations\NotificationScheduleArabicDefaults;
use SafeContracts\Translations\ProductionUxArabicDefaults;
use SafeContracts\Translations\RuntimeLabels;
use SafeContracts\Translations\TranslationCatalog;
use Throwable;

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

        TranslationCatalog::register();
        AdminArabicDefaults::register();
        FeatureArabicDefaults::register();
        ProductionUxArabicDefaults::register();
        MigrationRecoveryArabicDefaults::register();
        ControlledInputArabicDefaults::register();
        NotificationScheduleArabicDefaults::register();
        NotificationCenterArabicDefaults::register();
        RuntimeLabels::register();

        try {
            (new Migrator())->maybeMigrate();
        } catch (Throwable) {
            MigrationRecoveryPage::register();
            do_action('safecontracts_database_migration_blocked', MigrationGuard::failureState());
            return;
        }

        MobileBearerAuthentication::register();
        PresenceService::register();
        FirebaseAccessTokenProvider::register();
        NotificationScheduler::register();
        ContractHistoryRecorder::register();
        AuditRecorder::register();
        ContractArchiveAuditRecorder::register();
        NotificationCenterAuditRecorder::register();
        NotificationScheduleAuditRecorder::register();
        SafeDeletionAuditRecorder::register();
        LoginBranding::register();
        EmailSettingsPage::register();
        NavigationCleanup::register();
        AdminPageSummaryInjector::register();
        ContractPaymentTree::register();
        UserGuidePage::registerContextualHelp();
        NotificationEmailTestControl::register();
        RuntimeInspector::register();

        add_action('rest_api_init', [Router::class, 'register']);
        add_action('admin_menu', [AdminShell::class, 'register'], 5);
        add_action('admin_menu', [CustomersPage::class, 'register'], 10);
        add_action('admin_menu', [SuppliersPage::class, 'register'], 11);
        add_action('admin_menu', [ContractsPage::class, 'register'], 11);
        add_action('admin_menu', [PaymentsPage::class, 'register'], 12);
        add_action('admin_menu', [CollectionsPage::class, 'register'], 13);
        add_action('admin_menu', [FollowUpsPage::class, 'register'], 14);
        add_action('admin_menu', [NotificationCenterPage::class, 'register'], 15);
        add_action('admin_menu', [NotificationsPage::class, 'register'], 16);
        add_action('admin_menu', [NotificationSchedulePage::class, 'register'], 17);
        add_action('admin_menu', [FinancePage::class, 'register'], 18);
        add_action('admin_menu', [ReportsPage::class, 'register'], 18);
        add_action('admin_menu', [ActiveUsersPage::class, 'register'], 19);
        add_action('admin_menu', [UsersRolesPage::class, 'register'], 20);
        add_action('admin_menu', [ArchivePage::class, 'register'], 21);
        add_action('admin_menu', [ImportsPage::class, 'register'], 22);
        add_action('admin_menu', [GeneralSettingsPage::class, 'register'], 30);
        add_action('admin_menu', [PaymentMethodsPage::class, 'register'], 31);
        add_action('admin_menu', [NotificationSettingsPage::class, 'register'], 32);
        add_action('admin_menu', [FirebaseSettingsPage::class, 'register'], 33);
        add_action('admin_menu', [MobileConfigurationPage::class, 'register'], 34);
        add_action('admin_menu', [TranslationsPage::class, 'register'], 35);
        add_action('admin_menu', [UserGuidePage::class, 'register'], 36);
        add_action('admin_menu', [RuntimeInspectorPage::class, 'register'], 37);
        add_action('admin_enqueue_scripts', [AdminShell::class, 'enqueueAssets']);
        add_action('admin_enqueue_scripts', [AdminFeedback::class, 'enqueueAssets'], 20);
        add_action('admin_notices', [RuntimeInspectorPage::class, 'renderCapturedNotice'], 8);
        add_action('admin_notices', [AdminFeedback::class, 'render']);
        add_action('admin_notices', [ImportPeriodNotice::class, 'render'], 30);

        add_action('admin_post_' . CustomersPage::SAVE_ACTION, [CustomersPage::class, 'handleSave']);
        add_action('admin_post_' . CustomersPage::DELETE_ACTION, [CustomersPage::class, 'handleDelete']);
        add_action('admin_post_' . SuppliersPage::SAVE_ACTION, [SuppliersPage::class, 'handleSave']);
        add_action('admin_post_' . SuppliersPage::ARCHIVE_ACTION, [SuppliersPage::class, 'handleArchive']);
        add_action('admin_post_' . ContractsPage::SAVE_ACTION, [ContractsPage::class, 'handleSave']);
        add_action('admin_post_' . ContractsPage::BULK_ASSIGN_ACTION, [ContractsPage::class, 'handleBulkAssign']);
        add_action('admin_post_' . ContractsPage::DELETE_ACTION, [ContractsPage::class, 'handleDelete']);
        add_action('admin_post_' . DashboardPage::ARCHIVE_ACTION, [DashboardPage::class, 'handleArchive']);
        add_action('admin_post_' . DemoDataController::CREATE_ACTION, [DemoDataController::class, 'handleCreate']);
        add_action('admin_post_' . DemoDataController::DELETE_ACTION, [DemoDataController::class, 'handleDelete']);
        add_action('admin_post_' . PaymentsPage::SAVE_ACTION, [PaymentsPage::class, 'handleSave']);
        add_action('admin_post_' . PaymentsPage::DELETE_ACTION, [PaymentsPage::class, 'handleDelete']);
        add_action('admin_post_' . CollectionsPage::SAVE_ACTION, [CollectionsPage::class, 'handleSave']);
        add_action('admin_post_' . CollectionsPage::DELETE_ACTION, [CollectionsPage::class, 'handleDelete']);
        add_action('admin_post_' . AttachmentAdminController::UPLOAD_ACTION, [AttachmentAdminController::class, 'handleUpload']);
        add_action('admin_post_' . AttachmentAdminController::DETACH_ACTION, [AttachmentAdminController::class, 'handleDetach']);
        add_action('admin_post_' . FollowUpsPage::SAVE_ACTION, [FollowUpsPage::class, 'handleSave']);
        add_action('admin_post_' . ReportsPage::EXPORT_ACTION, [ReportsPage::class, 'handleExport']);
        add_action('admin_post_' . ImportsPage::UPLOAD_ACTION, [ImportsPage::class, 'handleUpload']);
        add_action('admin_post_' . ImportsPage::MAP_ACTION, [ImportsPage::class, 'handleMapping']);
        add_action('admin_post_' . ImportsPage::EXECUTE_ACTION, [ImportsPage::class, 'handleExecute']);
        add_action('admin_post_' . GeneralSettingsPage::SAVE_ACTION, [GeneralSettingsPage::class, 'handleSave']);
        add_action('admin_post_' . PaymentMethodsPage::SAVE_ACTION, [PaymentMethodsPage::class, 'handleSave']);
        add_action('admin_post_' . PaymentMethodsPage::DELETE_ACTION, [PaymentMethodsPage::class, 'handleDelete']);
        add_action('admin_post_' . NotificationSettingsPage::SAVE_ACTION, [NotificationSettingsPage::class, 'handleSave']);
        add_action('admin_post_' . NotificationSettingsPage::TOGGLE_ACTION, [NotificationSettingsPage::class, 'handleToggle']);
        add_action('admin_post_' . NotificationSettingsPage::DELETE_ACTION, [NotificationSettingsPage::class, 'handleDelete']);
        add_action('admin_post_' . NotificationCenterPage::SAVE_RULE_ACTION, [NotificationCenterPage::class, 'handleSaveRule']);
        add_action('admin_post_' . NotificationCenterPage::SAVE_TEMPLATE_ACTION, [NotificationCenterPage::class, 'handleSaveTemplate']);
        add_action('admin_post_' . NotificationCenterPage::SAVE_EMAIL_ACTION, [NotificationCenterPage::class, 'handleSaveEmail']);
        add_action('admin_post_' . NotificationCenterPage::DIRECT_SEND_ACTION, [NotificationCenterPage::class, 'handleDirectSend']);
        add_action('admin_post_' . NotificationCenterPage::SUPPRESSION_ACTION, [NotificationCenterPage::class, 'handleSuppression']);
        add_action('admin_post_' . NotificationSchedulePage::MANUAL_SEND_ACTION, [NotificationSchedulePage::class, 'handleManualSend']);
        add_action('admin_post_' . NotificationSchedulePage::SAVE_TIME_ACTION, [NotificationSchedulePage::class, 'handleSaveTime']);
        add_action('admin_post_' . UsersRolesPage::SAVE_CAPABILITIES_ACTION, [UsersRolesPage::class, 'handleSaveCapabilities']);
        add_action('admin_post_' . UsersRolesPage::ASSIGN_ROLE_ACTION, [UsersRolesPage::class, 'handleAssignRole']);
        add_action('admin_post_' . FirebaseSettingsPage::SAVE_ACTION, [FirebaseSettingsPage::class, 'handleSave']);
        add_action('admin_post_' . FirebaseSettingsPage::UPLOAD_ACTION, [FirebaseSettingsPage::class, 'handleUpload']);
        add_action('admin_post_' . FirebaseSettingsPage::DELETE_ACTION, [FirebaseSettingsPage::class, 'handleDelete']);
        add_action('admin_post_' . FirebaseSettingsPage::TEST_ACTION, [FirebaseSettingsPage::class, 'handleTest']);
        add_action('admin_post_' . FirebaseSettingsPage::TEST_PUSH_ACTION, [FirebaseSettingsPage::class, 'handleTestPush']);
        add_action('admin_post_' . MobileConfigurationPage::SAVE_ACTION, [MobileConfigurationPage::class, 'handleSave']);
        add_action('admin_post_' . TranslationsPage::SAVE_ACTION, [TranslationsPage::class, 'handleSave']);
        add_action('admin_post_' . RuntimeInspectorPage::CLEAR_ACTION, [RuntimeInspectorPage::class, 'handleClear']);
        do_action('safecontracts_loaded');
    }
}
