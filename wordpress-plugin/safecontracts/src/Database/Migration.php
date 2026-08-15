<?php

declare(strict_types=1);

namespace SafeContracts\Database;

interface Migration
{
    public function up(object $wpdb): void;
}
