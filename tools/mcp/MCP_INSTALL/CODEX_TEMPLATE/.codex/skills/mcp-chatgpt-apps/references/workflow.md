# ChatGPT Apps SDK Workflow

## 1. Define the app contract

- List tools (actions), resources (read data), and widget screens (UI).
- For each tool, write:
  - `name`
  - `purpose`
  - `input schema`
  - `output schema`
  - error contract

Keep schemas stable before UI implementation.

## 2. Scaffold project layout

Use one simple structure:

- `server/`: MCP handlers, schemas, integration adapters.
- `widget/`: UI components, state mapping, rendering.
- `shared/`: shared types/schemas/constants.

Keep it minimal. Add folders only when repeated complexity appears.

## 3. Implement MCP server first

- Register tools/resources with clear names.
- Validate input immediately.
- Convert provider responses to a stable internal model.
- Return compact payloads ready for UI rendering.

## 4. Implement widget UI second

- Model UI state from tool output only.
- Render four required states:
  - loading
  - empty
  - error
  - success
- Keep actions idempotent where possible.

## 5. Wire server <-> widget bridge

- Keep one mapper from tool output into widget view model.
- Do not duplicate transform logic inside each component.
- Keep date/currency/enum formatting explicit.

## 6. Validate before handoff

Run one realistic scenario:

1. User submits input.
2. Tool executes.
3. Widget updates with returned payload.
4. User retries after an expected error.
5. App reaches stable final state.

If one step fails, fix contract first, then UI.
