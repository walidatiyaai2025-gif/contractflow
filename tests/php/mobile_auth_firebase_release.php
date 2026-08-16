<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);

$required = [
    'wordpress-plugin/safecontracts/src/Auth/MobileSessionStore.php' => [
        "TOKEN_PREFIX = 'scm_'",
        "hash('sha256', \$token)",
        'random_bytes(32)',
    ],
    'wordpress-plugin/safecontracts/src/Auth/MobileBearerAuthentication.php' => [
        "add_filter('determine_current_user'",
        'HTTP_AUTHORIZATION',
    ],
    'wordpress-plugin/safecontracts/src/Rest/AuthController.php' => [
        "'/auth/login'",
        "'/auth/logout'",
        'wp_authenticate(',
        'Invalid username or password.',
        'no-store',
    ],
    'mobile/lib/features/auth/login_screen.dart' => [
        "l10n.t('Username')",
        "l10n.t('Password')",
        "'Sign in'",
        'context.scL10n',
    ],
    'mobile/lib/core/auth/mobile_token_store.dart' => [
        'FlutterSecureStorage',
        'enterprise_safecontracts.mobile.bearer_token',
        'Enterprise Safe Contracts mobile token is invalid.',
    ],
    'mobile/lib/features/notifications/push_registration.dart' => [
        'requestPermission(',
        'getToken()',
        'onTokenRefresh',
        "'devices/register'",
    ],
    'scripts/bootstrap_android.sh' => [
        'ESC_FIREBASE_ANDROID_CONFIG_DEV',
        'ESC_FIREBASE_ANDROID_CONFIG_STAGING',
        'ESC_FIREBASE_ANDROID_CONFIG_PRODUCTION',
        'com.safecontracts.enterprise.dev',
        'com.safecontracts.enterprise.staging',
        'com.safecontracts.enterprise',
        'ESC Firebase app reuses the Safe Contract mobile app id',
    ],
    '.gitignore' => [
        'mobile/android-release/google-services.json',
        'mobile/android/app/src/*/google-services.json',
    ],
];

$checks = 0;
foreach ($required as $relative => $markers) {
    $path = $root . '/' . $relative;
    if (! is_file($path)) {
        fwrite(STDERR, "FAIL: missing release auth/ESC Firebase policy file {$relative}\n");
        exit(1);
    }
    $content = (string) file_get_contents($path);
    foreach ($markers as $marker) {
        if (! str_contains($content, $marker)) {
            fwrite(STDERR, "FAIL: {$relative} missing marker {$marker}\n");
            exit(1);
        }
        $checks++;
    }
}

$legacyFirebase = $root . '/mobile/android-release/google-services.json';
if (is_file($legacyFirebase)) {
    fwrite(STDERR, "FAIL: ESC branch must not commit the inherited Safe Contract google-services.json\n");
    exit(1);
}
$checks++;

$sessionStore = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Auth/MobileSessionStore.php');
$optionWrite = strpos($sessionStore, 'update_option(self::OPTION, $sessions, false)');
$tokenReturn = strpos($sessionStore, "'token' => \$token");
if ($optionWrite === false || $tokenReturn === false) {
    fwrite(STDERR, "FAIL: mobile session persistence/return contract is incomplete\n");
    exit(1);
}
if (str_contains($sessionStore, "'token' => \$token,\n            'user_id'")) {
    fwrite(STDERR, "FAIL: raw bearer token must not be persisted with server session metadata\n");
    exit(1);
}

$authController = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Rest/AuthController.php');
if (str_contains($authController, 'Application Password')) {
    fwrite(STDERR, "FAIL: mobile login must use normal WordPress credentials, not Application Passwords\n");
    exit(1);
}

printf("Enterprise Safe Contracts mobile login + isolated Firebase release policy validation passed (%d checks).\n", $checks);
