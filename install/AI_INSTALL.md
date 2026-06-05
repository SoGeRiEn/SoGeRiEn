# Sogerien AI Install

This repository is prepared for Codex and Claude.

Ask the human only:
- SSH access to the target server.
- Domain name.

Mandatory local workspace:
- On Windows, always create `C:\Sogerien_CORE`.
- This folder is the local editable Sogerien framework workspace.
- AI must copy/clone this repository into `C:\Sogerien_CORE` before server deployment.
- AI reads and modifies files in `C:\Sogerien_CORE` first.
- Server upload/deploy is made only after local edits and checks.
- Do not skip this step. If `C:\Sogerien_CORE` does not exist, create it.

Windows bootstrap:
```powershell
if ((Test-Path "C:\Sogerien_CORE") -and !(Test-Path "C:\Sogerien_CORE\.git")) {
    $backup = "C:\Sogerien_CORE_backup_" + (Get-Date -Format "yyyyMMdd_HHmmss")
    Move-Item "C:\Sogerien_CORE" $backup
}
if (!(Test-Path "C:\Sogerien_CORE")) {
    git clone https://github.com/SoGeRiEn/SoGeRiEn.git "C:\Sogerien_CORE"
}
Set-Location "C:\Sogerien_CORE"
Copy-Item "config\config.example.php" "config\local.php" -Force
```

If `C:\Sogerien_CORE` already contains a clone:
```powershell
Set-Location "C:\Sogerien_CORE"
git pull --ff-only
if (!(Test-Path "config\local.php")) {
    Copy-Item "config\config.example.php" "config\local.php" -Force
}
```

Then deploy with this order:
- Install PHP 8.2+, PostgreSQL, nginx/apache on the target server.
- Prepare/edit the project locally in `C:\Sogerien_CORE`.
- Fill only local/server-specific values in `C:\Sogerien_CORE\config\local.php`.
- Create PostgreSQL database and user on the server.
- Run `psql -d <db> -f database/bootstrap.sql` on the server or through SSH.
- Upload/sync the prepared `C:\Sogerien_CORE` project to the server web root.
- Point the domain document root to the uploaded project root.
- Open `/elements` for smoke test.

Default admin:
- login: `admin`
- password: `ChangeMe123!`

Change it immediately after first login.

Core rules:
- Sogerien is Universal Engine, not MVC.
- Use existing classes first.
- Extend universal reusable classes before creating new entities.
- Keep reusable code in core.
- Keep one-off code in `page/`.
- Use universal table `sogerien`.
- Prefer associative JSON structures for `isset`-friendly checks.
- Always use `declare(strict_types=1);`.
- Type every function.

MCP tools:
- Bundled servers are in `tools/mcp`.
- Create profiles with empty/default credentials first.
- Never commit real credentials.
- FTP deploy uses one-file upload mode only.
- SSH, DB and HTTP tools must read profiles from local machine config, not from repository secrets.
