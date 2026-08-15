<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

if (! function_exists('wp_salt')) {
    function wp_salt(string $scheme = 'auth'): string
    {
        return 'safecontracts-test-' . $scheme . '-salt-0123456789abcdefghijklmnopqrstuvwxyz';
    }
}
if (! function_exists('delete_option')) {
    function delete_option(string $key): bool
    {
        unset($GLOBALS['sc_test_options'][$key]);
        return true;
    }
}
if (! function_exists('wp_json_encode')) {
    function wp_json_encode(mixed $value, int $flags = 0, int $depth = 512): string|false
    {
        return json_encode($value, $flags, $depth);
    }
}
if (! function_exists('is_wp_error')) {
    function is_wp_error(mixed $value): bool
    {
        return $value instanceof WP_Error;
    }
}
$GLOBALS['sc_firebase_http_requests'] = [];
$GLOBALS['sc_firebase_http_queue'] = [];
if (! function_exists('wp_remote_post')) {
    function wp_remote_post(string $url, array $args = []): array|WP_Error
    {
        $GLOBALS['sc_firebase_http_requests'][] = ['url' => $url, 'args' => $args];
        $response = array_shift($GLOBALS['sc_firebase_http_queue']);
        return is_array($response) || $response instanceof WP_Error
            ? $response
            : new WP_Error('http_unavailable', 'No mock response configured.');
    }
}
if (! function_exists('wp_remote_retrieve_response_code')) {
    function wp_remote_retrieve_response_code(array|WP_Error $response): int
    {
        return $response instanceof WP_Error ? 0 : (int) ($response['response']['code'] ?? 0);
    }
}
if (! function_exists('wp_remote_retrieve_body')) {
    function wp_remote_retrieve_body(array|WP_Error $response): string
    {
        return $response instanceof WP_Error ? '' : (string) ($response['body'] ?? '');
    }
}

require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Notifications\FirebaseAccessTokenProvider;
use SafeContracts\Notifications\FirebaseServiceAccountVault;
use SafeContracts\Notifications\FirebaseSettings;
use SafeContracts\Roles\Capabilities;

$tests = 0;
function sc_firebase_vault_assert(bool $ok, string $message): void
{
    global $tests;
    $tests++;
    if (! $ok) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}
function sc_firebase_vault_expect(string $class, callable $callback, string $message): void
{
    try {
        $callback();
    } catch (Throwable $error) {
        sc_firebase_vault_assert($error instanceof $class, $message);
        return;
    }
    sc_firebase_vault_assert(false, $message);
}
function sc_firebase_b64url_decode(string $value): string
{
    $value = strtr($value, '-_', '+/');
    $padding = strlen($value) % 4;
    if ($padding !== 0) {
        $value .= str_repeat('=', 4 - $padding);
    }
    return (string) base64_decode($value, true);
}

$GLOBALS['sc_test_current_caps'] = [Capabilities::MANAGE_SYSTEM => true];
$private = openssl_pkey_new([
    'private_key_bits' => 2048,
    'private_key_type' => OPENSSL_KEYTYPE_RSA,
]);
sc_firebase_vault_assert($private !== false, 'OpenSSL test RSA key can be created');
$pem = '';
sc_firebase_vault_assert(openssl_pkey_export($private, $pem) && $pem !== '', 'OpenSSL test RSA key can be exported');

