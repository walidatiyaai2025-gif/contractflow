# Last verified SafeContracts Plugin

This directory is the permanent repository slot for the **single latest verified** WordPress plugin package.

Expected retained files after the first verified publication:

- `SafeContracts-latest.zip`
- `SafeContracts-latest.zip.sha256`
- `VERIFIED.json`

Do not keep historical ZIPs here. Historical release packages belong in GitHub Releases.

A ZIP may only be placed here when the exact functional source candidate passed all required SafeContracts Quality Gates and the package was built/validated from `wordpress-plugin/safecontracts/` only.

Build the deterministic installable candidate with:

```bash
python3 scripts/package_plugin.py build --output dist/SafeContracts-plugin-candidate.zip
python3 scripts/package_plugin.py check dist/SafeContracts-plugin-candidate.zip
```

After the exact source candidate has a successful Quality Gates run, publish only the plugin with:

```bash
python3 scripts/verified_artifacts.py publish-plugin \
  --plugin dist/SafeContracts-plugin-candidate.zip \
  --source-sha <40-char-source-commit-sha> \
  --quality-run-id <github-actions-run-id> \
  --quality-gates-passed
```

Then run:

```bash
python3 scripts/verified_artifacts.py check --require-plugin
```

The plugin ZIP is intentionally publishable independently of the Android APK. A blocked mobile signing/UAT dependency must not prevent retaining the latest verified server plugin.

Never commit secrets, local caches, logs, debug archives or unverified packages into this directory.
