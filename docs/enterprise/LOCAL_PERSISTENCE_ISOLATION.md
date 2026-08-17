# ESC local persistence isolation contract

Enterprise Safe Contracts Android must not inherit or silently share local persistence conventions from Safe Contract.

## Current production persistence

The current mobile client declares `flutter_secure_storage` as its only local-persistence package and has exactly two audited production owners:

1. `mobile/lib/core/auth/mobile_token_store.dart`
   - key: `enterprise_safecontracts.mobile.bearer_token`
2. `mobile/lib/core/localization/mobile_locale_controller.dart`
   - key: `enterprise_safecontracts.mobile.language`

The locale key was previously inherited as `safecontracts_mobile_language`; P0-002G corrects it to the Enterprise namespace.

Combined with the distinct Android application ID `com.safecontracts.enterprise`, these stores are both application-sandboxed and explicitly Enterprise-namespaced.

## Fail-closed policy

`scripts/verify_esc_local_persistence_isolation.py` runs in ESC Foundation and rejects unreviewed persistence expansion, including:

- SharedPreferences;
- Hive;
- SQLite / sqflite;
- Drift;
- Isar;
- Sembast;
- ObjectBox;
- Realm;
- path-provider-backed local persistence;
- direct `dart:io` file/directory persistence primitives;
- direct `flutter_secure_storage` use outside the two audited ESC stores;
- drift from either Enterprise secure-storage key;
- reintroduction of inherited Safe Contract persistence keys.

The gate is intentionally restrictive. New persistent preferences, cache files, databases, offline queues, or secure-storage records are allowed only after the implementation defines and tests an explicit Enterprise namespace/isolation contract. The correct change is to extend the reviewed allowlist and regression coverage with the new namespaced store, not to delete or weaken the gate.

## Runtime-UAT boundary

This static contract does not prove real-device session/data lifecycle behavior. The physical-device coexistence UAT under #421 must still demonstrate:

- `session_isolation`;
- `clear_data_uninstall_isolation`.

Those checks remain `PENDING` until executed on the real release candidate and retained through the content-addressed evidence workflow.
