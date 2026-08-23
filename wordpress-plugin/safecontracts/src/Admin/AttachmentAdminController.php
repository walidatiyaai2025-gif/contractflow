<?php

declare(strict_types=1);

namespace SafeContracts\Admin;

use SafeContracts\Attachments\EntityAttachmentService;
use SafeContracts\Support\Input;
use Throwable;

final class AttachmentAdminController
{
    public const UPLOAD_ACTION = 'safecontracts_upload_entity_attachments';
    public const DETACH_ACTION = 'safecontracts_detach_entity_attachment';

    public static function handleUpload(): void
    {
        $type = sanitize_key((string) ($_POST['entity_type'] ?? ''));
        $entityId = max(0, (int) ($_POST['entity_id'] ?? 0));
        check_admin_referer(self::UPLOAD_ACTION . '_' . $type . '_' . $entityId);
        $status = 'attachments_added';
        try {
            $service = new EntityAttachmentService();
            $service->assertCanManage($type, $entityId);
            $mediaIds = MultipleAttachmentUploader::upload();
            if ($mediaIds === []) {
                throw new \InvalidArgumentException('Select at least one attachment to upload.');
            }
            $service->attachMany($type, $entityId, $mediaIds);
        } catch (Throwable $error) {
            unset($error);
            $status = 'attachment_failed';
        }
        wp_safe_redirect(self::redirectUrl($type, $entityId, $status));
        exit;
    }

    public static function handleDetach(): void
    {
        $type = sanitize_key((string) ($_POST['entity_type'] ?? ''));
        $entityId = max(0, (int) ($_POST['entity_id'] ?? 0));
        $mediaId = max(0, (int) ($_POST['media_id'] ?? 0));
        check_admin_referer(self::DETACH_ACTION . '_' . $type . '_' . $entityId . '_' . $mediaId);
        $status = 'attachment_removed';
        try {
            (new EntityAttachmentService())->detach($type, $entityId, $mediaId);
        } catch (Throwable $error) {
            unset($error);
            $status = 'attachment_failed';
        }
        wp_safe_redirect(self::redirectUrl($type, $entityId, $status));
        exit;
    }

    private static function redirectUrl(string $type, int $entityId, string $status): string
    {
        $args = ['safecontracts_status' => $status];
        if ($type === EntityAttachmentService::CONTRACT) {
            $args['page'] = ContractsPage::SLUG;
            $args['contract_id'] = $entityId;
        } elseif ($type === EntityAttachmentService::PAYMENT) {
            $args['page'] = PaymentsPage::SLUG;
            $args['payment_id'] = $entityId;
        } else {
            $args['page'] = CollectionsPage::SLUG;
        }
        return add_query_arg($args, admin_url('admin.php'));
    }
}
