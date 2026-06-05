# Sogerien AI Install

This repository is prepared for Codex and Claude.

Ask the human only:
- SSH access to the target server.
- Domain name.

Then deploy with this order:
- Install PHP 8.2+, PostgreSQL, nginx/apache.
- Clone repository to the web root.
- Copy `config/config.example.php` to `config/local.php`.
- Fill only server-local values in `config/local.php`.
- Create PostgreSQL database and user.
- Run `psql -d <db> -f database/bootstrap.sql`.
- Point the domain document root to the repository root.
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

