<?php

declare(strict_types=1);

namespace SafeContracts\Diagnostics;

use DomainException;
use InvalidArgumentException;
use RuntimeException;
use SafeContracts\Database\Migrator;
use SafeContracts\Roles\Capabilities;
use Throwable;

/**
 * Bounded, sanitized production diagnostics for SafeContracts admin/runtime failures.
 *
 * This recorder intentionally stores no stack traces, raw request payloads,
 * cookies, authorization headers, nonces, credentials, tokens or secrets.
 */
final class RuntimeInspector
{
    public const OPTION = 'safecontracts_runtime_inspector_events_v1';
    public const MAX_EVENTS = 50;
    public const EVENT_SCHEMA_VERSION = 2;

    /** @var array{id:string,operation:string,stage:string,context:array<string,mixed>}|null */
    private static $current = null;
    /** @var string|null */
    private static $capturedId = null;

    public static function register(): void
    {
        add_filter('wp_redirect', [self::class, 'captureFailedRedirect'], 999, 2);
        register_shutdown_function([self::class, 'captureFatalShutdown']);
    }

    /** @param array<string,mixed> $context */
    public static function begin(string $operation, array $context = []): string
    {
        $id = self::correlationId();
        self::$current = [
            'id' => $id,
            'operation' => self::boundedText($operation, 120),
            'stage' => 'begin',
            'context' => self::sanitizeContext($context),
        ];
        self::$capturedId = null;
        return $id;
    }

    /** @param array<string,mixed> $context */
    public static function stage(string $stage, array $context = []): void
    {
        if (self::$current === null) {
            return;
        }
        self::$current['stage'] = self::boundedText($stage, 120);
        self::$current['context'] = array_merge(
            self::$current['context'],
            self::sanitizeContext($context)
        );
    }

    /** @param array<string,mixed> $context */
    public static function capture(Throwable $error, array $context = []): string
    {
        if (self::$capturedId !== null) {
            return self::$capturedId;
        }

        if (self::$current === null) {
            self::begin('runtime.failure');
        }

        $current = self::$current ?? [
            'id' => self::correlationId(),
            'operation' => 'runtime.failure',
            'stage' => 'unknown',
            'context' => [],
        ];
        $dbError = self::databaseError();
        $event = [
            'schema_version' => self::EVENT_SCHEMA_VERSION,
            'id' => $current['id'],
            'occurred_at_utc' => gmdate('c'),
            'operation' => $current['operation'],
            'stage' => $current['stage'],
            'classification' => self::classifyFailure($error, $dbError),
            'root_cause' => self::rootCause($error),
            'exception_class' => get_class($error),
            'exception_code' => $error->getCode(),
            'exception_chain' => self::exceptionChain($error),
            'message' => self::boundedText($error->getMessage(), 1000),
            'source' => [
                'file' => basename($error->getFile()),
                'line' => $error->getLine(),
            ],
            'db_error' => $dbError,
            'user_id' => function_exists('get_current_user_id') ? (int) get_current_user_id() : 0,
            'request' => self::requestSnapshot(),
            'capabilities' => self::capabilitySnapshot(),
            'environment' => self::environmentSnapshot(),
            'context' => array_merge($current['context'], self::sanitizeContext($context)),
        ];

        $events = self::recent();
        array_unshift($events, $event);
        update_option(self::OPTION, array_slice($events, 0, self::MAX_EVENTS), false);
        self::$capturedId = $current['id'];
        return self::$capturedId;
    }

    /**
     * Finish the active trace but deliberately preserve a captured correlation ID
     * until the request redirect is filtered. This keeps the user-visible link
     * bound to the exact exception instead of replacing it with a generic
     * safecontracts_status fallback event.
     */
    public static function finish(): void
    {
        self::$current = null;
    }

    /** @return list<array<string,mixed>> */
    public static function recent(): array
    {
        $events = get_option(self::OPTION, []);
        if (! is_array($events)) {
            return [];
        }
        return array_values(array_filter($events, 'is_array'));
    }

    public static function clear(): void
    {
        update_option(self::OPTION, [], false);
        self::$current = null;
        self::$capturedId = null;
    }

