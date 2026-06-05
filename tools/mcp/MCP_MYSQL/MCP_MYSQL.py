from __future__ import annotations

import json
import os
import socket
import sys
from dataclasses import dataclass
from datetime import date, datetime, time, timezone
from decimal import Decimal
from pathlib import Path
from typing import Any

try:
    import pymysql
except Exception:
    pymysql = None

SERVER_NAME = "mcp-mysql"
SERVER_VERSION = "1.0.0"
PROTOCOL_VERSION = "2025-03-26"
DEFAULT_CONFIG_PATH = (Path(__file__).resolve().parent / "mysql_konfig.json").resolve()


def now_iso() -> str:
    return datetime.now(timezone.utc).isoformat()


def mask_config(cfg: dict[str, Any]) -> dict[str, Any]:
    safe = dict(cfg)
    if safe.get("password"):
        safe["password"] = "***"
    return safe


def to_json_safe(value: Any) -> Any:
    if value is None:
        return None
    if isinstance(value, (str, int, float, bool)):
        return value
    if isinstance(value, Decimal):
        return str(value)
    if isinstance(value, (datetime, date, time)):
        return value.isoformat()
    if isinstance(value, bytes):
        return value.hex()
    if isinstance(value, dict):
        out: dict[str, Any] = {}
        for k, v in value.items():
            out[str(k)] = to_json_safe(v)
        return out
    if isinstance(value, (list, tuple, set)):
        return [to_json_safe(x) for x in value]
    return str(value)


@dataclass
class MySQLConfig:
    host: str
    port: int
    database: str
    username: str
    password: str
    charset: str
    connect_timeout_sec: int

    @staticmethod
    def defaults() -> "MySQLConfig":
        return MySQLConfig(
            host="",
            port=3306,
            database="",
            username="",
            password="",
            charset="utf8mb4",
            connect_timeout_sec=10,
        )

    def to_dict(self) -> dict[str, Any]:
        return {
            "host": self.host,
            "port": self.port,
            "database": self.database,
            "username": self.username,
            "password": self.password,
            "charset": self.charset,
            "connect_timeout_sec": self.connect_timeout_sec,
        }

    @staticmethod
    def from_dict(raw: dict[str, Any]) -> "MySQLConfig":
        return MySQLConfig(
            host=str(raw.get("host") or "").strip(),
            port=max(1, int(raw.get("port") or 3306)),
            database=str(raw.get("database") or raw.get("dbname") or "").strip(),
            username=str(raw.get("username") or raw.get("user") or "").strip(),
            password=str(raw.get("password") or ""),
            charset=str(raw.get("charset") or "utf8mb4").strip() or "utf8mb4",
            connect_timeout_sec=max(1, int(raw.get("connect_timeout_sec") or raw.get("timeout") or 10)),
        )


class ConfigStore:
    def __init__(self, path: Path):
        self.path = path

    def load(self) -> MySQLConfig:
        cfg = MySQLConfig.defaults()
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
                    cfg = MySQLConfig.from_dict({**cfg.to_dict(), **data})
            except Exception:
                pass
        return cfg

    def save_patch(self, patch: dict[str, Any]) -> MySQLConfig:
        cur = self.load().to_dict()
        for key, value in patch.items():
            if str(key).startswith("__"):
                continue
            cur[str(key)] = value
        nxt = MySQLConfig.from_dict(cur)
        self.path.parent.mkdir(parents=True, exist_ok=True)
        self.path.write_text(json.dumps(nxt.to_dict(), ensure_ascii=False, indent=2), encoding="utf-8")
        return nxt


