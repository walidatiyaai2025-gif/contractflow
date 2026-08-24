<?php

declare(strict_types=1);

namespace SafeContracts\Admin;

use SafeContracts\Notifications\DeviceTokenRepository;
use SafeContracts\Notifications\FirebaseAccessTokenProvider;
use SafeContracts\Notifications\FirebaseServiceAccountVault;
use SafeContracts\Notifications\FirebaseSettings;
use SafeContracts\Notifications\FirebaseTestNotificationService;
use SafeContracts\Roles\Capabilities;
use SafeContracts\Support\Input;
use Throwable;

final class FirebaseSettingsPage
{
    public const SLUG = 'safecontracts-firebase-settings';
    public const SAVE_ACTION = 'safecontracts_save_firebase_settings';
    public const UPLOAD_ACTION = 'safecontracts_upload_firebase_service_account';
    public const DELETE_ACTION = 'safecontracts_delete_firebase_service_account';
    public const TEST_ACTION = 'safecontracts_test_firebase_connection';
    public const TEST_PUSH_ACTION = 'safecontracts_send_firebase_test_notification';

    public static function register(): void
    {
        add_submenu_page(AdminShell::SLUG, __('Firebase Settings', 'safecontracts'), __('Firebase Settings', 'safecontracts'), Capabilities::MANAGE_SYSTEM, self::SLUG, [self::class, 'render']);
    }

    public static function handleSave(): void
    {
        self::assertManage();
        check_admin_referer(self::SAVE_ACTION);
        $status = 'saved';
        try {
            $settings = new FirebaseSettings();
            $settings->savePublic([
                'project_id' => Input::string($_POST['project_id'] ?? '', 'Firebase project ID'),
                'messaging_sender_id' => Input::string($_POST['messaging_sender_id'] ?? '', 'Firebase messaging sender ID'),
                'app_id' => Input::string($_POST['app_id'] ?? '', 'Firebase app ID'),
            ]);
            $reference = trim(Input::string($_POST['credential_reference'] ?? '', 'Firebase credential reference'));
            if ($reference !== '') {
                $settings->saveCredentialReference($reference);
            }
        } catch (Throwable $error) {
            unset($error);
            $status = 'invalid';
        }
        self::redirect($status);
    }

    public static function handleUpload(): void
    {
        self::assertManage();
        check_admin_referer(self::UPLOAD_ACTION);
        $status = 'credential_saved';
        try {
            $file = $_FILES['service_account_json'] ?? null;
            if (! is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                throw new \InvalidArgumentException('Firebase service-account upload failed.');
            }
            $size = (int) ($file['size'] ?? 0);
            $tmpName = (string) ($file['tmp_name'] ?? '');
            if ($size <= 0 || $size > FirebaseServiceAccountVault::MAX_JSON_BYTES || $tmpName === '' || ! is_uploaded_file($tmpName)) {
                throw new \InvalidArgumentException('Firebase service-account upload is invalid.');
            }
            $json = file_get_contents($tmpName);
            if (! is_string($json) || $json === '') {
                throw new \InvalidArgumentException('Firebase service-account upload cannot be read.');
            }
            $settings = new FirebaseSettings();
            $projectId = trim((string) $settings->publicConfig()['project_id']);
            (new FirebaseServiceAccountVault())->storeJson($json, $projectId);
            $settings->saveCredentialReference(FirebaseServiceAccountVault::REFERENCE);
        } catch (Throwable $error) {
            unset($error);
            $status = 'credential_invalid';
        }
        self::redirect($status);
    }

    public static function handleDelete(): void
    {
        self::assertManage();
        check_admin_referer(self::DELETE_ACTION);
        $status = 'credential_deleted';
        try {
            $settings = new FirebaseSettings();
            (new FirebaseServiceAccountVault())->delete();
            if ($settings->credentialReference() === FirebaseServiceAccountVault::REFERENCE) {
                $settings->clearCredentialReference();
            }
        } catch (Throwable $error) {
            unset($error);
            $status = 'credential_delete_failed';
        }
        self::redirect($status);
    }

    public static function handleTest(): void
    {
        self::assertManage();
        check_admin_referer(self::TEST_ACTION);
        $status = 'test_failed';
        try {
            $projectId = trim((string) (new FirebaseSettings())->publicConfig()['project_id']);
            $result = (new FirebaseAccessTokenProvider())->testConnection($projectId);
            $status = $result['success'] ? 'test_ok' : 'test_failed';
        } catch (Throwable $error) {
            unset($error);
        }
        self::redirect($status);
    }

