from __future__ import annotations

import json
import os
import re
import sys
from dataclasses import dataclass
from datetime import datetime, timezone
from pathlib import Path
from typing import Any

SERVER_NAME = "mcp-php"
SERVER_VERSION = "1.0.0"
PROTOCOL_VERSION = "2025-03-26"
DEFAULT_CONFIG_PATH = (Path(__file__).resolve().parent / "php_konfig.json").resolve()


def now_iso() -> str:
    return datetime.now(timezone.utc).isoformat()


def to_int(value: Any, default: int, min_value: int = 0) -> int:
    try:
        n = int(value)
    except Exception:
        n = default
    return max(min_value, n)


def _is_escaped(text: str, pos: int) -> bool:
    slash_count = 0
    i = pos - 1
    while i >= 0 and text[i] == "\\":
        slash_count += 1
        i -= 1
    return (slash_count % 2) == 1


@dataclass
class PHPConfig:
    roots: list[str]
    max_file_bytes: int
    allowed_extensions: list[str]
    require_php_tag: bool

    @staticmethod
    def defaults() -> "PHPConfig":
        return PHPConfig(
            roots=[],
            max_file_bytes=2_000_000,
            allowed_extensions=[".php", ".phtml", ".inc"],
            require_php_tag=True,
        )

    def to_dict(self) -> dict[str, Any]:
        return {
            "roots": self.roots,
            "max_file_bytes": self.max_file_bytes,
            "allowed_extensions": self.allowed_extensions,
            "require_php_tag": self.require_php_tag,
        }

    @staticmethod
    def from_dict(raw: dict[str, Any]) -> "PHPConfig":
        roots_raw = raw.get("roots")
        roots: list[str] = []
        if isinstance(roots_raw, list):
            roots = [str(x).strip() for x in roots_raw if str(x).strip()]

        exts_raw = raw.get("allowed_extensions")
        exts: list[str] = []
        if isinstance(exts_raw, list):
            for item in exts_raw:
                ext = str(item).strip().lower()
                if not ext:
                    continue
                if not ext.startswith("."):
                    ext = f".{ext}"
                exts.append(ext)
        if not exts:
            exts = [".php", ".phtml", ".inc"]

        req_tag = bool(raw.get("require_php_tag", True))
        max_bytes = to_int(raw.get("max_file_bytes"), default=2_000_000, min_value=1_024)
        return PHPConfig(roots=roots, max_file_bytes=max_bytes, allowed_extensions=exts, require_php_tag=req_tag)


class ConfigStore:
    def __init__(self, path: Path):
        self.path = path

    def load(self) -> PHPConfig:
        cfg = PHPConfig.defaults()
        if self.path.is_file():
            try:
                data = json.loads(self.path.read_text(encoding="utf-8"))
                if isinstance(data, dict):
                    profiles = data.get("profiles")
                    if isinstance(profiles, dict) and profiles:
                        profile_name = str(data.get("default_profile") or "").strip()
                        profile = profiles.get(profile_name) if profile_name else None
                        if not isinstance(profile, dict):
                            profile = next((x for x in profiles.values() if isinstance(x, dict)), None)
                        if isinstance(profile, dict):
                            data = profile
                    cfg = PHPConfig.from_dict({**cfg.to_dict(), **data})
            except Exception:
                pass
        return cfg

    def save_patch(self, patch: dict[str, Any]) -> PHPConfig:
        cur = self.load().to_dict()
        for key, value in patch.items():
            if str(key).startswith("__"):
                continue
            cur[str(key)] = value
        nxt = PHPConfig.from_dict(cur)
        self.path.parent.mkdir(parents=True, exist_ok=True)
        self.path.write_text(json.dumps(nxt.to_dict(), ensure_ascii=False, indent=2), encoding="utf-8")
        return nxt


