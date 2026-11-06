<?php

declare(strict_types=1);

namespace SafeContracts\Rest;

use InvalidArgumentException;
use Throwable;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

final class ProfileAvatarController
{
    private const MAX_BYTES = 2097152;

    public static function register(): void
    {
        register_rest_route(Router::NAMESPACE, '/profile/avatar', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'upload'],
            'permission_callback' => [Router::class, 'canAccess'],
        ]);
    }

    public static function upload(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        try {
            $body = $request->get_json_params();
            if (! is_array($body)) {
                throw new InvalidArgumentException('Profile avatar upload requires a JSON object.');
            }
            foreach (array_keys($body) as $field) {
                if (! is_string($field) || ! in_array($field, ['mime_type', 'base64'], true)) {
                    throw new InvalidArgumentException('Unsupported profile avatar field.');
                }
            }

            $encoded = isset($body['base64']) ? trim((string) $body['base64']) : '';
            if ($encoded === '' || strlen($encoded) > 3000000) {
                throw new InvalidArgumentException('Profile avatar payload is empty or too large.');
            }
            $bytes = base64_decode($encoded, true);
            if (! is_string($bytes) || $bytes === '' || strlen($bytes) > self::MAX_BYTES) {
                throw new InvalidArgumentException('Profile avatar payload is invalid or too large.');
            }

            $image = function_exists('getimagesizefromstring') ? @getimagesizefromstring($bytes) : false;
            $detectedMime = is_array($image) && isset($image['mime']) ? strtolower((string) $image['mime']) : '';
            $declaredMime = strtolower(trim((string) ($body['mime_type'] ?? '')));
            $allowed = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
            ];
            if (! isset($allowed[$detectedMime]) || ($declaredMime !== '' && $declaredMime !== $detectedMime)) {
                throw new InvalidArgumentException('Profile avatar must be a JPEG, PNG, or WebP image.');
            }

            // Router::canAccess is the route permission callback, so this callback
            // only runs for an authenticated SafeContracts user.
            $userId = get_current_user_id();

            $filename = sprintf(
                'alkenzy-avatar-%d-%d.%s',
                $userId,
                time(),
                $allowed[$detectedMime]
            );
            $upload = wp_upload_bits($filename, null, $bytes);
            if (! is_array($upload) || ! empty($upload['error']) || empty($upload['url'])) {
                throw new InvalidArgumentException('The profile avatar could not be stored.');
            }

            $url = function_exists('esc_url_raw')
                ? esc_url_raw((string) $upload['url'])
                : (string) $upload['url'];
            update_user_meta($userId, 'safecontracts_mobile_avatar_url', $url);

            return ApiResponse::ok([
                'avatar_url' => $url,
            ], [
                'scope' => 'current_user',
            ]);
        } catch (InvalidArgumentException $error) {
            return RequestGuard::invalid($error, 'safecontracts_profile_avatar_invalid');
        } catch (Throwable $error) {
            return RequestGuard::failure($error, 'safecontracts_profile_avatar_failed');
        }
    }
}