    public static function handleTestPush(): void
    {
        self::assertManage();
        check_admin_referer(self::TEST_PUSH_ACTION);
        $status = 'test_push_failed';
        try {
            $result = (new FirebaseTestNotificationService())->sendForUser(get_current_user_id());
            $status = match ($result['status']) {
                'ok' => 'test_push_ok',
                'partial' => 'test_push_partial',
                'no_device' => 'test_push_no_device',
                'other_user_device' => 'test_push_other_user_device',
                'no_usable_token' => 'test_push_no_usable_token',
                default => self::testPushFailureStatus($result['error_codes']),
            };
        } catch (Throwable $error) {
            unset($error);
        }
        self::redirect($status);
    }

    public static function render(): void
    {
        if (! current_user_can(Capabilities::MANAGE_SYSTEM)) {
            wp_die(__('You do not have permission to manage Firebase settings.', 'safecontracts'));
        }
        $settings = new FirebaseSettings();
        $summary = $settings->safeSummary();
        $reference = $settings->credentialReference();
        $projectId = trim((string) $summary['project_id']);
        $senderId = trim((string) $summary['messaging_sender_id']);
        $appId = trim((string) $summary['app_id']);
        $publicReady = ! empty($summary['configured']) && $projectId !== '' && $senderId !== '' && $appId !== '';
        $publicFields = (int) ($projectId !== '') + (int) ($senderId !== '') + (int) ($appId !== '');
        $vault = new FirebaseServiceAccountVault();
        $metadata = $vault->metadata();
        $vaultReady = $reference === FirebaseServiceAccountVault::REFERENCE && $vault->configured($projectId);
        $notice = self::notice(sanitize_key((string) ($_GET['safecontracts_status'] ?? '')));
        $currentUserId = get_current_user_id();
        $deviceDiagnostics = [
            'current_user_active_devices' => 0,
            'active_devices' => 0,
            'active_users' => 0,
            'truncated' => false,
        ];
        try {
            $deviceDiagnostics = (new DeviceTokenRepository())->activeDiagnostics($currentUserId);
        } catch (Throwable $error) {
            unset($error);
        }
        ?>
        <div class="wrap safecontracts-settings safecontracts-firebase-settings" dir="auto">
            <div class="safecontracts-section-heading">
                <div>
                    <p class="safecontracts-admin-shell__eyebrow"><?php echo esc_html__('Push infrastructure', 'safecontracts'); ?></p>
                    <h1><?php echo esc_html__('Firebase Settings', 'safecontracts'); ?></h1>
                    <p><?php echo esc_html__('Configure public Firebase identifiers, encrypted server credentials, connectivity checks and real device diagnostics without exposing private keys or device tokens.', 'safecontracts'); ?></p>
                </div>
                <span class="safecontracts-state-chip <?php echo $publicReady ? 'is-success' : 'is-warning'; ?>"><?php echo $publicReady ? esc_html__('Public config complete', 'safecontracts') : esc_html__('Public config incomplete', 'safecontracts'); ?></span>
            </div>
            <?php if ($notice !== null) : ?><div class="notice <?php echo esc_attr($notice['class']); ?> is-dismissible"><p><?php echo esc_html($notice['message']); ?></p></div><?php endif; ?>

            <?php AdminSummaryCards::render([
                ['label' => __('Public fields configured', 'safecontracts'), 'value' => $publicFields, 'detail' => __('of 3 required fields', 'safecontracts')],
                ['label' => __('Active devices for this user', 'safecontracts'), 'value' => (int) $deviceDiagnostics['current_user_active_devices']],
                ['label' => __('Active devices', 'safecontracts'), 'value' => (int) $deviceDiagnostics['active_devices']],
                ['label' => __('Users with active devices', 'safecontracts'), 'value' => (int) $deviceDiagnostics['active_users']],
            ]); ?>

            <div class="safecontracts-split-layout">
                <section class="safecontracts-admin-card safecontracts-settings-card">
                    <div class="safecontracts-section-heading"><div><h2><?php echo esc_html__('Firebase project', 'safecontracts'); ?></h2><p class="description"><?php echo esc_html__('These are public project identifiers only.', 'safecontracts'); ?></p></div></div>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="<?php echo esc_attr(self::SAVE_ACTION); ?>"><?php wp_nonce_field(self::SAVE_ACTION); ?>
                        <p><label><?php echo esc_html__('Firebase project ID', 'safecontracts'); ?><input class="widefat" dir="ltr" name="project_id" maxlength="191" required value="<?php echo esc_attr((string) $summary['project_id']); ?>"></label></p>
                        <p><label><?php echo esc_html__('Messaging sender ID', 'safecontracts'); ?><input class="widefat" dir="ltr" name="messaging_sender_id" maxlength="32" inputmode="numeric" required value="<?php echo esc_attr((string) $summary['messaging_sender_id']); ?>"></label></p>
                        <p><label><?php echo esc_html__('Firebase app ID', 'safecontracts'); ?><input class="widefat" dir="ltr" name="app_id" maxlength="191" required value="<?php echo esc_attr((string) $summary['app_id']); ?>"></label></p>
                        <details>
                            <summary><?php echo esc_html__('Advanced external credential provider', 'safecontracts'); ?></summary>
                            <p><label><?php echo esc_html__('Credential reference', 'safecontracts'); ?><input class="widefat" dir="ltr" name="credential_reference" maxlength="128" value="<?php echo esc_attr($reference); ?>" placeholder="SAFECONTRACTS_FIREBASE_SERVICE_ACCOUNT"></label></p>
                            <p class="description"><?php echo esc_html__('Use only a server-side credential reference here. Never paste service-account JSON, private keys or OAuth tokens into this field.', 'safecontracts'); ?></p>
                        </details>
                        <?php submit_button(__('Save Firebase Settings', 'safecontracts')); ?>
                    </form>
                </section>

                <section class="safecontracts-admin-card">
                    <div class="safecontracts-section-heading"><div><h2><?php echo esc_html__('Security boundary', 'safecontracts'); ?></h2></div></div>
                    <p><?php echo esc_html__('SafeContracts never renders the Firebase private key, OAuth access token, bearer token or FCM device token on this page.', 'safecontracts'); ?></p>
                    <p><?php echo esc_html__('The service-account upload is validated and converted immediately to encrypted vault storage. Only non-secret metadata and a key fingerprint are shown afterward.', 'safecontracts'); ?></p>
                </section>
            </div>

            <section class="safecontracts-admin-card safecontracts-settings-card">
                <div class="safecontracts-section-heading">
                    <div><h2><?php echo esc_html__('Server Service Account', 'safecontracts'); ?></h2><p class="description"><?php echo esc_html__('Encrypted credential state and server-side connectivity controls.', 'safecontracts'); ?></p></div>
                    <span class="safecontracts-state-chip <?php echo $vaultReady ? 'is-success' : 'is-warning'; ?>"><?php echo $vaultReady ? esc_html__('Connected credential', 'safecontracts') : esc_html__('Not configured', 'safecontracts'); ?></span>
                </div>

                <?php if ($metadata !== null) : ?>
                    <table class="widefat striped"><tbody>
                        <tr><th><?php echo esc_html__('Project', 'safecontracts'); ?></th><td dir="ltr"><?php echo esc_html($metadata['project_id']); ?></td></tr>
                        <tr><th><?php echo esc_html__('Service account', 'safecontracts'); ?></th><td dir="ltr"><?php echo esc_html($metadata['client_email']); ?></td></tr>
                        <tr><th><?php echo esc_html__('Key fingerprint', 'safecontracts'); ?></th><td><code dir="ltr"><?php echo esc_html($metadata['key_fingerprint']); ?></code></td></tr>
                        <tr><th><?php echo esc_html__('Stored at', 'safecontracts'); ?></th><td><code dir="ltr"><?php echo esc_html($metadata['stored_at']); ?></code></td></tr>
                    </tbody></table>
                <?php else : ?>
                    <p><?php echo esc_html__('No encrypted service-account metadata is currently available.', 'safecontracts'); ?></p>
                <?php endif; ?>

                <form method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="<?php echo esc_attr(self::UPLOAD_ACTION); ?>"><?php wp_nonce_field(self::UPLOAD_ACTION); ?>
                    <p><label><strong><?php echo esc_html($metadata === null ? __('Service Account JSON', 'safecontracts') : __('Replace Service Account JSON', 'safecontracts')); ?></strong><input type="file" class="widefat" name="service_account_json" accept="application/json,.json" required></label></p>
                    <p class="description"><?php echo esc_html__('Maximum 64 KB. Uploaded plaintext exists only during this request and is converted immediately into encrypted credential storage.', 'safecontracts'); ?></p>
                    <?php submit_button($metadata === null ? __('Upload Service Account', 'safecontracts') : __('Replace Service Account', 'safecontracts'), 'primary'); ?>
                </form>

                <h3><?php echo esc_html__('Mobile device registration', 'safecontracts'); ?></h3>
                <table class="widefat striped"><tbody>
                    <tr><th><?php echo esc_html__('Current WordPress user ID', 'safecontracts'); ?></th><td><?php echo esc_html((string) $currentUserId); ?></td></tr>
                    <tr><th><?php echo esc_html__('Active devices for this user', 'safecontracts'); ?></th><td><?php echo esc_html((string) $deviceDiagnostics['current_user_active_devices']); ?></td></tr>
                    <tr><th><?php echo esc_html__('Active devices in SafeContracts', 'safecontracts'); ?></th><td><?php echo esc_html((string) $deviceDiagnostics['active_devices']); ?><?php echo $deviceDiagnostics['truncated'] ? esc_html__(' (500-user diagnostic limit reached)', 'safecontracts') : ''; ?></td></tr>
                    <tr><th><?php echo esc_html__('Users with active devices', 'safecontracts'); ?></th><td><?php echo esc_html((string) $deviceDiagnostics['active_users']); ?></td></tr>
                </tbody></table>
                <p class="description"><?php echo esc_html__('Compare the WordPress user ID above with User ID in the SafeContracts mobile Profile. No FCM token or bearer credential is displayed here.', 'safecontracts'); ?></p>

                <?php if ($metadata !== null) : ?>
                    <div class="safecontracts-heading-actions">
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="<?php echo esc_attr(self::TEST_ACTION); ?>"><?php wp_nonce_field(self::TEST_ACTION); ?><?php submit_button(__('Test Firebase Connection', 'safecontracts'), 'secondary', 'submit', false); ?></form>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="<?php echo esc_attr(self::TEST_PUSH_ACTION); ?>"><?php wp_nonce_field(self::TEST_PUSH_ACTION); ?><?php submit_button(__('Send Test Notification', 'safecontracts'), 'secondary', 'submit', false); ?></form>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('<?php echo esc_js(__('Delete the stored Firebase service account?', 'safecontracts')); ?>');"><input type="hidden" name="action" value="<?php echo esc_attr(self::DELETE_ACTION); ?>"><?php wp_nonce_field(self::DELETE_ACTION); ?><?php submit_button(__('Delete Credential', 'safecontracts'), 'delete', 'submit', false); ?></form>
                    </div>
                    <p class="description"><?php echo esc_html__('Test Notification targets every active device registered for this exact WordPress user. Existing Firebase delivery semantics and token deactivation behavior remain unchanged.', 'safecontracts'); ?></p>
                <?php endif; ?>
            </section>
        </div>
        <?php
    }

