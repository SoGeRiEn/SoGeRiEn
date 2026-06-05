# Sogerien AI Install

This repository is prepared for Codex and Claude.

Ask the human:
- Local folder where the repository must be installed and edited.
- SSH access to the target server.
- Domain name.

Mandatory local workspace:
- AI must ask the user for the local install folder before deployment.
- Recommended default on Windows: `C:\Sogerien_CORE`.
- The chosen folder is the local editable Sogerien framework workspace.
- AI must copy/clone this repository into the chosen local folder before server deployment.
- AI reads and modifies files in the chosen local folder first.
- Server upload/deploy is made only after local edits and checks.
- Do not skip this step. If the chosen local folder does not exist, create it.
- Do not edit files directly on the server as the main workflow.

Windows bootstrap:
```powershell
$LocalRepo = "C:\Sogerien_CORE" # replace with the folder provided by the user
if ((Test-Path $LocalRepo) -and !(Test-Path (Join-Path $LocalRepo ".git"))) {
    $backup = $LocalRepo.TrimEnd("\") + "_backup_" + (Get-Date -Format "yyyyMMdd_HHmmss")
    Move-Item $LocalRepo $backup
}
if (!(Test-Path $LocalRepo)) {
    git clone https://github.com/SoGeRiEn/SoGeRiEn.git $LocalRepo
}
Set-Location $LocalRepo
Copy-Item "config\config.example.php" "config\local.php" -Force
```

If the chosen local folder already contains a clone:
```powershell
$LocalRepo = "C:\Sogerien_CORE" # replace with the folder provided by the user
Set-Location $LocalRepo
git pull --ff-only
if (!(Test-Path "config\local.php")) {
    Copy-Item "config\config.example.php" "config\local.php" -Force
}
```

Then deploy with this order:
- Install PHP 8.2+, PostgreSQL, nginx/apache on the target server.
- Prepare/edit the project locally in the user-selected local repository folder.
- Fill only local/server-specific values in `<LOCAL_REPO>\config\local.php`.
- Create PostgreSQL database and user on the server.
- Run `psql -d <db> -f database/bootstrap.sql` on the server or through SSH.
- Upload/sync the prepared local repository folder to the server web root.
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
