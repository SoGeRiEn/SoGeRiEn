param(
  [string]$CodexHome = (Join-Path $env:USERPROFILE ".codex"),
  [string]$ProjectRoot = "C:\MCP_V3",
  [string]$InstallRoot = "C:\MCP_V3\MCP_INSTALL"
)

$ErrorActionPreference = 'Stop'

function Add-UserPathIfMissing {
  param([string]$PathToAdd)
  if (-not (Test-Path $PathToAdd)) { return }
  $userPath = [Environment]::GetEnvironmentVariable('Path', 'User')
  if ([string]::IsNullOrWhiteSpace($userPath)) { $userPath = '' }
  $parts = $userPath -split ';' | Where-Object { $_ -ne '' }
  if ($parts -notcontains $PathToAdd) {
    $newPath = (($parts + $PathToAdd) | Select-Object -Unique) -join ';'
    [Environment]::SetEnvironmentVariable('Path', $newPath, 'User')
  }
}

$pythonRoot = Join-Path $ProjectRoot 'Python3143'
Add-UserPathIfMissing -PathToAdd $pythonRoot
Add-UserPathIfMissing -PathToAdd (Join-Path $pythonRoot 'Scripts')

$skillsSrc = Join-Path $InstallRoot 'CODEX_TEMPLATE\.codex\skills'
$skillsDst = Join-Path $CodexHome 'skills'
New-Item -ItemType Directory -Force -Path $skillsDst | Out-Null
$allowedSkills = @(
  '.system',
  'doc',
  'figma',
  'figma-implement-design',
  'frontend-skill',
  'local-tools-ftp-sync',
  'MCP',
  'mcp-chatgpt-apps',
  'mcp-security-threat-model',
  'playwright',
  'sogerien-genius-core',
  'winui-app'
)

if (Test-Path $skillsSrc) {
  robocopy $skillsSrc $skillsDst /E /R:1 /W:1 /NFL /NDL /NJH /NJS /NC /NS | Out-Null
}

# Hard cleanup of skills folder - remove all stale skills outside whitelist.
Get-ChildItem $skillsDst -Directory -Force | ForEach-Object {
  if ($allowedSkills -notcontains $_.Name) {
    Remove-Item -LiteralPath $_.FullName -Recurse -Force
  }
}

$agentsSrc = Join-Path $InstallRoot 'CODEX_TEMPLATE\.codex\AGENTS.md'
$agentsDst = Join-Path $CodexHome 'AGENTS.md'
if (Test-Path $agentsSrc) {
  Copy-Item $agentsSrc $agentsDst -Force
}

$configPath = Join-Path $CodexHome 'config.toml'
if (-not (Test-Path $configPath)) {
  New-Item -ItemType File -Path $configPath -Force | Out-Null
}
$stamp = Get-Date -Format 'yyyyMMdd_HHmmss'
Copy-Item $configPath "$configPath.$stamp.bak" -Force
$text = Get-Content -Raw $configPath

$skillPaths = @(
  (Join-Path $CodexHome "skills\sogerien-genius-core\SKILL.md"),
  (Join-Path $CodexHome "skills\mcp-security-threat-model\SKILL.md"),
  (Join-Path $CodexHome "skills\mcp-chatgpt-apps\SKILL.md"),
  (Join-Path $CodexHome "skills\MCP\SKILL.md"),
  (Join-Path $CodexHome "skills\winui-app\SKILL.md")
)

foreach ($sp in $skillPaths) {
  if ($text -notmatch [regex]::Escape($sp)) {
    $text += "`r`n[[skills.config]]`r`npath = '$sp'`r`nenabled = false`r`n"
  }
}

if ($text -notmatch "\[projects\.'C:\\MCP_V3'\]") {
  $text += "`r`n[projects.'C:\MCP_V3']`r`ntrust_level = `"trusted`"`r`n"
}

$servers = @(
  @{ Name='mcp_codex_agents'; Dir='MCP_CODEX_AGENTS'; Script='MCP_CODEX_AGENTS.py' },
  @{ Name='mcp_codex_phone'; Dir='MCP_CODEX_PHONE'; Script='MCP_CODEX_PHONE.py' },
  @{ Name='mcp_filesystem'; Dir='MCP_FILESYSTEM'; Script='MCP_FILESYSTEM.py' },
  @{ Name='mcp_ftp'; Dir='MCP_FTP'; Script='MCP_FTP.py' },
  @{ Name='mcp_http'; Dir='MCP_HTTP'; Script='MCP_HTTP.py' },
  @{ Name='mcp_mssql'; Dir='MCP_MSSQL'; Script='MCP_MSSQL.py' },
  @{ Name='mcp_mysql'; Dir='MCP_MYSQL'; Script='MCP_MYSQL.py' },
  @{ Name='mcp_php'; Dir='MCP_PHP'; Script='MCP_PHP.py' },
  @{ Name='mcp_postgres'; Dir='MCP_POSTGRES'; Script='MCP_POSTGRES.py' },
  @{ Name='mcp_pyppeteer'; Dir='MCP_PYPPETEER'; Script='MCP_PYPPETEER.py' },
  @{ Name='mcp_screenshot'; Dir='MCP_SCREENSHOT'; Script='MCP_SCREENSHOT.py' },
  @{ Name='mcp_smtp'; Dir='MCP_SMTP'; Script='MCP_SMTP.py' },
  @{ Name='mcp_ssh'; Dir='MCP_SSH'; Script='MCP_SSH.py' },
  @{ Name='mcp_windows'; Dir='MCP_WINDOWS'; Script='MCP_WINDOWS.py' },
  @{ Name='mcp_mikrotik_api'; Dir='MCP_Mikrotik_API'; Script='MCP_Mikrotik_API.py' },
  @{ Name='mcp_mikrotik_winbox'; Dir='MCP_Mikrotik_Winbox'; Script='MCP_Mikrotik_Winbox.py' },
  @{ Name='mcp_chrome_devtools'; Dir='MCP_Chrome_DevTools'; Script='MCP_Chrome_DevTools.py' },
  @{ Name='mcp_cloudefire'; Dir='MCP_CloudeFire'; Script='MCP_CloudeFire.py' },
  @{ Name='mcp_esxi_vcenter'; Dir='MCP_ESXIvCenter'; Script='MCP_ESXIvCenter.py' },
  @{ Name='mcp_windows_admin'; Dir='MCP_WindowsAdmin'; Script='MCP_WindowsAdmin.py' }
)

foreach ($s in $servers) {
  $header = "[mcp_servers.$($s.Name)]"
  if ($text -notmatch [regex]::Escape($header)) {
    $dirPath = "C:\MCP_V3\$($s.Dir)"
    $pyPath = "$dirPath\.venv\Scripts\python.exe"
    $scriptPath = "$dirPath\$($s.Script)"
    $text += "`r`n$header`r`nargs = ['$scriptPath', `"--transport`", `"stdio`"]`r`ncommand = '$pyPath'`r`nenabled = true`r`n"
    $text += "`r`n[mcp_servers.$($s.Name).env]`r`nPYTHONIOENCODING = `"utf-8`"`r`nPYTHONUTF8 = `"1`"`r`n"
  }
}

Set-Content -Path $configPath -Value $text -Encoding UTF8

Write-Host "MCP install complete."
Write-Host "Codex home: $CodexHome"
Write-Host "Config updated: $configPath"
Write-Host "Restart Codex app to reload MCP servers."
