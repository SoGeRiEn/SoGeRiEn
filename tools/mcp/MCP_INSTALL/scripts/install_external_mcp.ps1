param(
  [string]$CodexHome = "C:\Users\SoGeRiEn\.codex",
  [string]$ProjectRoot = "C:\MCP_V3",
  [switch]$EnableSafe,
  [switch]$InstallPythonDeps,
  [switch]$InstallPowerShellMcp
)

$ErrorActionPreference = 'Stop'

$templatePath = Join-Path $ProjectRoot 'MCP_INSTALL\config.external_mcp_servers.template.toml'
$configPath = Join-Path $CodexHome 'config.toml'

if (-not (Test-Path $templatePath)) {
  throw "Template not found: $templatePath"
}

New-Item -ItemType Directory -Force -Path $CodexHome | Out-Null
if (-not (Test-Path $configPath)) {
  New-Item -ItemType File -Force -Path $configPath | Out-Null
}

$stamp = Get-Date -Format 'yyyyMMdd_HHmmss'
Copy-Item $configPath "$configPath.$stamp.external.bak" -Force

$config = Get-Content -Raw $configPath
$template = Get-Content -Raw $templatePath
if ($null -eq $config) { $config = '' }

$marker = "# BEGIN C:\MCP_V3 external MCP pack"
$endMarker = "# END C:\MCP_V3 external MCP pack"
if ($EnableSafe) {
  $template = $template -replace '(?ms)(\[mcp_servers\.cloudflare_docs\].*?)enabled = false', '$1enabled = true'
}

$block = "$marker`r`n$template`r`n$endMarker"
$pattern = "(?ms)" + [regex]::Escape($marker) + ".*?" + [regex]::Escape($endMarker)
if ($config -match $pattern) {
  $config = [regex]::Replace($config, $pattern, [System.Text.RegularExpressions.MatchEvaluator]{ param($m) $block })
} else {
  $config += "`r`n`r`n$block`r`n"
}

Set-Content -Path $configPath -Value $config -Encoding UTF8

if ($InstallPythonDeps) {
  $uv = Join-Path $env:USERPROFILE '.local\bin\uv.exe'
  if (-not (Test-Path $uv)) { $uv = 'uv' }
  & $uv --directory (Join-Path $ProjectRoot 'MCP_EXTERNAL\Windows-MCP') sync
  & $uv --directory (Join-Path $ProjectRoot 'MCP_EXTERNAL\awslabs-mcp\src\aws-api-mcp-server') sync
}

if ($InstallPowerShellMcp) {
  $pwsh = Get-Command pwsh -ErrorAction SilentlyContinue
  if (-not $pwsh) {
    throw "PowerShell 7 (pwsh) is not installed. Install PowerShell 7 first, then rerun with -InstallPowerShellMcp."
  }
  & $pwsh.Source -NoLogo -NoProfile -Command "Set-PSRepository PSGallery -InstallationPolicy Trusted; Install-Module PowerShell.MCP -Scope CurrentUser -Force"
}

Write-Host "External MCP template merged into: $configPath"
Write-Host "Backup: $configPath.$stamp.external.bak"
Write-Host "Restart Codex to reload MCP servers."