class PHPValidator:
    def __init__(self, cfg: PHPConfig):
        self.cfg = cfg

    def _resolve_file(self, file_path: str) -> Path:
        raw = str(file_path or "").strip()
        if not raw:
            raise RuntimeError("file_path_is_empty")

        p = Path(raw)
        candidates: list[Path] = []
        if p.is_absolute():
            candidates.append(p)
        else:
            cwd_candidate = (Path.cwd() / p).resolve()
            candidates.append(cwd_candidate)
            for root in self.cfg.roots:
                r = Path(root).expanduser()
                try:
                    candidates.append((r / p).resolve())
                except Exception:
                    continue

        for candidate in candidates:
            try:
                rp = candidate.resolve()
            except Exception:
                continue
            if rp.is_file():
                return rp
        raise RuntimeError("file_not_found")

    def _check_ext(self, path: Path) -> str:
        ext = path.suffix.lower()
        if ext not in self.cfg.allowed_extensions:
            return f"extension_not_allowed:{ext}"
        return ""

    def _scan(self, text: str) -> dict[str, Any]:
        issues: list[dict[str, Any]] = []
        warnings: list[str] = []

        if self.cfg.require_php_tag and "<?php" not in text and "<?=" not in text:
            issues.append({"severity": "error", "code": "php_open_tag_missing", "message": "Missing '<?php' or '<?= ' tag"})

        if re.search(r"<\?(?!php|=)", text, flags=re.IGNORECASE):
            warnings.append("short_open_tag_used")

        stack: list[str] = []
        in_s_quote = False
        in_d_quote = False
        in_line_comment = False
        in_block_comment = False
        heredoc_tag = ""
        line_no = 1
        i = 0
        n = len(text)

        while i < n:
            ch = text[i]
            nxt = text[i + 1] if i + 1 < n else ""

            if ch == "\n":
                line_no += 1
                in_line_comment = False

            if heredoc_tag:
                if ch == "\n":
                    start = i + 1
                    end = start
                    while end < n and text[end] != "\n":
                        end += 1
                    row = text[start:end].strip().rstrip(";")
                    if row == heredoc_tag:
                        heredoc_tag = ""
                i += 1
                continue

            if in_line_comment:
                i += 1
                continue

            if in_block_comment:
                if ch == "*" and nxt == "/":
                    in_block_comment = False
                    i += 2
                else:
                    i += 1
                continue

            if in_s_quote:
                if ch == "'" and not _is_escaped(text, i):
                    in_s_quote = False
                i += 1
                continue

            if in_d_quote:
                if ch == '"' and not _is_escaped(text, i):
                    in_d_quote = False
                i += 1
                continue

            if ch == "/" and nxt == "/":
                in_line_comment = True
                i += 2
                continue
            if ch == "#":
                in_line_comment = True
                i += 1
                continue
            if ch == "/" and nxt == "*":
                in_block_comment = True
                i += 2
                continue

            if text.startswith("<<<", i):
                m = re.match(r"<<<[ \t]*['\"]?([A-Za-z_][A-Za-z0-9_]*)['\"]?", text[i:])
                if m:
                    heredoc_tag = m.group(1)
                    i += m.end()
                    continue

            if ch == "'":
                in_s_quote = True
                i += 1
                continue
            if ch == '"':
                in_d_quote = True
                i += 1
                continue

            if ch in "([{":
                stack.append(ch)
            elif ch in ")]}":
                if not stack:
                    issues.append(
                        {
                            "severity": "error",
                            "code": "unmatched_closing_bracket",
                            "line": line_no,
                            "message": f"Unexpected closing bracket '{ch}'",
                        }
                    )
                else:
                    op = stack.pop()
                    pair = (op, ch)
                    if pair not in {("(", ")"), ("[", "]"), ("{", "}")}:
                        issues.append(
                            {
                                "severity": "error",
                                "code": "mismatched_brackets",
                                "line": line_no,
                                "message": f"Mismatched brackets '{op}' and '{ch}'",
                            }
                        )
            i += 1

        if stack:
            issues.append({"severity": "error", "code": "unclosed_brackets", "message": f"Unclosed bracket count: {len(stack)}"})
        if in_s_quote:
            issues.append({"severity": "error", "code": "unclosed_single_quote", "message": "Unclosed single quote"})
        if in_d_quote:
            issues.append({"severity": "error", "code": "unclosed_double_quote", "message": "Unclosed double quote"})
        if in_block_comment:
            issues.append({"severity": "error", "code": "unclosed_block_comment", "message": "Unclosed block comment"})
        if heredoc_tag:
            issues.append({"severity": "error", "code": "unclosed_heredoc", "message": f"Unclosed heredoc/nowdoc: {heredoc_tag}"})

        return {"issues": issues, "warnings": warnings}

    def validate_file(self, file_path: str) -> dict[str, Any]:
        try:
            path = self._resolve_file(file_path)
        except Exception as exc:
            return {"ok": False, "file": str(file_path), "error": str(exc)}

        ext_err = self._check_ext(path)
        if ext_err:
            return {"ok": False, "file": str(path), "error": ext_err}

        try:
            st = path.stat()
            if int(st.st_size) > int(self.cfg.max_file_bytes):
                return {
                    "ok": False,
                    "file": str(path),
                    "error": "file_too_large",
                    "max_file_bytes": int(self.cfg.max_file_bytes),
                    "size_bytes": int(st.st_size),
                }
            text = path.read_text(encoding="utf-8", errors="replace")
        except Exception as exc:
            return {"ok": False, "file": str(path), "error": f"read_failed:{exc}"}

        scan = self._scan(text)
        has_errors = any(str(x.get("severity")) == "error" for x in scan["issues"])
        return {
            "ok": not has_errors,
            "file": str(path),
            "size_bytes": len(text.encode("utf-8", errors="replace")),
            "issues": scan["issues"],
            "warnings": scan["warnings"],
            "engine": "static_no_php_binary",
        }

    def validate_many(self, paths: list[str]) -> dict[str, Any]:
        rows: list[dict[str, Any]] = []
        all_ok = True
        for idx, p in enumerate(paths, start=1):
            row = self.validate_file(str(p or ""))
            row["index"] = idx
            rows.append(row)
            if not bool(row.get("ok")):
                all_ok = False
        return {"ok": all_ok, "count": len(rows), "results": rows}


