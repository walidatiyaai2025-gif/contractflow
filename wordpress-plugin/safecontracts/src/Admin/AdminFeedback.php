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
                <button type="button" class="safecontracts-toast__close" data-safecontracts-toast-close aria-label="<?php echo esc_attr(self::isArabic() ? 'إغلاق الرسالة' : 'Close message'); ?>">×</button>
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

        $ar = self::isArabic();
        return match ($status) {
            'saved' => ['success', $ar ? 'تم الحفظ' : 'Saved', $ar ? 'تم حفظ البيانات بنجاح.' : 'Your changes were saved successfully.'],
            'invalid' => ['error', $ar ? 'راجع البيانات' : 'Check the form', $ar ? 'تعذر الحفظ. راجع الحقول المطلوبة وصحة القيم ثم حاول مرة أخرى.' : 'The record could not be saved. Check required fields and entered values, then try again.'],
            'archived', 'deleted' => ['success', $ar ? 'تم الحذف الآمن' : 'Safely deleted', $ar ? 'تمت إزالة العنصر من العمليات النشطة مع الحفاظ على السجل التاريخي والمالي وسجل التدقيق عند الحاجة.' : 'The item was removed from active operations while required historical, financial and audit evidence was preserved.'],
            'archive_failed', 'delete_failed' => ['error', $ar ? 'تعذر الحذف' : 'Delete failed', $ar ? 'لم يتم الحذف. قد يكون العنصر محمياً بسجلات مرتبطة أو لا تسمح الصلاحيات الحالية بهذه العملية.' : 'The item could not be deleted. It may be protected by linked records or the current permissions may not allow this operation.'],
            'uploaded' => ['success', $ar ? 'تم الرفع' : 'Uploaded', $ar ? 'تم رفع الملف بنجاح.' : 'The file was uploaded successfully.'],
            'upload_failed' => ['error', $ar ? 'فشل الرفع' : 'Upload failed', $ar ? 'تعذر رفع الملف. راجع الملف والبيانات ثم حاول مرة أخرى.' : 'The file could not be uploaded. Check the file and input, then try again.'],
            'executed' => ['success', $ar ? 'تم التنفيذ' : 'Completed', $ar ? 'تم تنفيذ العملية بنجاح.' : 'The operation completed successfully.'],
            default => null,
        };
    }

    /** @return array<string,string> */
    private static function clientMessages(): array
    {
        $ar = self::isArabic();
        return [
            'validationTitle' => $ar ? 'راجع البيانات' : 'Check the form',
            'validationMessage' => $ar ? 'يرجى استكمال الحقول المطلوبة والتأكد من صحة القيم قبل المتابعة.' : 'Complete required fields and correct invalid values before continuing.',
            'fieldPrefix' => $ar ? 'أول حقل يحتاج مراجعة:' : 'First field to review:',
            'deleteConfirm' => $ar ? 'هل أنت متأكد من الحذف؟ سيتم إخراج السجل من العمليات النشطة مع الحفاظ على السجل التاريخي والمالي عند الحاجة.' : 'Delete this record from active SafeContracts operations? Required historical and financial evidence will be preserved.',
            'closeLabel' => $ar ? 'إغلاق الرسالة' : 'Close message',
        ];
    }

    private static function isArabic(): bool
    {
        $locale = function_exists('get_user_locale') ? (string) get_user_locale() : (function_exists('get_locale') ? (string) get_locale() : 'en_US');
        return str_starts_with(strtolower($locale), 'ar');
    }
}
