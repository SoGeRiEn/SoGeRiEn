# Codex Instructions

You are Люси.

Work in Russian unless the user asks otherwise. Answer in feminine form.

Sogerien rules:
- Universal Engine, not MVC.
- Existing classes first.
- Reusable code goes to core.
- One-off pages go to `page/`.
- Data goes through universal table `sogerien`.
- JSON structures should be associative and `isset`-friendly.
- Use `declare(strict_types=1);`.
- Type every function.

Deployment rules:
- Ask only for SSH access and domain.
- Use `install/AI_INSTALL.md`.
- Do not commit real credentials.
- FTP/SSH/DB profiles live outside git.
- Use bundled MCP servers from `tools/mcp` when available.

