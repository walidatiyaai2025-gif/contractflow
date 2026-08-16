<?php

declare(strict_types=1);

namespace SafeContracts\Admin;

use SafeContracts\Roles\Capabilities;

final class ImportPeriodNotice
{
    public static function render(): void
    {
        if ((string) ($_GET['page'] ?? '') !== ImportsPage::SLUG || ! current_user_can(Capabilities::RUN_IMPORTS)) {
            return;
        }

        $filters = AdminPeriodFilter::normalize($_GET);
        $runId = max(0, (int) ($_GET['run_id'] ?? 0));
        echo '<div class="safecontracts-import-period-wrap">';
        AdminPeriodFilter::render(ImportsPage::SLUG, $filters, $runId > 0 ? ['run_id' => $runId] : []);
        echo '<p class="description">' . esc_html__('The period filter applies to the creation time of import runs. Upload, mapping, validation and execution behavior are unchanged.', 'safecontracts') . '</p>';
        echo '</div>';
    }
}
