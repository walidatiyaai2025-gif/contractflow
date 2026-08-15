<?php

declare(strict_types=1);

namespace SafeContracts;

use SafeContracts\Database\Migrator;
use SafeContracts\Rest\Router;

final class Plugin
{
    private static ?self $instance = null;
    private bool $booted = false;

    private function __construct()
    {
    }

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        $this->booted = true;

        // Keep schema upgrades safe for normal plugin updates without re-applying
        // role defaults that administrators may intentionally customize later.
        (new Migrator())->maybeMigrate();

        add_action('rest_api_init', [Router::class, 'register']);
        do_action('safecontracts_loaded');
    }
}
