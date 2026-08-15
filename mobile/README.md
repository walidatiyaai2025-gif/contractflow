# SafeContracts Mobile

Flutter foundation for the SafeContracts mobile client.

## Architectural boundary

WordPress + the SafeContracts plugin remain the source of truth. This client must not duplicate authoritative contract, payment, permission or financial logic. Server responses and server-enforced scope drive the mobile experience.

## Configuration

Public compile-time configuration is read through Dart defines:

```bash
flutter run --dart-define-from-file=config/local.example.json
```

Supported values:

- `SC_ENV`: `local`, `staging`, `production`
- `SC_API_BASE_URL`: absolute SafeContracts REST base URL

Do not place passwords, tokens, Firebase private credentials or other secrets in these files. Real `config/*.json` files are ignored by Git; only `*.example.json` belongs in the repository.

## Validation

```bash
flutter pub get
dart format --output=none --set-exit-if-changed lib test
flutter analyze
flutter test
```

Platform runners and release signing are intentionally outside this P0 foundation scope and will be added by the relevant mobile/build tasks.