    /**
     * Fallback coverage for existing admin handlers that collapse exceptions to
     * a safecontracts_status query parameter. Detailed instrumentation can be
     * added incrementally without leaving failures completely invisible.
     */
    public static function captureFailedRedirect(string $location, int $status = 302): string
    {
        unset($status);
        if (self::$capturedId !== null) {
            return self::appendCorrelationId($location, self::$capturedId);
        }
        if (! str_contains($location, 'safecontracts_status=')) {
            return $location;
        }

        $query = (string) (parse_url($location, PHP_URL_QUERY) ?? '');
        parse_str($query, $args);
        $safecontractsStatus = sanitize_key((string) ($args['safecontracts_status'] ?? ''));
        if ($safecontractsStatus === '' || ! preg_match('/(?:invalid|failed|error|blocked)/', $safecontractsStatus)) {
            return $location;
        }

        if (self::$current === null) {
            self::begin('admin.mutation', [
                'action' => self::requestAction(),
                'reported_status' => $safecontractsStatus,
            ]);
        }
        self::stage('admin.redirect.status', ['reported_status' => $safecontractsStatus]);
        $runtimeId = self::capture(new RuntimeException('Admin mutation reported failure status: ' . $safecontractsStatus));
        return self::appendCorrelationId($location, $runtimeId);
    }

    public static function captureFatalShutdown(): void
    {
        $last = error_get_last();
        if (! is_array($last)) {
            return;
        }
        $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
        if (! in_array((int) ($last['type'] ?? 0), $fatalTypes, true)) {
            return;
        }
        if (self::$current === null && ! self::isSafeContractsRequest()) {
            return;
        }
        if (self::$current === null) {
            self::begin('php.fatal');
        }
        self::stage('shutdown.fatal');
        self::capture(new RuntimeException(self::boundedText((string) ($last['message'] ?? 'Fatal PHP error'), 1000)), [
            'file' => basename((string) ($last['file'] ?? '')),
            'line' => (int) ($last['line'] ?? 0),
        ]);
    }

    /** @param array<string,mixed> $context @return array<string,mixed> */
    public static function sanitizeContext(array $context): array
    {
        $clean = [];
        $count = 0;
        foreach ($context as $key => $value) {
            if ($count >= 40) {
                break;
            }
            $name = self::boundedText((string) $key, 80);
            if ($name === '') {
                continue;
            }
            $count++;
            if (self::isSensitiveKey($name)) {
                $clean[$name] = '[redacted]';
                continue;
            }
            if (is_array($value)) {
                $clean[$name] = self::sanitizeNestedArray($value, 1);
            } elseif (is_bool($value) || is_int($value) || is_float($value) || $value === null) {
                $clean[$name] = $value;
            } else {
                $clean[$name] = self::boundedText((string) $value, 500);
            }
        }
        return $clean;
    }

    /** @return array<string,mixed> */
    public static function environmentSnapshot(): array
    {
        global $wp_version;
        return [
            'plugin_version' => defined('SAFECONTRACTS_VERSION') ? (string) SAFECONTRACTS_VERSION : 'unknown',
            'db_version' => (string) get_option(Migrator::VERSION_OPTION, 'unknown'),
            'db_latest' => Migrator::LATEST_VERSION,
            'php_version' => PHP_VERSION,
            'wordpress_version' => isset($wp_version) ? (string) $wp_version : 'unknown',
            'wordpress_timezone' => function_exists('wp_timezone_string') ? (string) wp_timezone_string() : '',
        ];
    }

    /** @return array<string,bool> */
    private static function capabilitySnapshot(): array
    {
        $result = [];
        foreach (Capabilities::all() as $capability) {
            $result[$capability] = function_exists('current_user_can') && current_user_can($capability);
        }
        return $result;
    }

    /** @return array<string,mixed> */
    private static function requestSnapshot(): array
    {
        return [
            'method' => self::boundedText((string) ($_SERVER['REQUEST_METHOD'] ?? ''), 16),
            'page' => sanitize_key((string) ($_REQUEST['page'] ?? '')),
            'action' => self::requestAction(),
            'input' => self::requestInputSnapshot(),
        ];
    }

    /** @return array<string,mixed> */
    private static function requestInputSnapshot(): array
    {
        $allowed = [
            'payment_id',
            'collection_id',
            'contract_id',
            'customer_id',
            'supplier_id',
            'counterparty_id',
            'payment_method_id',
            'entity_id',
            'attachment_id',
            'media_id',
            'accountant_user_id',
            'amount',
            'original_amount',
            'due_date',
            'expected_payment_date',
            'collection_date',
            'financial_direction',
            'status',
        ];
        $snapshot = [];
        foreach ($allowed as $key) {
            if (! array_key_exists($key, $_REQUEST) || ! is_scalar($_REQUEST[$key])) {
                continue;
            }
            $value = self::boundedText((string) $_REQUEST[$key], 120);
            if ($value !== '') {
                $snapshot[$key] = $value;
            }
        }
        return $snapshot;
    }