    private static function assertManage(): void
    {
        if (! current_user_can(Capabilities::MANAGE_SYSTEM)) {
            wp_die(__('You do not have permission to manage Firebase settings.', 'safecontracts'));
        }
    }

    private static function redirect(string $status): never
    {
        wp_safe_redirect(add_query_arg(['page' => self::SLUG, 'safecontracts_status' => sanitize_key($status)], admin_url('admin.php')));
        exit;
    }

    /** @param list<string> $errorCodes */
    private static function testPushFailureStatus(array $errorCodes): string
    {
        if (in_array('firebase_token_not_found', $errorCodes, true)) {
            return 'test_push_token_not_found';
        }
        if (in_array('firebase_sender_id_mismatch', $errorCodes, true)) {
            return 'test_push_sender_id_mismatch';
        }
        if (in_array('firebase_invalid_argument', $errorCodes, true)) {
            return 'test_push_invalid_argument';
        }
        if (in_array('firebase_permission_denied', $errorCodes, true) || in_array('firebase_http_403', $errorCodes, true)) {
            return 'test_push_permission_denied';
        }
        if (in_array('firebase_auth_failed', $errorCodes, true) || in_array('firebase_auth_unavailable', $errorCodes, true) || in_array('firebase_http_401', $errorCodes, true)) {
            return 'test_push_auth_failed';
        }
        if (in_array('firebase_quota_exceeded', $errorCodes, true)) {
            return 'test_push_quota_exceeded';
        }
        if (in_array('firebase_unavailable', $errorCodes, true)) {
            return 'test_push_unavailable';
        }
        return 'test_push_failed';
    }

