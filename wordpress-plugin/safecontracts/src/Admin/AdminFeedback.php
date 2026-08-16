<?php

declare(strict_types=1);

namespace SafeContracts\Admin;

use SafeContracts\Roles\Capabilities;

final class AdminFeedback
{
    public const STYLE_HANDLE = 'safecontracts-admin-feedback';
    public const SCRIPT_HANDLE = 'safecontracts-admin-feedback';

    public static function enqueueAssets(): void
    {
        if (! AdminShell::isSafeContractsPage()) {
            return;
        }

        wp_enqueue_style(
            self::STYLE_HANDLE,
            SAFECONTRACTS_URL . 'assets/admin/safecontracts-admin-feedback.css',
            [AdminShell::RESPONSIVE_STYLE_HANDLE],
            SAFECONTRACTS_VERSION
        );
        wp_enqueue_script(
            self::SCRIPT_HANDLE,
            SAFECONTRACTS_URL . 'assets/admin/safecontracts-admin-feedback.js',
            [],
            SAFECONTRACTS_VERSION,
            true
        );
        wp_localize_script(self::SCRIPT_HANDLE, 'SafeContractsAdminFeedback', self::clientMessages());
    }

    public static function render(): void
    {
        if (! AdminShell::isSafeContractsPage() || ! current_user_can(Capabilities::ACCESS)) {
            return;
        }

        $status = sanitize_key((string) ($_GET['safecontracts_status'] ?? ''));
        $feedback = self::feedbackForStatus($status);
        if ($feedback === null) {
            return;
        }

        [$type, $title, $message] = $feedback;
        ?>
        <div class="safecontracts-toast-stack" data-safecontracts-toast-stack aria-live="polite" aria-atomic="true">
            <div class="safecontracts-toast safecontracts-toast--<?php echo esc_attr($type); ?>" data-safecontracts-toast data-auto-dismiss="<?php echo $type === 'success' ? '1' : '0'; ?>" role="<?php echo $type === 'error' ? 'alert' : 'status'; ?>">
                <div class="safecontracts-toast__icon" aria-hidden="true"><?php echo $type === 'error' ? '!' : '✓'; ?></div>
                <div class="safecontracts-toast__body">
                    <strong><?php echo esc_html($title); ?></strong>
                    <p><?php echo esc_html($message); ?></p>
                </div>
                <button type="button" class="safecontracts-toast__close" data-safecontracts-toast-close aria-label="<?php echo esc_attr__('Close message', 'safecontracts'); ?>">×</button>
            </div>
        </div>
        <?php
    }

    /** @return array{0:string,1:string,2:string}|null */
    private static function feedbackForStatus(string $status): ?array
    {
        if ($status === '') {
            return null;
        }

        return match ($status) {
            'saved' => ['success', __('Saved', 'safecontracts'), __('Your changes were saved successfully.', 'safecontracts')],
            'invalid' => ['error', __('Check the form', 'safecontracts'), __('The record could not be saved. Check required fields and entered values, then try again.', 'safecontracts')],
            'archived', 'deleted' => ['success', __('Safely deleted', 'safecontracts'), __('The item was removed from active operations while required historical, financial and audit evidence was preserved.', 'safecontracts')],
            'archive_failed', 'delete_failed' => ['error', __('Delete failed', 'safecontracts'), __('The item could not be deleted. It may be protected by linked records or the current permissions may not allow this operation.', 'safecontracts')],
            'uploaded' => ['success', __('Uploaded', 'safecontracts'), __('The file was uploaded successfully.', 'safecontracts')],
            'upload_failed' => ['error', __('Upload failed', 'safecontracts'), __('The file could not be uploaded. Check the file and input, then try again.', 'safecontracts')],
            'executed' => ['success', __('Completed', 'safecontracts'), __('The operation completed successfully.', 'safecontracts')],
            'translations_saved' => ['success', __('Saved', 'safecontracts'), __('Translations saved.', 'safecontracts')],
            'translations_reset' => ['success', __('Saved', 'safecontracts'), __('Translations reset.', 'safecontracts')],
            default => null,
        };
    }

    /** @return array<string,string> */
    private static function clientMessages(): array
    {
        return [
            'validationTitle' => __('Check the form', 'safecontracts'),
            'validationMessage' => __('Complete required fields and correct invalid values before continuing.', 'safecontracts'),
            'fieldPrefix' => __('First field to review:', 'safecontracts'),
            'deleteConfirm' => __('Delete this record from active SafeContracts operations? Required historical and financial evidence will be preserved.', 'safecontracts'),
            'closeLabel' => __('Close message', 'safecontracts'),
        ];
    }
}
