from __future__ import annotations

import asyncio
import json
import os
import socket
import sys
from dataclasses import dataclass
from datetime import datetime, timezone
from pathlib import Path
from typing import Any

try:
    import aiohttp
except Exception:
    aiohttp = None

SERVER_NAME = "mcp-http"
SERVER_VERSION = "1.0.0"
PROTOCOL_VERSION = "2025-03-26"
DEFAULT_CONFIG_PATH = (Path(__file__).resolve().parent / "http_konfig.json").resolve()


def now_iso() -> str:
    return datetime.now(timezone.utc).isoformat()


@dataclass
class HTTPConfig:
    ping_url: str
    timeout_sec: int
    follow_redirects: bool

    @staticmethod
    def defaults() -> "HTTPConfig":
        return HTTPConfig(
            ping_url="https://example.com",
            timeout_sec=10,
            follow_redirects=True,
        )

    @staticmethod
    def _to_bool(value: Any, fallback: bool) -> bool:
        if isinstance(value, bool):
            return value
        if value is None:
            return fallback
        return str(value).strip().lower() not in {"0", "false", "no", "off"}

    @staticmethod
    def from_dict(raw: dict[str, Any]) -> "HTTPConfig":
        return HTTPConfig(
            ping_url=str(raw.get("ping_url") or "https://example.com").strip() or "https://example.com",
            timeout_sec=max(1, int(raw.get("timeout_sec") or 10)),
            follow_redirects=HTTPConfig._to_bool(raw.get("follow_redirects"), True),
        )

    def to_dict(self) -> dict[str, Any]:
        return {
            "ping_url": self.ping_url,
            "timeout_sec": self.timeout_sec,
            "follow_redirects": self.follow_redirects,
        }


class ConfigStore:
    def __init__(self, path: Path):
        self.path = path

    def load(self) -> HTTPConfig:
        cfg = HTTPConfig.defaults()
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
                    cfg = HTTPConfig.from_dict({**cfg.to_dict(), **data})
            except Exception:
                pass
        return cfg

    def save_patch(self, patch: dict[str, Any]) -> HTTPConfig:
        cur = self.load().to_dict()
        for key, value in patch.items():
            if str(key).startswith("__"):
                continue
            cur[str(key)] = value
        nxt = HTTPConfig.from_dict(cur)
        self.path.parent.mkdir(parents=True, exist_ok=True)
        self.path.write_text(json.dumps(nxt.to_dict(), ensure_ascii=False, indent=2), encoding="utf-8")
        return nxt


class HTTPClient:
    def __init__(self, cfg: HTTPConfig):
        self.cfg = cfg

    async def _request_async(
        self,
        method: str,
        url: str,
        headers: dict[str, str],
        body: str | None,
        timeout_sec: int,
        follow_redirects: bool,
    ) -> dict[str, Any]:
        if aiohttp is None:
            return {"ok": False, "error": "aiohttp_not_installed"}
        timeout = aiohttp.ClientTimeout(total=max(1, int(timeout_sec)))
        async with aiohttp.ClientSession(timeout=timeout) as session:
            async with session.request(
                method=method,
                url=url,
                headers=headers,
                data=body,
                allow_redirects=follow_redirects,
            ) as resp:
                text = await resp.text()
                return {
                    "ok": True,
                    "status": int(resp.status),
                    "headers": dict(resp.headers),
                    "body": text,
                    "url": str(resp.url),
                }

    def request(
        self,
        method: str,
        url: str,
        headers: dict[str, str] | None,
        body: str | None,
        timeout_sec: int | None,
        follow_redirects: bool | None,
    ) -> dict[str, Any]:
        clean_method = str(method or "GET").strip().upper() or "GET"
        clean_url = str(url or "").strip()
        if not clean_url:
            return {"ok": False, "error": "url_required"}
        clean_headers: dict[str, str] = {}
        for key, value in dict(headers or {}).items():
            clean_headers[str(key)] = str(value)
        clean_timeout = max(1, int(timeout_sec or self.cfg.timeout_sec or 10))
        redirects = self.cfg.follow_redirects if follow_redirects is None else bool(follow_redirects)
        try:
            return asyncio.run(
                self._request_async(
                    method=clean_method,
                    url=clean_url,
                    headers=clean_headers,
                    body=None if body is None else str(body),
                    timeout_sec=clean_timeout,
                    follow_redirects=redirects,
                )
            )
        except Exception as exc:
            return {"ok": False, "error": str(exc)}


