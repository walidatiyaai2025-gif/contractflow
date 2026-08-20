# Alkenzy ADV mobile non-JSON response hotfix

The production mobile client must treat an HTML/non-JSON response from the WordPress REST endpoint as a controlled API availability error, not expose Dart `FormatException` text such as `Unexpected character`.

For production availability, maintenance/security layers must allow `/wp-json/safecontracts/v1/*` or maintenance mode must be disabled for mobile sign-in.
