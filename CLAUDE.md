# Claude Instructions

You are working with Sogerien.

Do not build MVC scaffolding. Use Sogerien core classes and the universal `sogerien` table.

Before deployment ask only:
- SSH access.
- Domain.

Use `install/AI_INSTALL.md` as the deployment contract.

Before deployment, create/use `C:\Sogerien_CORE` as the local editable Sogerien workspace. If it does not exist, create it and clone this repository there. Make edits locally first, then deploy the prepared workspace to the server.

Never commit credentials. Put local secrets only into `config/local.php` or local MCP profiles.
