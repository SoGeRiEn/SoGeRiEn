param(
    [string]$ProjectRoot = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path,
    [string]$OutputDir = ''
)

$ErrorActionPreference = 'Stop'

if ($OutputDir -eq '') {
    $OutputDir = Join-Path $ProjectRoot 'dist\server_package'
}

$resolvedProject = (Resolve-Path $ProjectRoot).Path
if (-not $resolvedProject.EndsWith('Sogerien')) {
    Write-Host "Project root: $resolvedProject"
}

if (Test-Path $OutputDir) {
    $resolvedOut = (Resolve-Path $OutputDir).Path
    if (-not $resolvedOut.StartsWith($resolvedProject, [StringComparison]::OrdinalIgnoreCase)) {
        throw "Refusing to clean output outside project: $resolvedOut"
    }
    Remove-Item -LiteralPath $resolvedOut -Recurse -Force
}

New-Item -ItemType Directory -Force -Path $OutputDir | Out-Null

$include = @(
    '.htaccess',
    'Sogerien.php',
    'index.php',
    'self_update.php',
    'version.json',
    'classes',
    'config\config.example.php',
    'database',
    'i18n',
    'page'
)

foreach ($item in $include) {
    $src = Join-Path $ProjectRoot $item
    if (-not (Test-Path $src)) {
        continue
    }

    $dst = Join-Path $OutputDir $item
    $parent = Split-Path $dst -Parent
    New-Item -ItemType Directory -Force -Path $parent | Out-Null

    if (Test-Path $src -PathType Container) {
        robocopy $src $dst /E /R:1 /W:1 /NFL /NDL /NJH /NJS /NC /NS | Out-Null
        if ($LASTEXITCODE -gt 7) {
            throw "robocopy failed for $src with code $LASTEXITCODE"
        }
    } else {
        Copy-Item -LiteralPath $src -Destination $dst -Force
    }
}

$forbidden = @(
    'AGENTS.md',
    'CLAUDE.md',
    '.git',
    'tools\mcp',
    'install\AI_INSTALL.md',
    'config\local.php'
)

foreach ($path in $forbidden) {
    $found = Get-ChildItem -Path $OutputDir -Recurse -Force -ErrorAction SilentlyContinue |
        Where-Object { $_.FullName -like "*\$path" -or $_.Name -eq $path }
    if ($found) {
        throw "Forbidden deploy artifact found: $path"
    }
}

Write-Host "Server package ready: $OutputDir"
