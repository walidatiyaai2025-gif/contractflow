<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use DomainException;
use InvalidArgumentException;
use OverflowException;
use SafeContracts\Database\Migrator;
use SafeContracts\Finance\CurrencyCode;
use SafeContracts\Finance\Money;
use Throwable;

$assertions = 0;
function esc_p9_money_assert(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (! $condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

/** @param class-string<Throwable> $expected */
function esc_p9_money_expect_throw(callable $callback, string $expected, string $message): void
{
    try {
        $callback();
    } catch (Throwable $error) {
        esc_p9_money_assert($error instanceof $expected, $message . ' (received ' . $error::class . ')');
        return;
    }
    esc_p9_money_assert(false, $message . ' (no exception)');
}

$root = dirname(__DIR__, 2);
$currencySource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Finance/CurrencyCode.php');
$moneySource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Finance/Money.php');
$migratorSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Database/Migrator.php');
$legacyMoneySource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Contracts/ContractMoney.php');
$contractServiceSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Contracts/ContractService.php');
$paymentServiceSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Payments/PaymentService.php');
$contractsMigrationSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Database/Migrations/Migration0004Contracts.php');
$financialsMigrationSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Database/Migrations/Migration0005ContractFinancials.php');
$paymentsMigrationSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Database/Migrations/Migration0007Payments.php');
$settingsSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Settings/GeneralSettings.php');
$dashboardSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Admin/DashboardPage.php');
$routerSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Rest/Router.php');
$pluginSource = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Plugin.php');
$gateSource = (string) file_get_contents($root . '/scripts/test-php.sh');

// P9-001 is deliberately schema-free.
esc_p9_money_assert(Migrator::LATEST_VERSION === '1.46.0', 'P9-001 does not advance the database schema version');
esc_p9_money_assert(! str_contains($migratorSource, 'Migration0048'), 'P9-001 does not consume Migration0048');
esc_p9_money_assert(! str_contains($migratorSource, 'EnterpriseMoney') && ! str_contains($migratorSource, 'EnterpriseCurrency'), 'P9-001 registers no financial migration');

// Currency identity is explicit, immutable and syntactically strict.
$usd = CurrencyCode::from('usd');
$kwd = CurrencyCode::from(' KWD ');
esc_p9_money_assert($usd->value() === 'USD', 'lowercase currency canonicalizes to uppercase');
esc_p9_money_assert((string) $kwd === 'KWD', 'currency trims surrounding whitespace and stringifies canonically');
esc_p9_money_assert($usd->equals(CurrencyCode::from('USD')), 'equal canonical currency codes compare equal');
esc_p9_money_assert(! $usd->equals($kwd), 'different currency codes remain distinct');
foreach (['', 'US', 'USDX', 'U$D', '12A', 'ÜSD', 'د.ك'] as $invalidCurrency) {
    esc_p9_money_expect_throw(static fn (): CurrencyCode => CurrencyCode::from($invalidCurrency), InvalidArgumentException::class, 'invalid currency code fails closed: ' . $invalidCurrency);
}
esc_p9_money_expect_throw(static fn (): CurrencyCode => CurrencyCode::from(840), InvalidArgumentException::class, 'numeric currency identifiers are not silently coerced');

// Canonical four-decimal fixed-scale amount behavior.
$canonicalCases = [
    ['0', '0.0000'],
    ['-0', '0.0000'],
    ['-0.0000', '0.0000'],
    ['00012', '12.0000'],
    ['00012.34', '12.3400'],
    ['0.1', '0.1000'],
    ['0.0001', '0.0001'],
    ['-5.0001', '-5.0001'],
    [7, '7.0000'],
    [-7, '-7.0000'],
    ['9999999999999999.9999', '9999999999999999.9999'],
    ['-9999999999999999.9999', '-9999999999999999.9999'],
];
foreach ($canonicalCases as [$input, $expected]) {
    $money = Money::of($input, 'usd');
    esc_p9_money_assert($money->amount() === $expected, 'amount canonicalizes exactly: ' . (string) $input);
    esc_p9_money_assert($money->currencyCode() === 'USD', 'Money always carries canonical explicit currency');
}

foreach (['', '.', '-.', '+1', '1e3', '1E3', '1,000.00', '1 000', '$1', 'NaN', 'INF', '1.00000', '-1.00000'] as $invalidAmount) {
    esc_p9_money_expect_throw(static fn (): Money => Money::of($invalidAmount, 'USD'), InvalidArgumentException::class, 'invalid/plain-decimal amount fails closed: ' . $invalidAmount);
}
esc_p9_money_expect_throw(static fn (): Money => Money::of(1.25, 'USD'), InvalidArgumentException::class, 'binary float amount is rejected');
esc_p9_money_expect_throw(static fn (): Money => Money::of(null, 'USD'), InvalidArgumentException::class, 'null amount is rejected');
esc_p9_money_expect_throw(static fn (): Money => Money::of('10000000000000000', 'USD'), OverflowException::class, '17 whole digits exceed DECIMAL(20,4) capacity');
esc_p9_money_expect_throw(static fn (): Money => Money::of('-10000000000000000.0000', 'USD'), OverflowException::class, 'negative amount obeys the same capacity bound');

$zero = Money::of('-0.0000', $usd);
esc_p9_money_assert($zero->isZero(), 'negative zero normalizes to canonical zero');
esc_p9_money_assert($zero->negate()->amount() === '0.0000', 'negating zero preserves canonical zero');
esc_p9_money_assert($zero->toArray() === ['amount' => '0.0000', 'currency' => 'USD'], 'Money serialization preserves amount and currency as an inseparable pair');

// Exact same-currency arithmetic and comparison.
$one = Money::of('1.0000', 'USD');
$two = Money::of('2', 'USD');
$minusHalf = Money::of('-0.5000', 'USD');
esc_p9_money_assert($one->add($two)->amount() === '3.0000', 'same-currency addition is exact');
esc_p9_money_assert($one->add($minusHalf)->amount() === '0.5000', 'addition with a negative operand is exact');
esc_p9_money_assert($minusHalf->add($one)->amount() === '0.5000', 'signed addition is commutative for represented values');
esc_p9_money_assert($one->subtract($two)->amount() === '-1.0000', 'subtraction may produce a signed Money result');
esc_p9_money_assert($one->subtract($minusHalf)->amount() === '1.5000', 'subtracting a negative value is exact');
esc_p9_money_assert(Money::of('0.0001', 'USD')->subtract(Money::of('0.0002', 'USD'))->amount() === '-0.0001', 'minimum-scale subtraction is exact');
esc_p9_money_assert(Money::of('1234567890123456.7890', 'USD')->add(Money::of('0.0009', 'USD'))->amount() === '1234567890123456.7899', 'large fixed-scale addition retains every decimal digit');
esc_p9_money_assert(Money::of('-1234567890123456.7899', 'USD')->add(Money::of('0.0009', 'USD'))->amount() === '-1234567890123456.7890', 'large negative addition retains exact scale');
esc_p9_money_assert($one->negate()->amount() === '-1.0000', 'negate creates an exact negative value');
esc_p9_money_assert($one->negate()->negate()->equals($one), 'double negation restores value equality');
esc_p9_money_assert($one->compare($two) < 0, 'positive compare orders smaller before larger');
esc_p9_money_assert($two->compare($one) > 0, 'positive compare orders larger after smaller');
esc_p9_money_assert($one->compare(Money::of('1', 'USD')) === 0, 'compare uses canonical amount identity');
esc_p9_money_assert(Money::of('-2', 'USD')->compare(Money::of('-1', 'USD')) < 0, 'negative compare orders larger magnitude as smaller value');
esc_p9_money_assert(Money::of('-1', 'USD')->compare($zero) < 0, 'negative value compares below zero');
esc_p9_money_assert($zero->compare($one) < 0, 'zero compares below a positive value');
esc_p9_money_assert($one->equals(Money::of('1.000', 'USD')), 'same amount/currency are equal after canonicalization');

// Arithmetic result capacity fails closed rather than wrapping/truncating.
$max = Money::of('9999999999999999.9999', 'USD');
esc_p9_money_assert($max->add($zero)->equals($max), 'maximum value plus zero remains valid');
esc_p9_money_expect_throw(static fn (): Money => $max->add(Money::of('0.0001', 'USD')), OverflowException::class, 'positive arithmetic overflow fails closed');
esc_p9_money_expect_throw(static fn (): Money => $max->negate()->subtract(Money::of('0.0001', 'USD')), OverflowException::class, 'negative arithmetic overflow fails closed');

// Cross-currency values can coexist but may never be combined without a future explicit conversion boundary.
$usdTen = Money::of('10', 'USD');
$kwdTen = Money::of('10', 'KWD');
esc_p9_money_assert(! $usdTen->equals($kwdTen), 'equal numeric amounts in different currencies are not equal Money values');
esc_p9_money_expect_throw(static fn (): int => $usdTen->compare($kwdTen), DomainException::class, 'cross-currency comparison fails closed');
esc_p9_money_expect_throw(static fn (): Money => $usdTen->add($kwdTen), DomainException::class, 'cross-currency addition fails closed');
esc_p9_money_expect_throw(static fn (): Money => $usdTen->subtract($kwdTen), DomainException::class, 'cross-currency subtraction fails closed');

// Finance value layer remains pure and deterministic: no DB/network/clock/FX/float helpers.
foreach (['$wpdb', 'get_option(', 'update_option(', 'wp_remote_', 'curl_', 'current_time(', 'gmdate(', 'microtime(', 'DateTime', 'exchange_rate', 'currency_convert', 'floatval(', '(float)', 'round(', 'bcadd(', 'bcsub('] as $forbidden) {
    esc_p9_money_assert(! str_contains($currencySource, $forbidden) && ! str_contains($moneySource, $forbidden), 'P9-001 pure value layer contains no forbidden dependency: ' . $forbidden);
}
esc_p9_money_assert(str_contains($moneySource, 'assertSameCurrency'), 'Money centralizes the explicit same-currency boundary');
esc_p9_money_assert(str_contains($moneySource, 'MAX_WHOLE_DIGITS = 16') && str_contains($moneySource, 'SCALE = 4'), 'Money declares fixed DECIMAL(20,4)-compatible bounds');

// Legacy financial authority and single-currency behavior remain untouched by P9-001.
esc_p9_money_assert(str_contains($legacyMoneySource, 'final class ContractMoney') && ! str_contains($legacyMoneySource, 'CurrencyCode'), 'legacy ContractMoney remains a separate currency-unaware helper');
esc_p9_money_assert(str_contains($contractServiceSource, 'ContractMoney::normalizeNonNegative') && ! str_contains($contractServiceSource, 'SafeContracts\\Finance'), 'legacy ContractService remains on ContractMoney');
esc_p9_money_assert(str_contains($paymentServiceSource, 'ContractMoney::normalizeNonNegative') && ! str_contains($paymentServiceSource, 'SafeContracts\\Finance'), 'legacy PaymentService remains on ContractMoney');
esc_p9_money_assert(str_contains($settingsSource, "'currency_code'"), 'legacy General Settings currency code remains present');
foreach ([$contractsMigrationSource, $financialsMigrationSource, $paymentsMigrationSource] as $legacyMigrationSource) {
    esc_p9_money_assert(! str_contains($legacyMigrationSource, 'currency_code') && ! str_contains($legacyMigrationSource, 'exchange_rate'), 'legacy financial migrations receive no inferred currency/FX columns');
}
esc_p9_money_assert(! str_contains($dashboardSource, 'exchange_rate') && ! str_contains($dashboardSource, 'currency_convert'), 'legacy dashboard still performs no implicit currency conversion');

// Foundation has no REST/plugin execution surface and is explicitly wired into the backend gate.
esc_p9_money_assert(! str_contains($routerSource, 'Finance\\Money') && ! str_contains($routerSource, 'CurrencyCode'), 'P9-001 exposes no REST Money/Currency route');
esc_p9_money_assert(! str_contains($pluginSource, 'Finance\\Money') && ! str_contains($pluginSource, 'CurrencyCode'), 'P9-001 registers no runtime financial execution surface');
esc_p9_money_assert(str_contains($gateSource, 'enterprise_money_p9_001.php'), 'P9-001 regression is wired into the global backend gate');

echo "P9-001 Enterprise Money foundation passed ({$assertions} assertions).\n";
