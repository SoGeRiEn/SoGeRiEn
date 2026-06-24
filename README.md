# SoGeRiEn

SoGeRiEn is a Universal Engine for AI-agent development with Codex and Claude.

License: Apache-2.0

Core rules:
- Use framework classes through `help()` first.
- Use existing classes before adding new code.
- Reusable logic belongs in core classes.
- One-off project logic belongs in `page`.
- MVC-style controllers, repositories, service layers and extra entities are forbidden unless the pattern already exists in SoGeRiEn.
- Do not commit credentials.

Install:
- Read `install/AI_INSTALL.md`.
- Copy `config/config.example.php` to `config/local.php`.
- Put local secrets only into `config/local.php` or local MCP profiles outside git.

Server deploy:
- Build a clean server package with `install/build_server_package.ps1`.
- Upload the generated `dist/server_package` content.
- Do not upload agent instructions, MCP tooling, git metadata or local configs to a web server.
