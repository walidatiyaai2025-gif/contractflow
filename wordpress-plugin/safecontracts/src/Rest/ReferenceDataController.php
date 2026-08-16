<?php

declare(strict_types=1);

namespace SafeContracts\Rest;

use SafeContracts\ReferenceData\PaymentMethodRepository;
use SafeContracts\Roles\Capabilities;
use SafeContracts\Roles\RoleRegistrar;
use Throwable;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

final class ReferenceDataController
{
    public static function register(): void
    {
        register_rest_route(Router::NAMESPACE, '/reference-data', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [self::class, 'show'],
            'permission_callback' => [Router::class, 'canAccess'],
        ]);
    }

    public static function show(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        unset($request);
        try {
            $methods = array_map(
                static fn (array $method): array => [
                    'id' => (int) $method['id'],
                    'code' => (string) $method['code'],
                    'name' => (string) $method['name'],
                    'display_order' => (int) $method['display_order'],
                ],
                (new PaymentMethodRepository())->all(true)
            );

            $accountants = [];
            if (current_user_can(Capabilities::ASSIGN_CONTRACTS)) {
                $users = get_users([
                    'role' => RoleRegistrar::ACCOUNTANT,
                    'orderby' => 'display_name',
                    'order' => 'ASC',
                ]);
                if (is_array($users)) {
                    foreach ($users as $user) {
                        if (! is_object($user) || ! isset($user->ID)) {
                            continue;
                        }
                        $id = (int) $user->ID;
                        if ($id <= 0 ||
                            ! user_can($id, Capabilities::ACCESS) ||
                            ! user_can($id, Capabilities::CREATE_CONTRACTS) ||
                            ! user_can($id, Capabilities::VIEW_ASSIGNED)) {
                            continue;
                        }
                        $name = trim((string) ($user->display_name ?? ''));
                        if ($name === '') {
                            $name = trim((string) ($user->user_login ?? ''));
                        }
                        $accountants[] = [
                            'id' => $id,
                            'name' => $name !== '' ? $name : ('#' . $id),
                            'email' => trim((string) ($user->user_email ?? '')),
                        ];
                    }
                }
            }

            return RequestGuard::response([
                'payment_methods' => $methods,
                'accountants' => $accountants,
            ]);
        } catch (Throwable $error) {
            return RequestGuard::failure($error, 'safecontracts_reference_data_failed');
        }
    }
}
