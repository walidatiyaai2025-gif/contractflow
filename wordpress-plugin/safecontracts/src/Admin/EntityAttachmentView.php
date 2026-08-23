<?php

declare(strict_types=1);

namespace SafeContracts\Admin;

final class EntityAttachmentView
{
    /** @param list<array<string,mixed>> $attachments */
    public static function render(string $entityType, int $entityId, array $attachments, bool $canManage = false, bool $compact = false): void
    {
        if ($attachments === []) {
            echo '<span class="description">' . esc_html__('No attachments', 'safecontracts') . '</span>';
            return;
        }
        ?>
        <div class="safecontracts-entity-attachments<?php echo $compact ? ' is-compact' : ''; ?>" style="display:grid;gap:8px;">
            <?php foreach ($attachments as $row) : ?>
                <?php $attachment = CollectorAttachmentView::resolve($row['media_id'] ?? 0); ?>
                <?php if ($attachment === null) { continue; } ?>
                <div class="safecontracts-entity-attachment" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                    <?php if ($attachment['preview_url'] !== '') : ?>
                        <a href="<?php echo esc_url($attachment['url']); ?>" target="_blank" rel="noopener noreferrer"><img src="<?php echo esc_url($attachment['preview_url']); ?>" alt="" loading="lazy" style="width:48px;height:48px;object-fit:cover;border-radius:7px;"></a>
                    <?php endif; ?>
                    <div style="min-width:0;flex:1;">
                        <a href="<?php echo esc_url($attachment['url']); ?>" target="_blank" rel="noopener noreferrer"><strong><?php echo esc_html($attachment['title']); ?></strong></a>
                        <?php if (! $compact && (string) ($row['created_at'] ?? '') !== '') : ?><div class="description"><?php echo esc_html((string) $row['created_at']); ?></div><?php endif; ?>
                    </div>
                    <?php if ($canManage) : ?>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" data-safecontracts-delete-form data-delete-message="<?php echo esc_attr__('Remove this file from the record? The WordPress Media file itself will not be deleted.', 'safecontracts'); ?>">
                            <input type="hidden" name="action" value="<?php echo esc_attr(AttachmentAdminController::DETACH_ACTION); ?>">
                            <input type="hidden" name="entity_type" value="<?php echo esc_attr($entityType); ?>">
                            <input type="hidden" name="entity_id" value="<?php echo esc_attr((string) $entityId); ?>">
                            <input type="hidden" name="media_id" value="<?php echo esc_attr((string) $attachment['id']); ?>">
                            <?php wp_nonce_field(AttachmentAdminController::DETACH_ACTION . '_' . $entityType . '_' . $entityId . '_' . $attachment['id']); ?>
                            <button type="submit" class="button button-small safecontracts-delete-button"><?php echo esc_html__('Remove', 'safecontracts'); ?></button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
    }

    public static function renderUploadField(string $label = ''): void
    {
        $label = $label !== '' ? $label : __('Attachments', 'safecontracts');
        ?>
        <p><label><?php echo esc_html($label); ?><input class="widefat" type="file" name="<?php echo esc_attr(MultipleAttachmentUploader::FIELD); ?>[]" multiple accept=".pdf,.jpg,.jpeg,.png,.gif,.webp,.doc,.docx,.xls,.xlsx,.txt"></label></p>
        <p class="description"><?php echo esc_html(sprintf(__('Upload up to %d files at once. Supported: PDF, images, Word, Excel and text files.', 'safecontracts'), MultipleAttachmentUploader::MAX_FILES)); ?></p>
        <?php
    }

    public static function renderUploadForm(string $entityType, int $entityId, string $buttonLabel = ''): void
    {
        $buttonLabel = $buttonLabel !== '' ? $buttonLabel : __('Add files', 'safecontracts');
        ?>
        <form method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top:8px;">
            <input type="hidden" name="action" value="<?php echo esc_attr(AttachmentAdminController::UPLOAD_ACTION); ?>">
            <input type="hidden" name="entity_type" value="<?php echo esc_attr($entityType); ?>">
            <input type="hidden" name="entity_id" value="<?php echo esc_attr((string) $entityId); ?>">
            <?php wp_nonce_field(AttachmentAdminController::UPLOAD_ACTION . '_' . $entityType . '_' . $entityId); ?>
            <input type="file" name="<?php echo esc_attr(MultipleAttachmentUploader::FIELD); ?>[]" multiple required accept=".pdf,.jpg,.jpeg,.png,.gif,.webp,.doc,.docx,.xls,.xlsx,.txt">
            <button class="button button-small" type="submit"><?php echo esc_html($buttonLabel); ?></button>
        </form>
        <?php
    }
}
