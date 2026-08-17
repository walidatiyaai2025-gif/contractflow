[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)]
    [string]$SafeApk,

    [Parameter(Mandatory = $true)]
    [string]$EscApk,

    [Parameter(Mandatory = $true)]
    [string]$SourceSha,

    [Parameter(Mandatory = $true)]
    [string]$DeviceSerial,

    [Parameter(Mandatory = $true)]
    [string]$Tester,

    [Parameter(Mandatory = $true)]
    [string]$EvidenceRoot,

    [string]$PythonCommand = "python",
    [string]$AdbCommand = "adb",
    [string]$ApkAnalyzerCommand = "apkanalyzer",
    [string]$ApkSignerCommand = "apksigner"
)

Set-StrictMode -Version Latest
$ErrorActionPreference = "Stop"

$SafeApplicationId = "com.safecontracts.safecontracts_mobile"
$EscApplicationId = "com.safecontracts.enterprise"
$PendingDecision = "PENDING_REAL_DEVICE_UAT"
$RepoRoot = Split-Path -Parent $PSScriptRoot
$Collector = Join-Path $PSScriptRoot "collect_esc_android_coexistence_uat_session.py"

function Fail([string]$Message) {
    throw "ESC Android coexistence Windows UAT runner: $Message"
}

function Resolve-RequiredFile([string]$Path, [string]$Label) {
    if (-not (Test-Path -LiteralPath $Path -PathType Leaf)) {
        Fail "$Label is missing: $Path"
    }
    return (Resolve-Path -LiteralPath $Path).Path
}

function Resolve-RequiredCommand([string]$Name) {
    $command = Get-Command $Name -ErrorAction SilentlyContinue | Select-Object -First 1
    if ($null -eq $command) {
        Fail "required command is unavailable: $Name"
    }
    if ($command.Path) {
        return $command.Path
    }
    if ($command.Source) {
        return $command.Source
    }
    return $Name
}

function Invoke-External([string]$FilePath, [string[]]$Arguments) {
    $output = & $FilePath @Arguments 2>&1 | Out-String
    if ($LASTEXITCODE -ne 0) {
        $joined = $Arguments -join " "
        Fail "command failed ($LASTEXITCODE): $FilePath $joined`n$output"
    }
    return $output.TrimEnd()
}

function Add-SnapshotCommand(
    [System.Collections.Generic.List[string]]$Target,
    [string]$Label,
    [string[]]$Arguments
) {
    $Target.Add("## $Label")
    $Target.Add((Invoke-External $script:AdbExe $Arguments))
    $Target.Add("")
}

function Add-PackageSnapshot(
    [System.Collections.Generic.List[string]]$Target,
    [string]$PackageId
) {
    $Target.Add("## package $PackageId")
    $Target.Add((Invoke-External $script:AdbExe @("-s", $script:NormalizedSerial, "shell", "pm", "path", $PackageId)))
    $raw = Invoke-External $script:AdbExe @("-s", $script:NormalizedSerial, "shell", "dumpsys", "package", $PackageId)
    $selected = @(
        $raw -split "`r?`n" |
            Where-Object {
                $_ -match "(^|\s)(userId=|versionCode=|versionName=|firstInstallTime=|lastUpdateTime=|dataDir=)"
            } |
            ForEach-Object { $_.Trim() }
    )
    if ($selected.Count -eq 0) {
        Fail "package metadata was not observable for $PackageId"
    }
    foreach ($line in $selected) {
        $Target.Add($line)
    }
    $Target.Add("")
}

$NormalizedSourceSha = $SourceSha.Trim().ToLowerInvariant()
if ($NormalizedSourceSha -notmatch "^[0-9a-f]{40}$") {
    Fail "SourceSha must be a full 40-character Git SHA"
}

$NormalizedSerial = $DeviceSerial.Trim()
if ([string]::IsNullOrWhiteSpace($NormalizedSerial)) {
    Fail "DeviceSerial is required"
}

$NormalizedTester = $Tester.Trim()
if ($NormalizedTester.Length -lt 3) {
    Fail "Tester must contain at least three characters"
}

