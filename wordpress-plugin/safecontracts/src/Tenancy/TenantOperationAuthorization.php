<?php

declare(strict_types=1);

namespace SafeContracts\Tenancy;

final class TenantOperationAuthorization
{
    public const MANAGE_CUSTOMERS = 'manage_customers';
    public const DELETE_CUSTOMERS = 'delete_customers';
    public const DELETE_CONTRACTS = 'delete_contracts';
    public const FIREBASE_TEST_PUSH = 'firebase_test_push';

    public static function currentUserCan(string $globalCapability, string $operation): bool
    {
        if (! current_user_can($globalCapability)) {
            return false;
        }

        return TenantAuthorization::allowsOperation($operation);
    }
}
