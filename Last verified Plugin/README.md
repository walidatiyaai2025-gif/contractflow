# Last verified SafeContracts Plugin

This directory is the permanent repository slot for the **single latest verified** WordPress plugin package.

Expected retained files after the first verified publication:

- `SafeContracts-latest.zip`
- `SafeContracts-latest.zip.sha256`
- `VERIFIED.json`

Do not keep historical ZIPs here. Historical release packages belong in GitHub Releases.

A ZIP may only be placed here when the exact source commit passed all required SafeContracts Quality Gates and the install/upgrade candidate was verified according to `docs/PRODUCTION_ENVIRONMENT_BUILD.md`.

Use:

```bash
python3 scripts/verified_artifacts.py publish \
  --plugin /path/to/SafeContracts.zip \
  --apk /path/to/app-release.apk \
  --source-sha <40-char-commit-sha> \
  --quality-run-id <github-actions-run-id> \
  --quality-gates-passed
```

Then run:

```bash
python3 scripts/verified_artifacts.py check
```

Never commit secrets, local caches, logs, debug archives or unverified packages into this directory.