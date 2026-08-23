<?php

declare(strict_types=1);

namespace SafeContracts\Rest;

use DomainException;
use InvalidArgumentException;
use SafeContracts\Attachments\EntityAttachmentService;
use Throwable;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

final class ContractPresentationController
{
    public static function register(): void
    {
        register_rest_route(Router::NAMESPACE, '/contracts/(?P<id>\d+)/presentation', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [self::class, 'show'],
            'permission_callback' => [Router::class, 'canAccess'],
        ]);
    }

    public static function show(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $access = Permission::access();
        if ($access instanceof WP_Error) {
            return $access;
        }

        try {
            $contractId = ApiRequest::routeId($request);
            $rows = (new EntityAttachmentService())->all(EntityAttachmentService::CONTRACT, $contractId);
            $attachments = [];
            $coverUrl = null;

            foreach ($rows as $row) {
                $mediaId = (int) ($row['media_id'] ?? 0);
                if ($mediaId <= 0) {
                    continue;
                }
                $url = function_exists('wp_get_attachment_url') ? wp_get_attachment_url($mediaId) : false;
                if (! is_string($url) || trim($url) === '') {
                    continue;
                }
                $mime = function_exists('get_post_mime_type') ? (string) get_post_mime_type($mediaId) : '';
                $isImage = str_starts_with(strtolower($mime), 'image/');
                $attachment = [
                    'id' => (int) ($row['id'] ?? 0),
                    'media_id' => $mediaId,
                    'label' => (string) ($row['label'] ?? ''),
                    'display_order' => (int) ($row['display_order'] ?? 0),
                    'mime_type' => $mime,
                    'is_image' => $isImage,
                    'url' => esc_url_raw($url),
                ];
                $attachments[] = $attachment;
                if ($coverUrl === null && $isImage) {
                    $coverUrl = $attachment['url'];
                }
            }

            return ApiResponse::ok([
                'contract_id' => $contractId,
                'cover_image_url' => $coverUrl,
                'company_logo_url' => self::companyLogoUrl(),
                'attachments' => $attachments,
            ], [
                'cover_policy' => 'first_contract_image_then_company_logo',
                'attachment_count' => count($attachments),
            ]);
        } catch (InvalidArgumentException $error) {
            return ApiResponse::error('safecontracts_contract_presentation_invalid', $error->getMessage(), 422);
        } catch (DomainException $error) {
            return ApiResponse::error('safecontracts_contract_presentation_forbidden', $error->getMessage(), 403);
        } catch (Throwable $error) {
            unset($error);
            return ApiResponse::error(
                'safecontracts_contract_presentation_failed',
                __('Unable to load contract presentation data.', 'safecontracts'),
                500
            );
        }
    }

    private static function companyLogoUrl(): ?string
    {
        if (function_exists('get_theme_mod') && function_exists('wp_get_attachment_image_url')) {
            $customLogoId = (int) get_theme_mod('custom_logo', 0);
            if ($customLogoId > 0) {
                $url = wp_get_attachment_image_url($customLogoId, 'large');
                if (is_string($url) && trim($url) !== '') {
                    return esc_url_raw($url);
                }
            }
        }
        if (function_exists('get_site_icon_url')) {
            $url = get_site_icon_url(512);
            if (is_string($url) && trim($url) !== '') {
                return esc_url_raw($url);
            }
        }
        return null;
    }
}
