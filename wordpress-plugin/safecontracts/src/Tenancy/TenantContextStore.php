<?php

declare(strict_types=1);

namespace SafeContracts\Tenancy;

final class TenantContextStore
{
    private static ?TenantContext $context = null;

    public static function register(): void
    {
        add_filter('rest_request_before_callbacks', [self::class, 'resetBeforeCallbacks'], 1, 3);
    }

    public static function context(): TenantContext
    {
        return self::$context ??= new TenantContext();
    }

    public static function reset(): void
    {
        self::$context = new TenantContext();
    }

    public static function resetBeforeCallbacks(mixed $response, mixed $handler, mixed $request): mixed
    {
        unset($handler, $request);
        self::reset();
        return $response;
    }
}
