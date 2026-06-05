---
name: mcp
description: Universal MCP router for Sogerien tasks - routes to the right installed skill (backend, frontend, Figma, WinUI, security, Apps SDK, FTP deploy) with strict minimal-overhead workflow.
---

# MCP

## Purpose
Single entry skill that routes the task to the strongest installed domain skill with minimum overhead.

## Installed targets
1. `sogerien-genius-core` - PHP backend, API, roles, PostgreSQL JSONB, universal table model.
2. `frontend-skill` - visual frontend, landing/prototype quality, responsive polish.
3. `figma` - extract technical design context from Figma MCP.
4. `figma-implement-design` - 1:1 production implementation from Figma data.
5. `winui-app` - WinUI 3 desktop development.
6. `mcp-chatgpt-apps` - ChatGPT Apps SDK + MCP tool/resource contracts.
7. `mcp-security-threat-model` - repository-grounded threat modeling.
8. `playwright` - browser automation, screenshots, interaction QA.
9. `local-tools-ftp-sync` - mandatory deploy/sync via FTP/FTPS/SFTP.
10. `doc` - safe `.docx` read/edit with formatting preservation.

## Router logic
1. Detect dominant task domain.
2. Route to one primary target skill.
3. Add secondary skills only when task explicitly spans domains.
4. Keep path minimal - no parallel abstractions, no duplicate layers.

## Mixed-task order
1. `sogerien-genius-core` (if backend touched)
2. `figma` (if design source is Figma)
3. `figma-implement-design` (if strict 1:1 required)
4. `frontend-skill` (UI quality and responsiveness)
5. `winui-app` (desktop scope)
6. `mcp-chatgpt-apps` (Apps SDK/MCP app scope)
7. `mcp-security-threat-model` (security pass)
8. `playwright` (browser verification)
9. `local-tools-ftp-sync` (deploy in same task after local edits)

## Hard rules
- Reuse existing project code first.
- Extend universal classes before creating new entities.
- Prefer minimum code with maximum reuse.
- If local files changed and task requires server sync - route to `local-tools-ftp-sync` in the same task.
- For FTP tasks require config format `local_tools_ftp_multi_v1` and explicit `profile`; do not write flat root FTP fields.
- FTP deploy is non-interactive with automatic overwrite. Do not request confirmation from user.
- FTP upload mode is single-file only via `ftp_upload` with `local_path`.

## Output contract
- State chosen branch (primary skill).
- If mixed: list ordered skill chain.
- Show only concrete actions and blockers.