class MCPHTTPServer:
    def __init__(self):
        cfg_path = Path(os.getenv("HTTP_CONFIG_PATH", str(DEFAULT_CONFIG_PATH))).expanduser().resolve()
        self.store = ConfigStore(cfg_path)

    def tool_schemas(self) -> list[dict[str, Any]]:
        return [
            {
                "name": "http_config_show",
                "description": "Show active HTTP config",
                "inputSchema": {"type": "object", "properties": {}},
            },
            {
                "name": "http_config_save",
                "description": "Save partial HTTP config patch into http_konfig.json",
                "inputSchema": {
                    "type": "object",
                    "properties": {"patch": {"type": "object", "additionalProperties": True}},
                    "required": ["patch"],
                },
            },
            {
                "name": "http_connect_test",
                "description": "Run GET request against configured ping_url",
                "inputSchema": {"type": "object", "properties": {}},
            },
            {
                "name": "http_request",
                "description": "Execute one HTTP request",
                "inputSchema": {
                    "type": "object",
                    "properties": {
                        "method": {"type": "string"},
                        "url": {"type": "string"},
                        "headers": {"type": "object", "additionalProperties": {"type": "string"}},
                        "body": {"type": "string"},
                        "timeout_sec": {"type": "integer", "minimum": 1},
                        "follow_redirects": {"type": "boolean"},
                    },
                    "required": ["url"],
                },
            },
        ]

    def call_tool(self, name: str, args: dict[str, Any]) -> dict[str, Any]:
        cfg = self.store.load()
        cli = HTTPClient(cfg)

        if name == "http_config_show":
            return {
                "ok": True,
                "server": SERVER_NAME,
                "config_path": str(self.store.path),
                "config": cfg.to_dict(),
            }

        if name == "http_config_save":
            patch = args.get("patch")
            if not isinstance(patch, dict):
                raise RuntimeError("patch_must_be_object")
            saved = self.store.save_patch(patch)
            return {
                "ok": True,
                "server": SERVER_NAME,
                "config_path": str(self.store.path),
                "config": saved.to_dict(),
            }

        if name == "http_connect_test":
            out = cli.request(
                method="GET",
                url=cfg.ping_url,
                headers={},
                body=None,
                timeout_sec=cfg.timeout_sec,
                follow_redirects=cfg.follow_redirects,
            )
            return {"server": SERVER_NAME, **out}

        if name == "http_request":
            headers = args.get("headers") or {}
            if not isinstance(headers, dict):
                return {"ok": False, "server": SERVER_NAME, "error": "headers_must_be_object"}
            out = cli.request(
                method=str(args.get("method") or "GET"),
                url=str(args.get("url") or ""),
                headers={str(k): str(v) for k, v in headers.items()},
                body=None if args.get("body") is None else str(args.get("body")),
                timeout_sec=int(args.get("timeout_sec")) if args.get("timeout_sec") is not None else None,
                follow_redirects=bool(args.get("follow_redirects")) if args.get("follow_redirects") is not None else None,
            )
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
    server = MCPHTTPServer()
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
                args = params.get("arguments") or {}
                if not isinstance(args, dict):
                    raise RuntimeError("arguments_must_be_object")
                out = server.call_tool(tool_name, args)
                tool_call_result(req_id, out)
            except Exception as exc:
                tool_call_result(req_id, {"ok": False, "server": SERVER_NAME, "error": str(exc)})
            continue

        if req_id is not None:
            error_result(req_id, -32601, f"method_not_found:{method}")

    return 0


if __name__ == "__main__":
    socket.setdefaulttimeout(20)
    raise SystemExit(run_stdio_server())
