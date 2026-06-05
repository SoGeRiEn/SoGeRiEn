---
name: mcp-security-threat-model
description: Build repository-grounded threat models for MCP servers and toolchains. Use when asked to analyze repository security, map trust boundaries, enumerate attack scenarios, assess risk, or produce a mitigation backlog for MCP handlers, orchestrators, configs, and runtime integrations.
---

# MCP Security Threat Model

## Overview

Build a practical threat model from actual repository code and configuration.
Prioritize exploit paths and return a mitigation plan that engineering can implement immediately.

## Workflow

1. Scope and inventory
- Confirm target repository path and deployment context (local-only, shared host, production).
- Enumerate high-risk entry points first: MCP server bootstrap, tool registration, config files, subprocess/exec points, network clients, filesystem write paths.
- Inventory sensitive assets: credentials, tokens, private keys, DB access, email access, remote shell access.

2. Trust boundaries and data flows
- Draw boundaries between: MCP client, MCP server process, local OS/filesystem, remote services (SSH/FTP/DB/HTTP/SMTP), and logs/state storage.
- Trace untrusted inputs to privileged sinks (command execution, SQL execution, file writes, outbound requests, proxy interception).
- Mark each hop as authenticated/unauthenticated and encrypted/unencrypted.

3. Threat enumeration
- Use STRIDE + abuse-cases for each trust boundary.
- For each threat record:
  - `entry_point`
  - `preconditions`
  - `attack_path` (step-by-step)
  - `impact`
  - `likelihood`
  - `existing_controls`
  - `gaps`
- Flag secrets-in-repo as an active incident, not a theoretical risk.

4. Attack scenario construction
- Build at least 8 concrete scenarios from repository reality.
- Include kill chain stages: initial access -> execution -> persistence/lateral movement -> impact.
- Prefer scenarios with compound effects (e.g., credential leak -> remote exec -> data exfiltration).
- Use [references/attack-scenarios.md](references/attack-scenarios.md) as a baseline catalog and adapt to observed code.

5. Risk prioritization
- Score each scenario: `risk = impact(1-5) * likelihood(1-5)`.
- Convert to priorities:
  - `P0`: 20-25
  - `P1`: 12-19
  - `P2`: 6-11
  - `P3`: 1-5
- Break ties by exploit simplicity and blast radius.

6. Mitigation backlog
- For each `P0/P1`, provide:
  - immediate containment
  - engineering fix
  - verification test
  - owner role (platform/backend/devops/security)
- Focus on minimal, high-leverage controls first: least privilege, secret rotation, allowlists, strict host verification, output redaction, audit logging.

## Required Output Format

Return the result in this structure:

1. `System Summary`
2. `Assets and Trust Boundaries`
3. `Threats (Table)`
4. `Attack Scenarios (Top N)`
5. `Prioritized Mitigations`
6. `Residual Risk and Assumptions`

For every confirmed finding include file evidence as absolute paths with line numbers.
Clearly label inferred risks as `inference` when direct evidence is unavailable.

## Repository-First Rules

- Never claim a vulnerability without repository evidence or an explicit inference label.
- Prefer `rg` for code search and focus on privileged operations first.
- Identify privilege boundaries before proposing controls.
- Avoid generic recommendations with no implementation owner.
- Keep recommendations deployable in small iterations.

## Quick Checks

Run these checks early:
- secret storage in config/json/env and accidental commit
- dangerous defaults (host key checking off, SSL verify off, permissive auth)
- remote command/query execution exposure
- SSRF/open proxy behavior
- path traversal and uncontrolled file writes
- data exfiltration via logs/events/snapshots
- process orchestration abuse (arbitrary command/cwd/env)

Load [references/attack-scenarios.md](references/attack-scenarios.md) when building scenarios and mitigation backlog.