$SafeApkPath = Resolve-RequiredFile $SafeApk "Safe Contract APK"
$EscApkPath = Resolve-RequiredFile $EscApk "ESC APK"
$CollectorPath = Resolve-RequiredFile $Collector "objective UAT session collector"
$PythonExe = Resolve-RequiredCommand $PythonCommand
$script:AdbExe = Resolve-RequiredCommand $AdbCommand
$ApkAnalyzerExe = Resolve-RequiredCommand $ApkAnalyzerCommand
$ApkSignerExe = Resolve-RequiredCommand $ApkSignerCommand
$script:NormalizedSerial = $NormalizedSerial

$EvidenceRootPath = [IO.Path]::GetFullPath($EvidenceRoot)
if (Test-Path -LiteralPath $EvidenceRootPath) {
    $existing = Get-ChildItem -LiteralPath $EvidenceRootPath -Force | Select-Object -First 1
    if ($null -ne $existing) {
        Fail "EvidenceRoot must be empty to prevent evidence overwrite: $EvidenceRootPath"
    }
} else {
    New-Item -ItemType Directory -Path $EvidenceRootPath -Force | Out-Null
}

$deviceState = Invoke-External $script:AdbExe @("-s", $NormalizedSerial, "get-state")
if ($deviceState.Trim() -ne "device") {
    Fail "selected ADB target is not in device state: $NormalizedSerial"
}

$ObjectiveDraftPath = Join-Path $EvidenceRootPath "objective_draft.json"
$collectorArguments = @(
    $CollectorPath,
    "--safe-apk", $SafeApkPath,
    "--esc-apk", $EscApkPath,
    "--source-sha", $NormalizedSourceSha,
    "--device-serial", $NormalizedSerial,
    "--tester", $NormalizedTester,
    "--output", $ObjectiveDraftPath,
    "--apkanalyzer", $ApkAnalyzerExe,
    "--apksigner", $ApkSignerExe,
    "--adb", $script:AdbExe
)

$collectorOutput = Invoke-External $PythonExe $collectorArguments
if (-not (Test-Path -LiteralPath $ObjectiveDraftPath -PathType Leaf)) {
    Fail "objective collector did not create objective_draft.json"
}

$draft = Get-Content -LiteralPath $ObjectiveDraftPath -Raw | ConvertFrom-Json
if ($draft.source_sha -ne $NormalizedSourceSha) {
    Fail "objective draft source SHA does not match the requested source"
}
if ($draft.decision -ne "PENDING") {
    Fail "objective draft must remain pending"
}
if ($draft.device.reference -ne $NormalizedSerial) {
    Fail "objective draft device reference does not match the selected device"
}

$snapshot = [System.Collections.Generic.List[string]]::new()
$snapshot.Add("ESC Android coexistence Windows UAT device snapshot")
$snapshot.Add("source_sha=$NormalizedSourceSha")
$snapshot.Add("device_serial=$NormalizedSerial")
$snapshot.Add("tester=$NormalizedTester")
$snapshot.Add("captured_at_utc=$([DateTime]::UtcNow.ToString('o'))")
$snapshot.Add("")
Add-SnapshotCommand $snapshot "manufacturer" @("-s", $NormalizedSerial, "shell", "getprop", "ro.product.manufacturer")
Add-SnapshotCommand $snapshot "model" @("-s", $NormalizedSerial, "shell", "getprop", "ro.product.model")
Add-SnapshotCommand $snapshot "android_release" @("-s", $NormalizedSerial, "shell", "getprop", "ro.build.version.release")
Add-SnapshotCommand $snapshot "android_sdk" @("-s", $NormalizedSerial, "shell", "getprop", "ro.build.version.sdk")
Add-PackageSnapshot $snapshot $SafeApplicationId
Add-PackageSnapshot $snapshot $EscApplicationId
$DeviceSnapshotPath = Join-Path $EvidenceRootPath "device_snapshot.txt"
[IO.File]::WriteAllLines($DeviceSnapshotPath, $snapshot, [Text.UTF8Encoding]::new($false))

