# ESC local persistence isolation contract

Enterprise Safe Contracts Android must not inherit or silently share local persistence conventions from Safe Contract.

## Current production persistence

The current mobile client declares only one local persistence package: `flutter_secure_storage`.

Its only production owner is:

`mobile/lib/core/auth/mobile_token_store.dart`

The persistent bearer token key is fixed to:

`enterprise_safecontracts.mobile.bearer_token`

Combined with the distinct Android application ID `com.safecontracts.enterprise`, this keeps the current secure token state both application-sandboxed and Enterprise-namespaced.

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
- direct `flutter_secure_storage` use outside the audited ESC token store;
- drift from the Enterprise secure-token key.

The gate is intentionally restrictive. New persistent preferences, cache files, databases, offline queues, or secure-storage records are allowed only after the implementation defines and tests an explicit Enterprise namespace/isolation contract. The correct change is to extend the reviewed storage design and its regression coverage, not to delete or weaken the gate.

## Runtime-UAT boundary

This static contract does not prove real-device session/data lifecycle behavior. The physical-device coexistence UAT under #421 must still demonstrate:

- `session_isolation`;
- `clear_data_uninstall_isolation`.

Those checks remain `PENDING` until executed on the real release candidate and retained through the content-addressed evidence workflow.
