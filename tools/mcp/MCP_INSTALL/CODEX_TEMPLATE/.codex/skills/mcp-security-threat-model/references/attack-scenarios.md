# MCP Attack Scenarios

Use this catalog as a starting point. Keep only scenarios supported by repository evidence.

## Scenario Template

For each scenario, capture:
- `id`
- `title`
- `entry_point`
- `preconditions`
- `attack_path`
- `impact`
- `likelihood` (1-5)
- `impact_score` (1-5)
- `risk_score`
- `detection`
- `mitigation`

## Baseline Scenarios for MCP Tooling

1. Config secret leakage -> infrastructure takeover
- Entry: committed `*_konfig.json` with plaintext credentials.
- Path: attacker reads repo/backups -> reuses FTP/SSH/DB creds -> remote access.
- Impact: full compromise of remote systems and data.
- Mitigation: remove secrets from VCS, rotate credentials, use secret manager.

2. Prompt/tool abuse -> remote command execution
- Entry: exposed tool capable of shell or SSH command execution.
- Path: crafted instruction triggers dangerous command on remote host.
- Impact: destructive command execution, persistence, lateral movement.
- Mitigation: command allowlists, high-risk command denylist, approval gates.

3. MITM proxy misuse -> credential/session interception
- Entry: proxy tooling with permissive rules and event capture.
- Path: traffic interception logs headers/bodies with auth tokens.
- Impact: account takeover, data exfiltration.
- Mitigation: strict scope constraints, redact secrets in events, disable for prod traffic.

4. SSRF via generic HTTP/WebSocket tool
- Entry: unrestricted URL input in outbound request tool.
- Path: attacker targets metadata/internal services via server network access.
- Impact: internal data leak, pivot to internal systems.
- Mitigation: URL/IP allowlists, block private ranges, egress policy.

5. Unsafe host verification defaults in SSH
- Entry: host key checking disabled by default.
- Path: active MITM presents fake host key, captures credentials/commands.
- Impact: credential theft and command hijacking.
- Mitigation: strict host key checking default, managed known_hosts.

6. Arbitrary SQL execution in privileged DB profile
- Entry: free-form query tool with write-capable credentials.
- Path: attacker runs destructive/exfiltration SQL.
- Impact: data loss, integrity compromise, sensitive data leak.
- Mitigation: readonly role by default, query class restrictions, audit logs.

7. FTP sync overwrite/destructive deployment
- Entry: broad sync/upload to remote root.
- Path: malicious or mistaken local changes overwrite critical remote files.
- Impact: outage, web shell upload, integrity loss.
- Mitigation: safe target roots, path guards, dry-run + approval for critical paths.

8. Orchestrator config poisoning -> arbitrary process execution
- Entry: writable orchestrator config with command/cwd/env fields.
- Path: attacker modifies config -> orchestrator starts attacker-controlled command.
- Impact: local privilege abuse and persistence.
- Mitigation: config file ACL hardening, signed configs, startup integrity checks.

9. Sensitive data in logs/events/snapshots
- Entry: verbose logging and captured payloads.
- Path: secrets appear in logs, then copied to artifacts/backups.
- Impact: delayed but broad credential leakage.
- Mitigation: structured redaction, log retention limits, scoped read access.

10. Overly permissive tool dispatch
- Entry: generic dispatcher calling any registered tool by name.
- Path: attacker invokes high-impact tools not intended for that context.
- Impact: privilege escalation across tool boundaries.
- Mitigation: per-tool authorization policy, context-aware allowlist, audit trail.

11. Dependency/runtime poisoning
- Entry: dynamic imports from local paths and external binaries.
- Path: swapped module/binary executes attacker logic at runtime.
- Impact: full compromise of MCP process.
- Mitigation: hash pinning, trusted paths only, integrity verification on startup.

12. Denial of service via heavy commands/large outputs
- Entry: expensive remote commands, large response bodies, high tail values.
- Path: repeated calls exhaust CPU/memory/disk/network.
- Impact: MCP service instability or crash.
- Mitigation: strict rate limits, quotas, output caps, timeout ceilings.

## Prioritization Guidance

Prioritize first:
- Any scenario with confirmed credential exposure.
- Any path reaching remote command execution with low attacker effort.
- Any path allowing data exfiltration from production stores.

Use `risk_score = likelihood * impact_score`.
Map to priority:
- `P0`: 20-25
- `P1`: 12-19
- `P2`: 6-11
- `P3`: 1-5