$projectId = FirebaseSettings::DEFAULT_PROJECT_ID;
$serviceAccount = [
    'type' => 'service_account',
    'project_id' => $projectId,
    'private_key_id' => '0123456789abcdef0123456789abcdef01234567',
    'private_key' => $pem,
    'client_email' => 'safecontracts-fcm@safecontract-13846.iam.gserviceaccount.com',
    'client_id' => '123456789012345678901',
    'auth_uri' => 'https://accounts.google.com/o/oauth2/auth',
    'token_uri' => 'https://oauth2.googleapis.com/token',
];
$json = json_encode($serviceAccount, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
$vault = new FirebaseServiceAccountVault();
$metadata = $vault->storeJson($json, $projectId);
sc_firebase_vault_assert($metadata['project_id'] === $projectId, 'Vault metadata retains project ID');
sc_firebase_vault_assert($metadata['client_email'] === $serviceAccount['client_email'], 'Vault metadata retains safe service-account email');
sc_firebase_vault_assert(strlen($metadata['key_fingerprint']) === 16, 'Vault exposes only bounded key fingerprint');

$stored = $GLOBALS['sc_test_options'][FirebaseServiceAccountVault::OPTION] ?? null;
$storedText = json_encode($stored, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
sc_firebase_vault_assert(is_array($stored), 'Vault persists structured encrypted record');
sc_firebase_vault_assert(! str_contains($storedText, $pem), 'Vault option never stores plaintext private key');
sc_firebase_vault_assert(! str_contains($storedText, 'BEGIN PRIVATE KEY'), 'Vault option exposes no PEM marker');
sc_firebase_vault_assert(! str_contains($storedText, '"private_key"'), 'Vault option exposes no private-key JSON field');
sc_firebase_vault_assert(str_contains($storedText, 'ciphertext'), 'Vault option stores ciphertext');

$roundTrip = $vault->credential($projectId);
sc_firebase_vault_assert(hash_equals($pem, $roundTrip['private_key']), 'Vault decrypts credential only inside trusted runtime');
sc_firebase_vault_assert($vault->configured($projectId), 'Vault reports configured only when decryption and validation succeed');
sc_firebase_vault_expect(
    InvalidArgumentException::class,
    static fn () => $vault->storeJson($json, 'different-project'),
    'Vault rejects service-account project mismatch'
);

$settings = new FirebaseSettings();
sc_firebase_vault_assert(
    $settings->saveCredentialReference(FirebaseServiceAccountVault::REFERENCE) === FirebaseServiceAccountVault::REFERENCE,
    'SafeContracts binds uploaded vault to fixed credential reference'
);

$GLOBALS['sc_firebase_http_queue'][] = [
    'response' => ['code' => 200],
    'body' => json_encode([
        'access_token' => 'ya29.safecontracts-test-access-token',
        'expires_in' => 3600,
        'token_type' => 'Bearer',
    ], JSON_THROW_ON_ERROR),
];
$provider = new FirebaseAccessTokenProvider();
$token = $provider->accessToken($projectId);
sc_firebase_vault_assert($token === 'ya29.safecontracts-test-access-token', 'Provider mints short-lived OAuth access token');
sc_firebase_vault_assert(count($GLOBALS['sc_firebase_http_requests']) === 1, 'OAuth token mint performs one server request');
$oauthRequest = $GLOBALS['sc_firebase_http_requests'][0];
sc_firebase_vault_assert($oauthRequest['url'] === 'https://oauth2.googleapis.com/token', 'OAuth request is pinned to validated Google token endpoint');
sc_firebase_vault_assert(($oauthRequest['args']['body']['grant_type'] ?? '') === 'urn:ietf:params:oauth:grant-type:jwt-bearer', 'OAuth uses JWT bearer grant');
$assertion = (string) ($oauthRequest['args']['body']['assertion'] ?? '');
$segments = explode('.', $assertion);
sc_firebase_vault_assert(count($segments) === 3, 'OAuth assertion is a signed JWT');
$claims = json_decode(sc_firebase_b64url_decode($segments[1]), true, 16, JSON_THROW_ON_ERROR);
sc_firebase_vault_assert(($claims['iss'] ?? '') === $serviceAccount['client_email'], 'JWT issuer is service account');
sc_firebase_vault_assert(($claims['scope'] ?? '') === FirebaseAccessTokenProvider::SCOPE, 'JWT scope is Firebase Messaging only');
sc_firebase_vault_assert(($claims['aud'] ?? '') === $serviceAccount['token_uri'], 'JWT audience is validated OAuth token endpoint');
sc_firebase_vault_assert((int) ($claims['exp'] ?? 0) - (int) ($claims['iat'] ?? 0) === 3600, 'JWT lifetime is bounded to one hour');

$GLOBALS['sc_firebase_http_queue'][] = [
    'response' => ['code' => 200],
    'body' => json_encode(['name' => 'projects/safecontract-13846/messages/validation-only'], JSON_THROW_ON_ERROR),
];
$connection = $provider->testConnection($projectId);
sc_firebase_vault_assert($connection['success'] === true && $connection['status_code'] === 200, 'FCM HTTP v1 validate-only connection test succeeds');
$fcmRequest = $GLOBALS['sc_firebase_http_requests'][1];
sc_firebase_vault_assert(str_contains($fcmRequest['url'], '/v1/projects/' . $projectId . '/messages:send'), 'Connection test targets project-scoped FCM HTTP v1 endpoint');
sc_firebase_vault_assert(($fcmRequest['args']['headers']['Authorization'] ?? '') === 'Bearer ' . $token, 'FCM request uses minted bearer token');
$fcmBody = json_decode((string) ($fcmRequest['args']['body'] ?? ''), true, 16, JSON_THROW_ON_ERROR);
sc_firebase_vault_assert(($fcmBody['validate_only'] ?? false) === true, 'Connection test never delivers a notification');

FirebaseAccessTokenProvider::register();
$filteredToken = apply_filters(
    'safecontracts_firebase_access_token',
    '',
    FirebaseServiceAccountVault::REFERENCE,
    $projectId
);
sc_firebase_vault_assert($filteredToken === $token, 'Existing Firebase transport receives vault OAuth token through provider filter');
sc_firebase_vault_assert(count($GLOBALS['sc_firebase_http_requests']) === 2, 'Provider reuses only in-process short-lived token cache');

$tampered = $GLOBALS['sc_test_options'][FirebaseServiceAccountVault::OPTION];
$tamperedCipher = base64_decode((string) $tampered['ciphertext'], true);
sc_firebase_vault_assert(is_string($tamperedCipher) && $tamperedCipher !== '', 'Encrypted payload can be inspected for tamper test');
$tamperedCipher[0] = chr(ord($tamperedCipher[0]) ^ 1);
$tampered['ciphertext'] = base64_encode($tamperedCipher);
$GLOBALS['sc_test_options'][FirebaseServiceAccountVault::OPTION] = $tampered;
sc_firebase_vault_assert(! $vault->configured($projectId), 'Authenticated encryption fails closed after ciphertext tampering');

$GLOBALS['sc_test_options'][FirebaseServiceAccountVault::OPTION] = $stored;
$vault->delete();
$settings->clearCredentialReference();
sc_firebase_vault_assert(! array_key_exists(FirebaseServiceAccountVault::OPTION, $GLOBALS['sc_test_options']), 'Credential deletion removes encrypted vault record');
sc_firebase_vault_assert(! array_key_exists(FirebaseSettings::CREDENTIAL_REFERENCE_OPTION, $GLOBALS['sc_test_options']), 'Credential deletion clears SafeContracts reference');

printf("SafeContracts Firebase service-account vault tests passed (%d assertions).\n", $tests);
