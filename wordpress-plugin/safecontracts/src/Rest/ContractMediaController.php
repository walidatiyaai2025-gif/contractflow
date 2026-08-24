<?php

declare(strict_types=1);

namespace SafeContracts\Rest;

use DomainException;
use SafeContracts\Attachments\EntityAttachmentService;
use SafeContracts\Counterparties\CounterpartyReadRepository;
use Throwable;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

final class ContractMediaController
{
    public static function register(): void
    {
        register_rest_route(Router::NAMESPACE, '/contracts/(?P<id>\d+)/media', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [self::class, 'show'],
            'permission_callback' => [Router::class, 'canAccess'],
        ]);
    }

    public static function show(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        try {
            $contractId = ApiRequest::routeId($request);
            $rows = (new CounterpartyReadRepository())->contracts(['contract_id' => $contractId]);
            if ($rows === []) {
                return ApiResponse::notFound('Contract');
            }

            $attachments = (new EntityAttachmentService())->all(EntityAttachmentService::CONTRACT, $contractId);
            $items = [];
            $heroUrl = '';
            foreach ($attachments as $attachment) {
                $mediaId = max(0, (int) ($attachment['media_id'] ?? 0));
                if ($mediaId <= 0) {
                    continue;
                }
                $url = wp_get_attachment_url($mediaId);
                if (! is_string($url) || trim($url) === '') {
                    continue;
                }
                $mime = (string) get_post_mime_type($mediaId);
                $safeUrl = esc_url_raw($url, ['http', 'https']);
                if ($safeUrl === '') {
                    continue;
                }
                if ($heroUrl === '' && str_starts_with(strtolower($mime), 'image/')) {
                    $heroUrl = $safeUrl;
                }
                $items[] = [
                    'id' => (int) ($attachment['id'] ?? 0),
                    'media_id' => $mediaId,
                    'label' => sanitize_text_field((string) ($attachment['label'] ?? '')),
                    'role' => sanitize_key((string) ($attachment['attachment_role'] ?? 'supporting')),
                    'mime_type' => $mime,
                    'url' => $safeUrl,
                    'created_at' => (string) ($attachment['created_at'] ?? ''),
                ];
            }

            $heroSource = 'contract';
            if ($heroUrl === '') {
                $heroUrl = self::companyLogoUrl();
                $heroSource = 'company';
            }

            return ApiResponse::ok([
                'contract_id' => $contractId,
                'hero_url' => $heroUrl,
                'hero_source' => $heroSource,
                'attachments' => $items,
            ], [
                'scope' => ApiScope::mode(),
                'fallback' => 'company_logo',
            ]);
        } catch (DomainException $error) {
            return ApiResponse::error('safecontracts_contract_media_forbidden', $error->getMessage(), 403);
        } catch (Throwable $error) {
            unset($error);
            return ApiResponse::error('safecontracts_contract_media_failed', __('Unable to load contract media.', 'safecontracts'), 500);
        }
    }

    private static function companyLogoUrl(): string
    {
        $customLogoId = max(0, (int) get_theme_mod('custom_logo', 0));
        if ($customLogoId > 0) {
            $url = wp_get_attachment_image_url($customLogoId, 'full');
            if (is_string($url) && $url !== '') {
                $safe = esc_url_raw($url, ['http', 'https']);
                if ($safe !== '') {
                    return $safe;
                }
            }
        }

        $siteIcon = get_site_icon_url(512);
        if (is_string($siteIcon) && $siteIcon !== '') {
            $safe = esc_url_raw($siteIcon, ['http', 'https']);
            if ($safe !== '') {
                return $safe;
            }
        }

        return esc_url_raw(SAFECONTRACTS_URL . 'assets/brand/safe-contracts-identity.jpg', ['http', 'https']);
    }
}