    /** @return array{class:string,message:string}|null */
    private static function notice(string $status): ?array
    {
        return match ($status) {
            'saved' => ['class' => 'notice-success', 'message' => __('Firebase settings saved.', 'safecontracts')],
            'credential_saved' => ['class' => 'notice-success', 'message' => __('Firebase service account encrypted and saved successfully.', 'safecontracts')],
            'credential_deleted' => ['class' => 'notice-success', 'message' => __('Firebase service account deleted.', 'safecontracts')],
            'test_ok' => ['class' => 'notice-success', 'message' => __('Firebase OAuth and FCM HTTP v1 authorization test succeeded.', 'safecontracts')],
            'test_push_ok' => ['class' => 'notice-success', 'message' => __('Test notification sent to all active SafeContracts devices registered to this WordPress user.', 'safecontracts')],
            'test_push_partial' => ['class' => 'notice-warning', 'message' => __('Test notification reached some, but not all, active devices for this WordPress user. SafeContracts continued sending to the remaining devices and deactivated any device token Firebase reported as unregistered.', 'safecontracts')],
            'test_push_no_device' => ['class' => 'notice-warning', 'message' => __('No active SafeContracts mobile device is registered yet. Open Mobile Profile and use Retry device registration.', 'safecontracts')],
            'test_push_other_user_device' => ['class' => 'notice-warning', 'message' => __('Active SafeContracts devices exist, but none belong to this WordPress user. Compare the WordPress user ID on this page with User ID in the mobile Profile.', 'safecontracts')],
            'test_push_no_usable_token' => ['class' => 'notice-warning', 'message' => __('Active device records belong to this WordPress user, but none currently contains a usable FCM token. Open Mobile Profile and use Retry device registration.', 'safecontracts')],
            'test_push_token_not_found' => ['class' => 'notice-error', 'message' => __('Firebase reported the registered device token as unregistered or not found. SafeContracts deactivated the rejected device registration; retry device registration from Mobile Profile.', 'safecontracts')],
            'test_push_sender_id_mismatch' => ['class' => 'notice-error', 'message' => __('Firebase rejected the device because its FCM token belongs to a different Firebase sender or project. Verify the mobile Firebase configuration matches this project.', 'safecontracts')],
            'test_push_invalid_argument' => ['class' => 'notice-error', 'message' => __('Firebase rejected the test notification as invalid. Verify the Firebase project, app registration and device token configuration.', 'safecontracts')],
            'test_push_permission_denied' => ['class' => 'notice-error', 'message' => __('Firebase denied permission to send the notification. Verify the service account belongs to this project and has Firebase Cloud Messaging send permission.', 'safecontracts')],
            'test_push_auth_failed' => ['class' => 'notice-error', 'message' => __('Firebase authentication failed. Verify the stored service account and project configuration.', 'safecontracts')],
            'test_push_quota_exceeded' => ['class' => 'notice-error', 'message' => __('Firebase rejected the test because the messaging quota is exhausted. Review the Firebase project quota before retrying.', 'safecontracts')],
            'test_push_unavailable' => ['class' => 'notice-error', 'message' => __('Firebase Cloud Messaging is temporarily unavailable. Retry the test after the service recovers.', 'safecontracts')],
            'invalid' => ['class' => 'notice-error', 'message' => __('Firebase settings are invalid.', 'safecontracts')],
            'credential_invalid' => ['class' => 'notice-error', 'message' => __('The Firebase service-account JSON is invalid or does not match this Firebase project.', 'safecontracts')],
            'credential_delete_failed' => ['class' => 'notice-error', 'message' => __('Firebase service-account deletion failed.', 'safecontracts')],
            'test_failed' => ['class' => 'notice-error', 'message' => __('Firebase connection test failed. Verify the service account and its FCM permissions.', 'safecontracts')],
            'test_push_failed' => ['class' => 'notice-error', 'message' => __('Firebase test notification failed for every usable device registered to this WordPress user. Review the Firebase project, credential and device registration diagnostics.', 'safecontracts')],
            default => null,
        };
    }
}
