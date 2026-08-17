<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use InvalidArgumentException;
use SafeContracts\Expiry\ContractTermExpiryPolicy;

$assertions = 0;
function esc_p8_expiry_assert(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (! $condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

function esc_p8_expiry_expect_invalid(callable $callback, string $message): void
{
    try {
        $callback();
    } catch (InvalidArgumentException) {
        esc_p8_expiry_assert(true, $message);
        return;
    }
    esc_p8_expiry_assert(false, $message);
}

$root = dirname(__DIR__, 2);
$migratorSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Database/Migrator.php');
$policySource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Expiry/ContractTermExpiryPolicy.php');
$repositorySource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Expiry/ContractTermExpiryRepository.php');
$serviceSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Expiry/ContractTermExpiryService.php');
$routerSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Rest/Router.php');
$gateSource = (string) file_get_contents($root . '/scripts/test-php.sh');

// P8-006 is deliberately schema-free and must not claim a persisted expiry model.
esc_p8_expiry_assert(! str_contains($migratorSource, 'ContractTermExpiry'), 'P8-006 adds no expiry migration registration');
esc_p8_expiry_assert(! str_contains($migratorSource, 'Migration0048EnterpriseContractTermExpiry'), 'P8-006 does not consume the next migration number for derived state');

// Strict date-only evaluation boundaries.
esc_p8_expiry_assert(ContractTermExpiryPolicy::normalizeDate('2028-02-29', 'Leap date') === '2028-02-29', 'valid leap date is accepted');
esc_p8_expiry_expect_invalid(static fn (): string => ContractTermExpiryPolicy::normalizeDate('2027-02-29'), 'invalid leap date is rejected');
esc_p8_expiry_expect_invalid(static fn (): string => ContractTermExpiryPolicy::normalizeDate('2028-13-01'), 'invalid month is rejected');
esc_p8_expiry_expect_invalid(static fn (): string => ContractTermExpiryPolicy::normalizeDate('2028-01-1'), 'non-canonical date format is rejected');

$undated = ContractTermExpiryPolicy::evaluate(null, '2028-01-01');
esc_p8_expiry_assert($undated === ['expiry_state' => 'undated', 'days_until_end' => null, 'days_past_end' => null], 'missing end date evaluates to undated without invented expiry');
$before = ContractTermExpiryPolicy::evaluate('2028-01-03', '2028-01-01');
esc_p8_expiry_assert($before === ['expiry_state' => 'not_expired', 'days_until_end' => 2, 'days_past_end' => null], 'future end date returns deterministic days until end');
$endsToday = ContractTermExpiryPolicy::evaluate('2028-01-03', '2028-01-03');
esc_p8_expiry_assert($endsToday === ['expiry_state' => 'ends_today', 'days_until_end' => 0, 'days_past_end' => null], 'exact end-date boundary evaluates to ends_today');
$expired = ContractTermExpiryPolicy::evaluate('2028-01-03', '2028-01-06');
esc_p8_expiry_assert($expired === ['expiry_state' => 'expired', 'days_until_end' => null, 'days_past_end' => 3], 'past end date returns deterministic days past end');
$leapSpan = ContractTermExpiryPolicy::evaluate('2028-03-01', '2028-02-28');
esc_p8_expiry_assert($leapSpan['days_until_end'] === 2, 'calendar-day distance accounts for leap day');
esc_p8_expiry_expect_invalid(static fn (): array => ContractTermExpiryPolicy::evaluate('2028-02-30', '2028-02-01'), 'invalid authoritative end date fails closed');
esc_p8_expiry_expect_invalid(static fn (): array => ContractTermExpiryPolicy::evaluate('2028-02-28', 'not-a-date'), 'invalid as-of date fails closed');

foreach ([
    "STATE_UNDATED = 'undated'",
    "STATE_NOT_EXPIRED = 'not_expired'",
    "STATE_ENDS_TODAY = 'ends_today'",
    "STATE_EXPIRED = 'expired'",
] as $stateMarker) {
    esc_p8_expiry_assert(str_contains($policySource, $stateMarker), 'Expiry policy explicitly defines ' . $stateMarker);
}

// No hidden current-time/timezone assumption: UTC is used only as a neutral date-math coordinate.
foreach (['current_time(', 'gmdate(', "DateTimeImmutable('now", 'time()', 'wp_date('] as $forbiddenClock) {
    esc_p8_expiry_assert(! str_contains($policySource, $forbiddenClock) && ! str_contains($serviceSource, $forbiddenClock), 'P8-006 contains no hidden clock dependency ' . $forbiddenClock);
}
esc_p8_expiry_assert(str_contains($policySource, "new DateTimeZone('UTC')"), 'date-only arithmetic uses an explicit neutral UTC coordinate');

// Repository is read-only and current-tenant scoped.
esc_p8_expiry_assert(str_contains($repositorySource, 'TenantContextStore::context()->requireTenantId()'), 'expiry repository derives tenant from TenantContextStore');
esc_p8_expiry_assert(str_contains($repositorySource, 'WHERE id = %d AND tenant_id = %d'), 'Contract lookup is tenant-scoped');
esc_p8_expiry_assert(str_contains($repositorySource, 'start_date, end_date'), 'repository reads authoritative Contract term dates');
foreach (['UPDATE ', 'INSERT ', 'DELETE ', 'START TRANSACTION', 'COMMIT', 'ROLLBACK'] as $forbiddenWrite) {
    esc_p8_expiry_assert(! str_contains(strtoupper($repositorySource), strtoupper($forbiddenWrite)), 'expiry repository remains read-only: no ' . trim($forbiddenWrite));
}

// Service preserves tenant authorization and Contract data scope without mutation.
esc_p8_expiry_assert(str_contains($serviceSource, 'authorize(Capabilities::ACCESS)'), 'expiry evaluation requires ACCESS');
esc_p8_expiry_assert(! str_contains($serviceSource, 'Capabilities::EDIT_CONTRACTS'), 'expiry evaluation requires no mutation capability');
esc_p8_expiry_assert(str_contains($serviceSource, 'TenantAuthorization::allowsCapability'), 'tenant role remains a narrowing authorization ceiling');
esc_p8_expiry_assert(str_contains($serviceSource, 'Capabilities::VIEW_ALL') && str_contains($serviceSource, 'Capabilities::VIEW_ASSIGNED'), 'service preserves VIEW_ALL / own VIEW_ASSIGNED data scope');
esc_p8_expiry_assert(str_contains($serviceSource, '$accountantUserId === get_current_user_id()'), 'VIEW_ASSIGNED evaluation is limited to current accountant');
esc_p8_expiry_assert(str_contains($serviceSource, "'contract_status' =>") && str_contains($serviceSource, "'is_archived' =>"), 'service reports lifecycle context without rewriting it');
esc_p8_expiry_assert(str_contains($serviceSource, "'as_of_date' => $asOfDate"), 'service returns the explicit evaluation date');
foreach (['updateStatus', 'updateDates', 'do_action(', 'wp_schedule', 'Notification', 'RenewalTerms', 'NoticePeriod'] as $forbiddenExecution) {
    esc_p8_expiry_assert(! str_contains($serviceSource, $forbiddenExecution), 'expiry service has no execution side effect/coupling ' . $forbiddenExecution);
}

// Foundation exposes no API/UI and is wired into the backend regression gate.
esc_p8_expiry_assert(! str_contains($routerSource, 'ContractTermExpiry'), 'P8-006 adds no REST expiry route');
esc_p8_expiry_assert(str_contains($gateSource, 'enterprise_contract_term_expiry_p8_006.php'), 'P8-006 regression is wired into global backend gate');

echo "P8-006 Contract Term Expiry evaluation passed ({$assertions} assertions).\n";
