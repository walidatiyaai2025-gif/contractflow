<?php

declare(strict_types=1);

namespace SafeContracts\Admin;

use RuntimeException;

final class MultipleAttachmentUploader
{
    public const FIELD = 'safecontracts_files';
    public const MAX_FILES = 10;

    /** @return list<int> */
    public static function upload(string $field = self::FIELD): array
    {
        $upload = $_FILES[$field] ?? null;
        if (! is_array($upload) || ! isset($upload['name'])) {
            return [];
        }

        $names = is_array($upload['name']) ? array_values($upload['name']) : [$upload['name']];
        $types = is_array($upload['type'] ?? null) ? array_values($upload['type']) : [$upload['type'] ?? ''];
        $tmpNames = is_array($upload['tmp_name'] ?? null) ? array_values($upload['tmp_name']) : [$upload['tmp_name'] ?? ''];
        $errors = is_array($upload['error'] ?? null) ? array_values($upload['error']) : [$upload['error'] ?? UPLOAD_ERR_NO_FILE];
        $sizes = is_array($upload['size'] ?? null) ? array_values($upload['size']) : [$upload['size'] ?? 0];

        $realFiles = 0;
        foreach ($names as $index => $name) {
            if (trim((string) $name) !== '' && (int) ($errors[$index] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $realFiles++;
            }
        }
        if ($realFiles === 0) {
            return [];
        }
        if ($realFiles > self::MAX_FILES) {
            throw new RuntimeException('You can upload up to ' . self::MAX_FILES . ' attachments at a time.');
        }

        self::loadWordPressMediaApi();
        $ids = [];
        foreach ($names as $index => $name) {
            $name = trim((string) $name);
            $error = (int) ($errors[$index] ?? UPLOAD_ERR_NO_FILE);
            if ($name === '' || $error === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            if ($error !== UPLOAD_ERR_OK) {
                throw new RuntimeException('One of the selected attachments could not be uploaded.');
            }

            $key = 'safecontracts_multi_' . $index . '_' . substr(hash('sha256', $name . '|' . microtime(true)), 0, 10);
            $_FILES[$key] = [
                'name' => $name,
                'type' => (string) ($types[$index] ?? ''),
                'tmp_name' => (string) ($tmpNames[$index] ?? ''),
                'error' => $error,
                'size' => (int) ($sizes[$index] ?? 0),
            ];

            try {
                $mediaId = media_handle_upload($key, 0, [], [
                    'test_form' => false,
                    'mimes' => self::allowedMimes(),
                ]);
            } finally {
                unset($_FILES[$key]);
            }

            if (function_exists('is_wp_error') && is_wp_error($mediaId)) {
                $message = method_exists($mediaId, 'get_error_message') ? (string) $mediaId->get_error_message() : 'Attachment upload failed.';
                throw new RuntimeException($message !== '' ? $message : 'Attachment upload failed.');
            }
            $mediaId = (int) $mediaId;
            if ($mediaId <= 0) {
                throw new RuntimeException('WordPress did not return a valid media ID for an uploaded attachment.');
            }
            $ids[] = $mediaId;
        }

        return array_values(array_unique($ids));
    }

    /** @param list<int> $mediaIds */
    public static function cleanup(array $mediaIds): void
    {
        if (! function_exists('wp_delete_attachment')) {
            self::loadWordPressMediaApi();
        }
        foreach ($mediaIds as $mediaId) {
            if ((int) $mediaId > 0 && function_exists('wp_delete_attachment')) {
                wp_delete_attachment((int) $mediaId, true);
            }
        }
    }

    /** @return array<string,string> */
    private static function allowedMimes(): array
    {
        return [
            'pdf' => 'application/pdf',
            'jpg|jpeg|jpe' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'txt' => 'text/plain',
        ];
    }

    private static function loadWordPressMediaApi(): void
    {
        if (function_exists('media_handle_upload')) {
            return;
        }
        if (! defined('ABSPATH')) {
            throw new RuntimeException('WordPress media upload API is unavailable.');
        }
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        if (! function_exists('media_handle_upload')) {
            throw new RuntimeException('WordPress media upload API could not be loaded.');
        }
    }
}
