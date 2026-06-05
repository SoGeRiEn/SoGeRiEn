# Widget UI Patterns

## State Shape

Use one flat state container with explicit sections:

- `status`: `"idle" | "loading" | "success" | "error"`
- `data`: normalized payload for rendering
- `error`: nullable object with `code` and `message`
- `meta`: optional pagination/sort/filter info

## Rendering Rules

- Never render directly from raw tool payload.
- Map raw payload to a view model first.
- Keep components dumb: receive props, emit events.

## Interaction Rules

- Disable repeated submit while `loading`.
- Support retry from error state.
- Preserve previous successful data during a refresh when useful.

## Error Rules

- Show user-friendly message in UI.
- Keep technical `code` in debug log panel if present.
- Handle missing fields defensively with safe defaults.

## Performance Rules

- Debounce high-frequency widget inputs.
- Paginate long lists at tool level, not only in UI.
- Avoid client-only heavy data shaping for large payloads.

## Accessibility Basics

- Label all inputs and controls.
- Keep keyboard navigation usable.
- Announce loading and error states for assistive technologies.