class MCPPHPServer:
    def __init__(self):
        cfg_path = Path(os.getenv("PHP_CONFIG_PATH", str(DEFAULT_CONFIG_PATH))).expanduser().resolve()
        self.store = ConfigStore(cfg_path)

    def tool_schemas(self) -> list[dict[str, Any]]:
        return [
            {
                "name": "php_config_show",
                "description": "Show active PHP validator config",
                "inputSchema": {"type": "object", "properties": {}},
            },
            {
                "name": "php_config_save",
                "description": "Save partial PHP validator config into php_konfig.json",
                "inputSchema": {
                    "type": "object",
                    "properties": {"patch": {"type": "object", "additionalProperties": True}},
                    "required": ["patch"],
                },
            },
            {
                "name": "php_validate_file",
                "description": "Validate one PHP file (static checks, no php binary required)",
                "inputSchema": {
                    "type": "object",
                    "properties": {"file_path": {"type": "string"}},
                    "required": ["file_path"],
                },
            },
            {
                "name": "php_validate_many",
                "description": "Validate many PHP files",
                "inputSchema": {
                    "type": "object",
                    "properties": {"file_paths": {"type": "array", "items": {"type": "string"}}},
                    "required": ["file_paths"],
                },
            },
        ]

    def call_tool(self, name: str, args: dict[str, Any]) -> dict[str, Any]:
        cfg = self.store.load()

        if name == "php_config_show":
            return {"ok": True, "server": SERVER_NAME, "config_path": str(self.store.path), "config": cfg.to_dict()}

        if name == "php_config_save":
            patch = args.get("patch")
            if not isinstance(patch, dict):
                raise RuntimeError("patch_must_be_object")
            saved = self.store.save_patch(patch)
            return {"ok": True, "server": SERVER_NAME, "config_path": str(self.store.path), "config": saved.to_dict()}

        validator = PHPValidator(cfg)

        if name == "php_validate_file":
            file_path = str(args.get("file_path") or "").strip()
            if not file_path:
                return {"ok": False, "server": SERVER_NAME, "error": "file_path_is_empty"}
            out = validator.validate_file(file_path)
            return {"server": SERVER_NAME, **out}

        if name == "php_validate_many":
            file_paths = args.get("file_paths")
            if not isinstance(file_paths, list):
                return {"ok": False, "server": SERVER_NAME, "error": "file_paths_must_be_array"}
            out = validator.validate_many([str(x or "") for x in file_paths])
            return {"server": SERVER_NAME, **out}

        raise RuntimeError(f"unknown_tool:{name}")


def write_message(payload: dict[str, Any]) -> None:
    sys.stdout.write(json.dumps(payload, ensure_ascii=False) + "\n")
    sys.stdout.flush()


def success_result(req_id: Any, result: dict[str, Any]) -> None:
    write_message({"jsonrpc": "2.0", "id": req_id, "result": result})


def error_result(req_id: Any, code: int, message: str) -> None:
    write_message({"jsonrpc": "2.0", "id": req_id, "error": {"code": code, "message": message}})


def tool_call_result(req_id: Any, data: dict[str, Any]) -> None:
    text = json.dumps(data, ensure_ascii=False)
    write_message(
        {
            "jsonrpc": "2.0",
            "id": req_id,
            "result": {"content": [{"type": "text", "text": text}], "isError": not bool(data.get("ok"))},
        }
    )


def run_stdio_server() -> int:
    server = MCPPHPServer()
    for raw in sys.stdin:
        line = raw.strip()
        if not line:
            continue
        try:
            msg = json.loads(line)
        except Exception:
            continue

        method = str(msg.get("method") or "")
        req_id = msg.get("id")
        params = msg.get("params") or {}

        if method == "notifications/initialized":
            continue

        if method == "initialize":
            success_result(
                req_id,
                {
                    "protocolVersion": PROTOCOL_VERSION,
                    "serverInfo": {"name": SERVER_NAME, "version": SERVER_VERSION},
                    "capabilities": {"tools": {}},
                },
            )
            continue

        if method == "ping":
            success_result(req_id, {"ts": now_iso()})
            continue

        if method == "tools/list":
            success_result(req_id, {"tools": server.tool_schemas()})
            continue

        if method == "tools/call":
            try:
                tool_name = str(params.get("name") or "")
                tool_args = params.get("arguments") or {}
                if not isinstance(tool_args, dict):
                    raise RuntimeError("arguments_must_be_object")
                out = server.call_tool(tool_name, tool_args)
                tool_call_result(req_id, out)
            except Exception as exc:
                tool_call_result(req_id, {"ok": False, "server": SERVER_NAME, "error": str(exc)})
            continue

        if req_id is not None:
            error_result(req_id, -32601, f"method_not_found:{method}")
    return 0


if __name__ == "__main__":
    raise SystemExit(run_stdio_server())
