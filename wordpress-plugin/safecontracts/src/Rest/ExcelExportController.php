<?php

declare(strict_types=1);

namespace SafeContracts\Rest;

use DomainException;
use InvalidArgumentException;
use SafeContracts\Reports\ReportExportService;
use SafeContracts\Roles\Capabilities;
use Throwable;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

final class ExcelExportController
{
    public static function register(): void
    {
        register_rest_route(Router::NAMESPACE, '/reports/excel', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [self::class, 'download'],
            'permission_callback' => [self::class, 'canExport'],
        ]);
    }

    public static function download(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $permission = self::canExport();
        if ($permission instanceof WP_Error) {
            return $permission;
        }

        try {
            $export = (new ReportExportService())->generate(RequestGuard::params($request));
            return RequestGuard::response([
                'filename' => $export['filename'],
                'content_type' => $export['content_type'],
                'encoding' => 'base64',
                'content_base64' => base64_encode((string) $export['content']),
                'filters' => $export['filters'],
                'row_counts' => $export['row_counts'],
            ], ['download' => true]);
        } catch (InvalidArgumentException $error) {
            return RequestGuard::invalid($error, 'safecontracts_export_invalid');
        } catch (DomainException $error) {
            return RequestGuard::domain($error, 'safecontracts_export_forbidden');
        } catch (Throwable $error) {
            return RequestGuard::failure($error, 'safecontracts_export_failed');
        }
    }

    public static function canExport(): bool|WP_Error
    {
        $access = Router::canAccess();
        if ($access !== true) {
            return $access;
        }
        if (current_user_can(Capabilities::EXPORT_REPORTS)) {
            return true;
        }
        return RequestGuard::forbidden(
            'safecontracts_export_forbidden',
            __('You do not have permission to export SafeContracts reports.', 'safecontracts')
        );
    }
}
