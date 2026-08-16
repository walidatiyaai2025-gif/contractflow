<?php

declare(strict_types=1);

namespace SafeContracts\Support;

final class Brand
{
    public const NAME = 'Safe Contracts';

    public static function iconDataUri(): string
    {
        $path = dirname(__DIR__, 2) . '/assets/brand/safe-contracts-identity.jpg';
        $bytes = @file_get_contents($path);

        if (! is_string($bytes) || $bytes === '') {
            return '';
        }

        return 'data:image/jpeg;base64,' . base64_encode($bytes);
    }
}