    private static function requestAction(): string
    {
        return sanitize_key((string) ($_REQUEST['action'] ?? ''));
    }

    private static function isSafeContractsRequest(): bool
    {
        $page = sanitize_key((string) ($_REQUEST['page'] ?? ''));
        $action = self::requestAction();
        return str_starts_with($page, 'safecontracts') || str_starts_with($action, 'safecontracts');
    }

    private static function appendCorrelationId(string $location, string $runtimeId): string
    {
        if ($runtimeId === '' || str_contains($location, 'safecontracts_runtime_id=')) {
            return $location;
        }
        if (function_exists('add_query_arg')) {
            return add_query_arg(['safecontracts_runtime_id' => $runtimeId], $location);
        }
        $separator = str_contains($location, '?') ? '&' : '?';
        return $location . $separator . 'safecontracts_runtime_id=' . rawurlencode($runtimeId);
    }

    private static function databaseError(): string
    {
        global $wpdb;
        if (! is_object($wpdb) || ! property_exists($wpdb, 'last_error')) {
            return '';
        }
        return self::boundedText((string) $wpdb->last_error, 1000);
    }

    /** @return list<array{class:string,code:int|string,message:string}> */
    private static function exceptionChain(Throwable $error): array
    {
        $chain = [];
        $current = $error;
        for ($depth = 0; $depth < 4 && $current !== null; $depth++) {
            $chain[] = [
                'class' => get_class($current),
                'code' => $current->getCode(),
                'message' => self::boundedText($current->getMessage(), 1000),
            ];
            $current = $current->getPrevious();
        }
        return $chain;
    }

    private static function rootCause(Throwable $error): string
    {
        $current = $error;
        for ($depth = 0; $depth < 8 && $current->getPrevious() !== null; $depth++) {
            $current = $current->getPrevious();
        }
        $message = self::boundedText($current->getMessage(), 1000);
        return $message !== '' ? $message : get_class($current);
    }

    private static function classifyFailure(Throwable $error, string $dbError): string
    {
        if ($dbError !== '') {
            return 'database';
        }
        if ($error instanceof InvalidArgumentException) {
            return 'validation';
        }
        if ($error instanceof DomainException) {
            $message = strtolower($error->getMessage());
            if (str_contains($message, 'permission') || str_contains($message, 'scope') || str_contains($message, 'access')) {
                return 'authorization';
            }
            return 'business_rule';
        }
        if ($error instanceof RuntimeException) {
            return 'runtime';
        }
        return 'unexpected';
    }

    /** @param array<mixed> $values @return array<mixed> */
    private static function sanitizeNestedArray(array $values, int $depth): array
    {
        if ($depth > 3) {
            return ['[depth-limited]'];
        }
        $clean = [];
        $count = 0;
        foreach ($values as $key => $value) {
            if ($count >= 20) {
                break;
            }
            $count++;
            $targetKey = is_int($key) ? $key : self::boundedText((string) $key, 80);
            if (! is_int($targetKey) && self::isSensitiveKey($targetKey)) {
                $clean[$targetKey] = '[redacted]';
            } elseif (is_array($value)) {
                $clean[$targetKey] = self::sanitizeNestedArray($value, $depth + 1);
            } elseif (is_bool($value) || is_int($value) || is_float($value) || $value === null) {
                $clean[$targetKey] = $value;
            } else {
                $clean[$targetKey] = self::boundedText((string) $value, 500);
            }
        }
        return $clean;
    }

    private static function isSensitiveKey(string $key): bool
    {
        return (bool) preg_match('/(?:pass(?:word)?|secret|token|nonce|cookie|authorization|auth[_-]?header|api[_-]?key|credential|private[_-]?key)/i', $key);
    }

    private static function boundedText(string $value, int $max): string
    {
        $value = trim(strip_tags($value));
        if (strlen($value) <= $max) {
            return $value;
        }
        return substr($value, 0, $max);
    }

    private static function correlationId(): string
    {
        try {
            $suffix = bin2hex(random_bytes(4));
        } catch (Throwable) {
            $suffix = substr(sha1(uniqid('', true)), 0, 8);
        }
        return 'SC-' . gmdate('Ymd-His') . '-' . $suffix;
    }
}
