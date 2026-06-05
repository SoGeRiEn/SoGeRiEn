---
name: mcp-chatgpt-apps
description: Build ChatGPT Apps SDK applications with MCP tools and widget UI. Use when a user asks to create, scaffold, refactor, debug, or productionize an Apps SDK app with MCP server handlers, tool schemas, resources, and interactive widget interfaces.
---

# mcp-chatgpt-apps

Implement ChatGPT Apps SDK solutions as a single coherent app: MCP server contracts plus widget UI.

## Workflow

1. Define app contract before coding.
2. Scaffold minimal project structure.
3. Implement MCP server tools/resources first.
4. Implement widget UI with deterministic data contract.
5. Connect tool results to widget state.
6. Validate end-to-end flow with a smoke scenario.

Read [references/workflow.md](references/workflow.md) for the full step-by-step build flow.

## Contract Rules

- Define every tool input/output with explicit schema and stable field names.
- Keep tool payloads compact and serializable.
- Return machine-readable errors (`code`, `message`, optional `details`).
- Keep UI state normalized and map directly from tool output.
- Add loading, empty, error, and success states for every widget.

## Implementation Rules

- Build one reusable server core; avoid duplicated handlers.
- Prefer small universal helpers over many feature-specific classes.
- Keep business logic on the server side; keep widgets focused on rendering and interactions.
- Keep adapters thin when integrating external APIs.
- Add inline comments only for non-obvious protocol or transformation logic.

## Debug Checklist

1. Verify MCP tool registration names and schema match widget calls.
2. Verify tool output fields exactly match widget expectations.
3. Verify widget handles partial/missing optional fields safely.
4. Verify serialization for dates, enums, and nested maps.
5. Verify app works with at least one realistic user flow from start to finish.

For UI-specific patterns, read [references/widget-ui-patterns.md](references/widget-ui-patterns.md).