class MySQLClient:
    def __init__(self, cfg: MySQLConfig):
        self.cfg = cfg

    def validate_auth(self) -> str:
        if not self.cfg.host:
            return "host_missing"
        if not self.cfg.database:
            return "database_missing"
        if not self.cfg.username:
            return "username_missing"
        return ""

    def _conn_kwargs(self) -> dict[str, Any]:
        return {
            "host": self.cfg.host,
            "port": int(self.cfg.port),
            "user": self.cfg.username,
            "password": self.cfg.password,
            "database": self.cfg.database,
            "charset": self.cfg.charset,
            "connect_timeout": int(self.cfg.connect_timeout_sec),
            "read_timeout": int(self.cfg.connect_timeout_sec),
            "write_timeout": int(self.cfg.connect_timeout_sec),
            "autocommit": True,
            "cursorclass": pymysql.cursors.DictCursor if pymysql is not None else None,
        }

    def connect_test(self) -> dict[str, Any]:
        if pymysql is None:
            return {"ok": False, "error": "pymysql_not_installed"}
        kwargs = self._conn_kwargs()
        kwargs.pop("cursorclass", None)
        try:
            conn = pymysql.connect(**kwargs, cursorclass=pymysql.cursors.DictCursor)
            with conn.cursor() as cur:
                cur.execute("select version() as version, database() as database_name, user() as username")
                row = cur.fetchone() or {}
            conn.close()
            out = to_json_safe(row)
            if not isinstance(out, dict):
                out = {}
            return {"ok": True, **out}
        except Exception as exc:
            return {"ok": False, "error": str(exc)}

    def query(self, sql: str, params: list[Any]) -> dict[str, Any]:
        if pymysql is None:
            return {"ok": False, "error": "pymysql_not_installed"}
        kwargs = self._conn_kwargs()
        kwargs.pop("cursorclass", None)
        try:
            conn = pymysql.connect(**kwargs, cursorclass=pymysql.cursors.DictCursor)
            with conn.cursor() as cur:
                cur.execute(sql, params)
                if cur.description is None:
                    affected = int(cur.rowcount) if isinstance(cur.rowcount, int) else 0
                    conn.close()
                    return {"ok": True, "rows": [], "count": 0, "affected_rows": max(0, affected)}
                rows = [to_json_safe(dict(row)) for row in cur.fetchall()]
                affected = int(cur.rowcount) if isinstance(cur.rowcount, int) else len(rows)
            conn.close()
            return {"ok": True, "rows": rows, "count": len(rows), "affected_rows": max(0, affected)}
        except Exception as exc:
            return {"ok": False, "error": str(exc)}


class MCPMySQLServer:
    def __init__(self):
        cfg_path = Path(os.getenv("MYSQL_CONFIG_PATH", str(DEFAULT_CONFIG_PATH))).expanduser().resolve()
        self.store = ConfigStore(cfg_path)

    def tool_schemas(self) -> list[dict[str, Any]]:
        return [
            {
                "name": "mysql_config_show",
                "description": "Show active MySQL config with masked password",
                "inputSchema": {"type": "object", "properties": {}},
            },
            {
                "name": "mysql_config_save",
                "description": "Save partial MySQL config patch into mysql_konfig.json",
                "inputSchema": {
                    "type": "object",
                    "properties": {"patch": {"type": "object", "additionalProperties": True}},
                    "required": ["patch"],
                },
            },
            {
                "name": "mysql_connect_test",
                "description": "Connect to configured MySQL and run lightweight test query",
                "inputSchema": {"type": "object", "properties": {}},
            },
            {
                "name": "mysql_query",
                "description": "Execute SQL query in configured MySQL",
                "inputSchema": {
                    "type": "object",
                    "properties": {
                        "sql": {"type": "string"},
                        "params": {"type": "array", "items": {}},
                    },
                    "required": ["sql"],
                },
            },
        ]

    def call_tool(self, name: str, args: dict[str, Any]) -> dict[str, Any]:
        cfg = self.store.load()

        if name == "mysql_config_show":
            return {"ok": True, "server": SERVER_NAME, "config_path": str(self.store.path), "config": mask_config(cfg.to_dict())}

        if name == "mysql_config_save":
            patch = args.get("patch")
            if not isinstance(patch, dict):
                raise RuntimeError("patch_must_be_object")
            saved = self.store.save_patch(patch)
            return {"ok": True, "server": SERVER_NAME, "config_path": str(self.store.path), "config": mask_config(saved.to_dict())}

        cli = MySQLClient(cfg)
        auth_err = cli.validate_auth()
        if auth_err:
            return {"ok": False, "server": SERVER_NAME, "error": auth_err}

        if name == "mysql_connect_test":
            out = cli.connect_test()
            return {"server": SERVER_NAME, **out}

        if name == "mysql_query":
            sql = str(args.get("sql") or "").strip()
            if not sql:
                return {"ok": False, "server": SERVER_NAME, "error": "sql_is_empty"}
            params = args.get("params") or []
            if not isinstance(params, list):
                return {"ok": False, "server": SERVER_NAME, "error": "params_must_be_array"}
            out = cli.query(sql, params)
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
    server = MCPMySQLServer()
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