$manualChecks = @(
    [ordered]@{
        key = "session_isolation"
        status = "PENDING"
        expected_artifact = "manual/session_isolation.zip"
        operator_action = "Use the approved accounts on both applications, verify independent retained session and tenant context after relaunch, and retain reviewed visual/log evidence."
    },
    [ordered]@{
        key = "safe_only_push"
        status = "PENDING"
        expected_artifact = "manual/safe_only_push.zip"
        operator_action = "Use the approved Firebase/test-notification path to target Safe Contract only and retain evidence that ESC did not receive or open the notification."
    },
    [ordered]@{
        key = "esc_only_push"
        status = "PENDING"
        expected_artifact = "manual/esc_only_push.zip"
        operator_action = "Use the approved ESC Firebase/test-notification path to target ESC only and retain evidence that Safe Contract did not receive or open the notification."
    },
    [ordered]@{
        key = "independent_update"
        status = "PENDING"
        expected_artifact = "manual/independent_update.zip"
        operator_action = "Perform the approved independent-update scenario for both products while both remain present, then retain before/after version and data-integrity evidence."
    },
    [ordered]@{
        key = "clear_data_uninstall_isolation"
        status = "PENDING"
        expected_artifact = "manual/clear_data_uninstall_isolation.zip"
        operator_action = "Perform the approved data-lifecycle and removal isolation scenario and retain evidence that the untouched product remains installed, launchable, and data-intact."
    }
)

$additionalArtifacts = @(
    [ordered]@{ key = "esc_firebase_identity"; expected_artifact = "manual/esc_firebase_identity.zip" },
    [ordered]@{ key = "business_uat"; expected_artifact = "manual/business_uat.zip" },
    [ordered]@{ key = "coexistence"; expected_artifact = "manual/coexistence_review.zip" },
    [ordered]@{ key = "firebase_delivery"; expected_artifact = "manual/firebase_delivery_review.zip" }
)

$requirements = [ordered]@{
    schema_version = 1
    decision = $PendingDecision
    source_sha = $NormalizedSourceSha
    device_serial = $NormalizedSerial
    tester = $NormalizedTester
    objective_draft = "objective_draft.json"
    device_snapshot = "device_snapshot.txt"
    manual_checks = $manualChecks
    additional_required_artifacts = $additionalArtifacts
    boundary = @(
        "This runner performs objective collection and read-only ADB snapshots only.",
        "It does not perform update, data lifecycle, removal, notification-send, authentication, backend-session, evidence-bundle, or finalization operations.",
        "Do not create the expected manual artifact until the corresponding physical-device scenario has actually been executed and reviewed."
    )
}
$RequirementsPath = Join-Path $EvidenceRootPath "manual_evidence_requirements.json"
$requirements | ConvertTo-Json -Depth 8 | Set-Content -LiteralPath $RequirementsPath -Encoding UTF8

$summary = [ordered]@{
    schema_version = 1
    decision = $PendingDecision
    source_sha = $NormalizedSourceSha
    device_serial = $NormalizedSerial
    tester = $NormalizedTester
    objective_collection = "COMPLETED"
    manual_runtime_uat = "PENDING"
    evidence_bundle = "NOT_RUN"
    finalization = "NOT_RUN"
    objective_draft = "objective_draft.json"
    device_snapshot = "device_snapshot.txt"
    manual_requirements = "manual_evidence_requirements.json"
    collector_output = $collectorOutput
}
$SummaryPath = Join-Path $EvidenceRootPath "runner_summary.json"
$summary | ConvertTo-Json -Depth 6 | Set-Content -LiteralPath $SummaryPath -Encoding UTF8

Write-Host "ESC Windows UAT operator session prepared."
Write-Host "Objective draft: $ObjectiveDraftPath"
Write-Host "Device snapshot: $DeviceSnapshotPath"
Write-Host "Manual requirements: $RequirementsPath"
Write-Host "Overall state remains $PendingDecision until physical-device runtime evidence is executed, reviewed, bundled, and finalized by the existing separate tooling."
