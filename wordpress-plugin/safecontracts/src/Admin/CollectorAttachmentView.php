<?php

declare(strict_types=1);

namespace SafeContracts\Admin;

final class CollectorAttachmentView
{
    /** @return array{id:int,title:string,url:string,preview_url:string,mime:string}|null */
    public static function resolve(mixed $mediaId): ?array
    {
        $mediaId = (int) $mediaId;
        if ($mediaId <= 0 || ! function_exists('get_post_type') || get_post_type($mediaId) !== 'attachment') {
            return null;
        }

        $url = function_exists('wp_get_attachment_url') ? wp_get_attachment_url($mediaId) : false;
        if (! is_string($url) || $url === '') {
            return null;
        }

        $mime = function_exists('get_post_mime_type') ? (string) get_post_mime_type($mediaId) : '';
        $title = function_exists('get_the_title') ? trim((string) get_the_title($mediaId)) : '';
        if ($title === '') {
            $title = sprintf(__('Attachment #%d', 'safecontracts'), $mediaId);
        }

        $preview = '';
        if (str_starts_with($mime, 'image/') && function_exists('wp_get_attachment_image_url')) {
            $image = wp_get_attachment_image_url($mediaId, 'medium');
            if (is_string($image)) {
                $preview = $image;
            }
        }

        return [
            'id' => $mediaId,
            'title' => $title,
            'url' => $url,
            'preview_url' => $preview,
            'mime' => $mime,
        ];
    }

    /** @param array<string,mixed> $collection */
    public static function render(array $collection, bool $compact = false): void
    {
        $attachment = self::resolve($collection['proof_media_id'] ?? null);
        if ($attachment === null) {
            echo '<span aria-hidden="true">—</span>';
            return;
        }

        $collector = '';
        $createdBy = (int) ($collection['created_by'] ?? 0);
        if ($createdBy > 0 && function_exists('get_userdata')) {
            $user = get_userdata($createdBy);
            if (is_object($user) && isset($user->display_name)) {
                $collector = trim((string) $user->display_name);
            }
        }
        ?>
        <div class="safecontracts-collector-proof<?php echo $compact ? ' is-compact' : ''; ?>">
            <?php if ($attachment['preview_url'] !== '') : ?>
                <a href="<?php echo esc_url($attachment['url']); ?>" target="_blank" rel="noopener noreferrer" aria-label="<?php echo esc_attr(sprintf(__('Preview %s', 'safecontracts'), $attachment['title'])); ?>">
                    <img src="<?php echo esc_url($attachment['preview_url']); ?>" alt="" loading="lazy" style="width:64px;height:64px;object-fit:cover;border-radius:8px;">
                </a>
            <?php endif; ?>
            <div>
                <strong><?php echo esc_html($attachment['title']); ?></strong>
                <?php if ($collector !== '') : ?><div class="description"><?php echo esc_html(sprintf(__('Collector: %s', 'safecontracts'), $collector)); ?></div><?php endif; ?>
                <?php if (! $compact) : ?><div class="description"><?php echo esc_html((string) ($collection['collection_date'] ?? '')); ?> · <?php echo esc_html((string) ($collection['customer_name'] ?? '')); ?> / <?php echo esc_html((string) ($collection['contract_number'] ?? '')); ?></div><?php endif; ?>
                <a class="button button-small" href="<?php echo esc_url($attachment['url']); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html__('Preview attachment', 'safecontracts'); ?></a>
            </div>
        </div>
        <?php
    }
}
