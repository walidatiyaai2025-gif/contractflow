[CmdletBinding()]
param(
    [string]$Repository = 'walidatiyaai2025-gif/contractflow',
    [string]$Branch = 'enterprise-safecontracts',
    [string]$BreakGlassNote = 'No routine bypass is configured. Emergency protection changes require repository-owner approval and must be recorded in #522.',
    [string]$EvidenceRoot = '',
    [string]$PythonCommand = 'python',
    [switch]$Apply
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$ExpectedRepository = 'walidatiyaai2025-gif/contractflow'
$ExpectedBranch = 'enterprise-safecontracts'
$GitHubActionsAppSlug = 'github-actions'
$GitHubApiVersion = '2026-03-10'

if ($Repository -ne $ExpectedRepository) {
    throw "Refusing repository '$Repository'. Expected exactly '$ExpectedRepository'."
}
if ($Branch -ne $ExpectedBranch) {
    throw "Refusing branch '$Branch'. Expected exactly '$ExpectedBranch'."
}
if ([string]::IsNullOrWhiteSpace($BreakGlassNote) -or $BreakGlassNote.Trim().Length -lt 12) {
    throw 'BreakGlassNote must contain the actual approved emergency/bypass policy.'
}

function Resolve-GitHubActionsAppId {
    $Headers = @{
        Accept = 'application/vnd.github+json'
        'X-GitHub-Api-Version' = $GitHubApiVersion
        'User-Agent' = 'enterprise-safecontracts-branch-protection-helper'
    }
    try {
        $App = Invoke-RestMethod `
            -Method Get `
            -Uri "https://api.github.com/apps/$GitHubActionsAppSlug" `
            -Headers $Headers
    }
    catch {
        throw "Unable to resolve the GitHub Actions App from GitHub API: $($_.Exception.Message)"
    }

    if ($null -eq $App -or $App.slug -ne $GitHubActionsAppSlug) {
        throw "GitHub API did not return the expected '$GitHubActionsAppSlug' app identity."
    }
    $AppId = 0L
    if (-not [Int64]::TryParse([string]$App.id, [ref]$AppId) -or $AppId -le 0) {
        throw 'GitHub Actions App ID must resolve to a positive integer.'
    }
    return $AppId
}

$GitHubActionsAppId = Resolve-GitHubActionsAppId
Write-Host "Resolved required status-check source: $GitHubActionsAppSlug (App ID $GitHubActionsAppId)"

$Protection = [ordered]@{
    required_status_checks = [ordered]@{
        strict = $true
        checks = @(
            [ordered]@{ context = 'esc-foundation'; app_id = $GitHubActionsAppId },
            [ordered]@{ context = 'esc-mobile'; app_id = $GitHubActionsAppId }
        )
    }
    enforce_admins = $true
    required_pull_request_reviews = [ordered]@{
        dismiss_stale_reviews = $false
        require_code_owner_reviews = $false
        required_approving_review_count = 0
        require_last_push_approval = $false
    }
    restrictions = $null
    required_linear_history = $false
    allow_force_pushes = $false
    allow_deletions = $false
    block_creations = $false
    required_conversation_resolution = $true
    lock_branch = $false
    allow_fork_syncing = $false
}

$ProtectionJson = $Protection | ConvertTo-Json -Depth 10 -Compress
Write-Host 'ESC branch-protection payload preview:'
$Protection | ConvertTo-Json -Depth 10

if (-not $Apply) {
    Write-Host ''
    Write-Host 'PREVIEW ONLY: no GitHub settings were changed.'
    Write-Host 'Re-run with -Apply after reviewing the payload and authenticating gh with repository administration permission.'
    exit 0
}

if (-not (Get-Command gh -ErrorAction SilentlyContinue)) {
    throw 'GitHub CLI (gh) is required for -Apply.'
}
if (-not (Get-Command $PythonCommand -ErrorAction SilentlyContinue)) {
    throw "Python command '$PythonCommand' was not found."
}

& gh auth status *> $null
if ($LASTEXITCODE -ne 0) {
    throw 'gh is not authenticated. Run gh auth login with an approved repository-admin identity.'
}

function Invoke-GhApi {
    param([string[]]$Arguments)

    $Output = & gh @Arguments 2>&1
    if ($LASTEXITCODE -ne 0) {
        $Message = ($Output | ForEach-Object { $_.ToString() }) -join [Environment]::NewLine
        throw "gh api failed: $Message"
    }
    return (($Output | ForEach-Object { $_.ToString() }) -join [Environment]::NewLine)
}

function Write-Utf8NoBom {
    param(
        [string]$Path,
        [string]$Content
    )

    $Encoding = [System.Text.UTF8Encoding]::new($false)
    [System.IO.File]::WriteAllText($Path, $Content + [Environment]::NewLine, $Encoding)
}

Write-Warning 'Applying protection to enterprise-safecontracts. This changes repository administration settings.'
$ProtectionJson | & gh api `
    --method PUT `
    -H 'Accept: application/vnd.github+json' `
    -H "X-GitHub-Api-Version: $GitHubApiVersion" `
    "repos/$Repository/branches/$Branch/protection" `
    --input - *> $null
if ($LASTEXITCODE -ne 0) {
    throw 'GitHub rejected the branch-protection update.'
}

if ([string]::IsNullOrWhiteSpace($EvidenceRoot)) {
    $Stamp = [DateTime]::UtcNow.ToString('yyyyMMddTHHmmssZ')
    $EvidenceRoot = Join-Path (Get-Location) "esc-branch-protection-evidence-$Stamp"
}
if (Test-Path -LiteralPath $EvidenceRoot) {
    throw "EvidenceRoot already exists: $EvidenceRoot"
}
New-Item -ItemType Directory -Path $EvidenceRoot | Out-Null

$BranchJson = Invoke-GhApi @(
    'api',
    '-H', 'Accept: application/vnd.github+json',
    '-H', "X-GitHub-Api-Version: $GitHubApiVersion",
    "repos/$Repository/branches/$Branch"
)
$RulesJson = Invoke-GhApi @(
    'api',
    '-H', 'Accept: application/vnd.github+json',
    '-H', "X-GitHub-Api-Version: $GitHubApiVersion",
    "repos/$Repository/rules/branches/$Branch"
)
$LegacyProtectionJson = Invoke-GhApi @(
    'api',
    '-H', 'Accept: application/vnd.github+json',
    '-H', "X-GitHub-Api-Version: $GitHubApiVersion",
    "repos/$Repository/branches/$Branch/protection"
)

$BranchPath = Join-Path $EvidenceRoot 'branch.json'
$RulesPath = Join-Path $EvidenceRoot 'rules.json'
$ProtectionPath = Join-Path $EvidenceRoot 'protection.json'
$AppIdentityPath = Join-Path $EvidenceRoot 'github-actions-app.json'
$AuditPath = Join-Path $EvidenceRoot 'esc-branch-protection-audit.json'

Write-Utf8NoBom -Path $BranchPath -Content $BranchJson
Write-Utf8NoBom -Path $RulesPath -Content $RulesJson
Write-Utf8NoBom -Path $ProtectionPath -Content $LegacyProtectionJson
Write-Utf8NoBom -Path $AppIdentityPath -Content (([ordered]@{
    slug = $GitHubActionsAppSlug
    id = $GitHubActionsAppId
}) | ConvertTo-Json -Compress)

$AuditScript = Join-Path $PSScriptRoot 'audit_esc_branch_protection.py'
& $PythonCommand $AuditScript `
    --branch-json $BranchPath `
    --rules-json $RulesPath `
    --protection-json $ProtectionPath `
    --break-glass-note $BreakGlassNote `
    --expected-status-check-app-id $GitHubActionsAppId `
    --output $AuditPath
if ($LASTEXITCODE -ne 0) {
    throw "Protection was applied, but verification failed. Inspect evidence under '$EvidenceRoot' and keep #522 open."
}

Write-Host "PASS: protection applied and independently audited with GitHub Actions source pinning. Evidence: $EvidenceRoot"
Write-Host 'Keep #522 open until the retained evidence is reviewed against the repository-admin acceptance criteria.'
