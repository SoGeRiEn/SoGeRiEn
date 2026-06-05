from __future__ import annotations

import json
import os
import re
import shlex
import socket
import subprocess
import sys
from dataclasses import dataclass
from datetime import datetime, timezone
from pathlib import Path
from typing import Any

try:
    import paramiko  # type: ignore
except Exception:
    paramiko = None

SERVER_NAME = "mcp-ssh"
SERVER_VERSION = "2.2.0"
PROTOCOL_VERSION = "2025-03-26"
CONFIG_FORMAT = "local_tools_ssh_multi_v1"
DEFAULT_CONFIG_PATH = (Path(__file__).resolve().parent / "ssh_konfig.json").resolve()


def now_iso() -> str:
    return datetime.now(timezone.utc).isoformat()


def to_bool(value: Any, default: bool = False) -> bool:
    if isinstance(value, bool):
        return value
    if isinstance(value, str):
        return value.strip().lower() in {"1", "true", "yes", "on"}
    if value is None:
        return default
    return bool(value)


def mask_config(cfg: dict[str, Any]) -> dict[str, Any]:
    safe = dict(cfg)
    if safe.get("password"):
        safe["password"] = "***"
    return safe


@dataclass
class SSHConfig:
    host: str
    port: int
    username: str
    password: str
    key_path: str
    known_hosts_path: str
    strict_host_key_checking: bool
    connect_timeout_sec: int
    command_timeout_sec: int
    allow_agent: bool
    look_for_keys: bool
    backend: str

    @staticmethod
    def defaults() -> "SSHConfig":
        return SSHConfig(
            host="",
            port=22,
            username="",
            password="",
            key_path="",
            known_hosts_path="",
            strict_host_key_checking=False,
            connect_timeout_sec=10,
            command_timeout_sec=0,
            allow_agent=False,
            look_for_keys=False,
            backend="",
        )

    def to_dict(self) -> dict[str, Any]:
        return {
            "host": self.host,
            "port": self.port,
            "username": self.username,
            "password": self.password,
            "key_path": self.key_path,
            "known_hosts_path": self.known_hosts_path,
            "strict_host_key_checking": self.strict_host_key_checking,
            "connect_timeout_sec": self.connect_timeout_sec,
            "command_timeout_sec": self.command_timeout_sec,
            "allow_agent": self.allow_agent,
            "look_for_keys": self.look_for_keys,
            "backend": self.backend,
        }

    @staticmethod
    def from_dict(raw: dict[str, Any]) -> "SSHConfig":
        backend = str(raw.get("backend") or "").strip().lower()
        if backend not in {"", "openssh", "paramiko"}:
            raise ValueError(f"unsupported_backend:{backend}")
        return SSHConfig(
            host=str(raw.get("host") or "").strip(),
            port=max(1, int(raw.get("port") or 22)),
            username=str(raw.get("username") or "").strip(),
            password=str(raw.get("password") or ""),
            key_path=str(raw.get("key_path") or "").strip(),
            known_hosts_path=str(raw.get("known_hosts_path") or "").strip(),
            strict_host_key_checking=to_bool(raw.get("strict_host_key_checking"), default=False),
            connect_timeout_sec=max(1, int(raw.get("connect_timeout_sec") or 10)),
            command_timeout_sec=max(0, int(raw.get("command_timeout_sec") or 0)),
            allow_agent=to_bool(raw.get("allow_agent"), default=False),
            look_for_keys=to_bool(raw.get("look_for_keys"), default=False),
            backend=backend,
        )


class ConfigStore:
    def __init__(self, path: Path):
        self.path = path

    @staticmethod
    def default_data() -> dict[str, Any]:
        return {
            "format": CONFIG_FORMAT,
            "profiles": {"default": SSHConfig.defaults().to_dict()},
            "default_profile": "default",
        }

    @staticmethod
    def validate_data(data: dict[str, Any]) -> None:
        if str(data.get("format") or "").strip() != CONFIG_FORMAT:
            raise RuntimeError(f"unsupported_config_format:{data.get('format')}")
        profiles = data.get("profiles")
        if not isinstance(profiles, dict) or not profiles:
            raise RuntimeError("profiles_required")
        for name, profile in profiles.items():
            if not str(name).strip():
                raise RuntimeError("profile_name_empty")
            if not isinstance(profile, dict):
                raise RuntimeError(f"profile_must_be_object:{name}")
        default_profile = str(data.get("default_profile") or "").strip()
        if default_profile and default_profile not in profiles:
            raise RuntimeError(f"default_profile_not_found:{default_profile}")

    def load_raw(self) -> dict[str, Any]:
        if not self.path.is_file():
            return self.default_data()
        data = json.loads(self.path.read_text(encoding="utf-8"))
        if not isinstance(data, dict):
            raise RuntimeError("config_root_must_be_object")
        self.validate_data(data)
        return data

    def pick_profile_name(self, data: dict[str, Any], profile: str | None = None) -> str:
        profiles = data.get("profiles")
        if not isinstance(profiles, dict) or not profiles:
            raise RuntimeError("profiles_required")
        wanted = str(profile or os.getenv("SSH_PROFILE") or data.get("default_profile") or "").strip()
        if wanted:
            if wanted not in profiles:
                raise RuntimeError(f"profile_not_found:{wanted}")
            return wanted
        return str(next(iter(profiles.keys())))

    def load(self, profile: str | None = None) -> tuple[SSHConfig, str]:
        data = self.load_raw()
        profile_name = self.pick_profile_name(data, profile)
        profiles = data["profiles"]
        profile_data = profiles.get(profile_name)
        if not isinstance(profile_data, dict):
            raise RuntimeError(f"profile_must_be_object:{profile_name}")
        cfg = SSHConfig.from_dict({**SSHConfig.defaults().to_dict(), **profile_data})
        return cfg, profile_name

    def save_patch(self, patch: dict[str, Any], profile: str | None = None) -> tuple[SSHConfig, str]:
        data = self.load_raw()
        profiles = data.get("profiles")
        if not isinstance(profiles, dict):
            raise RuntimeError("profiles_required")
        target_profile = str(profile or patch.get("profile") or data.get("default_profile") or "").strip()
        if not target_profile:
            raise RuntimeError("profile_required")

        cur_raw = profiles.get(target_profile)
        if not isinstance(cur_raw, dict):
            cur_raw = SSHConfig.defaults().to_dict()
        cur = dict(cur_raw)
        for key, value in patch.items():
            skey = str(key)
            if skey.startswith("__") or skey == "profile":
                continue
            if skey in {"format", "profiles"}:
                continue
            if skey == "default_profile":
                data["default_profile"] = str(value).strip() or target_profile
                continue
            cur[skey] = value
        nxt = SSHConfig.from_dict(cur)
        profiles[target_profile] = nxt.to_dict()
        if not str(data.get("default_profile") or "").strip():
            data["default_profile"] = target_profile
        if str(data.get("default_profile")) not in profiles:
            raise RuntimeError(f"default_profile_not_found:{data.get('default_profile')}")
        data["format"] = CONFIG_FORMAT
        self.path.parent.mkdir(parents=True, exist_ok=True)
        self.path.write_text(json.dumps(data, ensure_ascii=False, indent=2), encoding="utf-8")
        return nxt, target_profile

    def delete_profile(self, profile: str | None = None, new_default_profile: str | None = None) -> tuple[str, str]:
        data = self.load_raw()
        profiles = data.get("profiles")
        if not isinstance(profiles, dict) or not profiles:
            raise RuntimeError("profiles_required")
        target_profile = str(profile or "").strip()
        if not target_profile:
            raise RuntimeError("profile_required")
        if target_profile not in profiles:
            raise RuntimeError(f"profile_not_found:{target_profile}")
        if len(profiles) <= 1:
            raise RuntimeError("cannot_delete_last_profile")

        del profiles[target_profile]
        wanted_default = str(new_default_profile or "").strip()
        if wanted_default:
            if wanted_default not in profiles:
                raise RuntimeError(f"default_profile_not_found:{wanted_default}")
            data["default_profile"] = wanted_default
        elif str(data.get("default_profile") or "").strip() == target_profile:
            data["default_profile"] = str(next(iter(profiles.keys())))

        data["format"] = CONFIG_FORMAT
        self.path.parent.mkdir(parents=True, exist_ok=True)
        self.path.write_text(json.dumps(data, ensure_ascii=False, indent=2), encoding="utf-8")
        return target_profile, str(data.get("default_profile") or "")


class SSHClient:
    def __init__(self, cfg: SSHConfig):
        self.cfg = cfg

    def validate_auth(self) -> str:
        if not self.cfg.host:
            return "host_missing"
        if not self.cfg.username and not self.cfg.password and not self.cfg.key_path:
            return "username_or_auth_missing"
        return ""

    def _remote_cmd(self, cmd: str) -> str:
        timeout_sec = int(self.cfg.command_timeout_sec or 0)
        if timeout_sec <= 0:
            return cmd
        wrapped = f"timeout -k 5s {timeout_sec}s bash -lc {shlex.quote(cmd)}"
        return f"bash -lc {shlex.quote(wrapped)}"

    def _ssh_args(self, cmd: str) -> list[str]:
        target = f"{self.cfg.username}@{self.cfg.host}" if self.cfg.username else self.cfg.host
        null_path = "NUL" if os.name == "nt" else "/dev/null"
        args = [
            "ssh",
            "-o",
            "BatchMode=yes",
            "-o",
            "NumberOfPasswordPrompts=0",
            "-o",
            f"ConnectTimeout={self.cfg.connect_timeout_sec}",
            "-p",
            str(self.cfg.port),
        ]
        if self.cfg.strict_host_key_checking:
            args.extend(["-o", "StrictHostKeyChecking=yes"])
            if self.cfg.known_hosts_path:
                args.extend(["-o", f"UserKnownHostsFile={self.cfg.known_hosts_path}"])
        else:
            args.extend(["-o", "StrictHostKeyChecking=no"])
            args.extend(["-o", f"UserKnownHostsFile={self.cfg.known_hosts_path or null_path}"])
        if self.cfg.key_path:
            args.extend(["-i", self.cfg.key_path])
        args.extend([target, self._remote_cmd(cmd)])
        return args

    def exec_openssh(self, command: str) -> dict[str, Any]:
        args = self._ssh_args(command)
        timeout_sec = int(self.cfg.command_timeout_sec or 0)
        try:
            proc = subprocess.run(
                args,
                capture_output=True,
                text=True,
                timeout=timeout_sec if timeout_sec > 0 else None,
            )
            return {
                "ok": proc.returncode == 0,
                "backend": "openssh",
                "exit_code": int(proc.returncode),
                "stdout": proc.stdout,
                "stderr": proc.stderr,
                "cmd": " ".join(shlex.quote(x) for x in args),
            }
        except subprocess.TimeoutExpired as exc:
            return {
                "ok": False,
                "backend": "openssh",
                "error": "command_timeout",
                "exit_code": -1,
                "stdout": exc.stdout if isinstance(exc.stdout, str) else "",
                "stderr": exc.stderr if isinstance(exc.stderr, str) else "",
            }
        except Exception as exc:
            return {
                "ok": False,
                "backend": "openssh",
                "exit_code": -1,
                "stdout": "",
                "stderr": str(exc),
            }

    def exec_paramiko(self, command: str) -> dict[str, Any]:
        if paramiko is None:
            return {"ok": False, "backend": "paramiko", "error": "paramiko_not_installed", "exit_code": -1, "stdout": "", "stderr": ""}

        target = f"{self.cfg.username}@{self.cfg.host}" if self.cfg.username else self.cfg.host
        client = paramiko.SSHClient()  # type: ignore[attr-defined]

        if self.cfg.strict_host_key_checking:
            if self.cfg.known_hosts_path:
                client.load_host_keys(self.cfg.known_hosts_path)
            else:
                client.load_system_host_keys()
            client.set_missing_host_key_policy(paramiko.RejectPolicy())  # type: ignore[attr-defined]
        else:
            client.set_missing_host_key_policy(paramiko.AutoAddPolicy())  # type: ignore[attr-defined]

        try:
            client.connect(
                hostname=self.cfg.host,
                port=int(self.cfg.port),
                username=self.cfg.username or None,
                password=self.cfg.password or None,
                key_filename=self.cfg.key_path or None,
                timeout=float(self.cfg.connect_timeout_sec),
                banner_timeout=float(self.cfg.connect_timeout_sec),
                auth_timeout=float(self.cfg.connect_timeout_sec),
                look_for_keys=bool(self.cfg.look_for_keys),
                allow_agent=bool(self.cfg.allow_agent),
            )
            stdin, stdout, stderr = client.exec_command(
                self._remote_cmd(command),
                timeout=float(self.cfg.command_timeout_sec) if self.cfg.command_timeout_sec > 0 else None,
            )
            try:
                if stdin is not None:
                    stdin.close()
            except Exception:
                pass
            out = stdout.read().decode("utf-8", errors="replace")
            err = stderr.read().decode("utf-8", errors="replace")
            code = int(stdout.channel.recv_exit_status())
            return {
                "ok": code == 0,
                "backend": "paramiko",
                "target": target,
                "exit_code": code,
                "stdout": out,
                "stderr": err,
            }
        except Exception as exc:
            return {
                "ok": False,
                "backend": "paramiko",
                "target": target,
                "exit_code": -1,
                "stdout": "",
                "stderr": str(exc),
            }
        finally:
            try:
                client.close()
            except Exception:
                pass

    def exec_paramiko_sudo(self, command: str) -> dict[str, Any]:
        if paramiko is None:
            return {"ok": False, "backend": "paramiko", "error": "paramiko_not_installed", "exit_code": -1, "stdout": "", "stderr": ""}
        if not self.cfg.password:
            return {"ok": False, "backend": "paramiko", "error": "password_required_for_sudo", "exit_code": -1, "stdout": "", "stderr": ""}
        client = paramiko.SSHClient()  # type: ignore[attr-defined]
        if self.cfg.strict_host_key_checking:
            client.load_system_host_keys()
            client.set_missing_host_key_policy(paramiko.RejectPolicy())  # type: ignore[attr-defined]
        else:
            client.set_missing_host_key_policy(paramiko.AutoAddPolicy())  # type: ignore[attr-defined]
        try:
            client.connect(
                hostname=self.cfg.host,
                port=int(self.cfg.port),
                username=self.cfg.username or None,
                password=self.cfg.password,
                key_filename=self.cfg.key_path or None,
                timeout=float(self.cfg.connect_timeout_sec),
                banner_timeout=float(self.cfg.connect_timeout_sec),
                auth_timeout=float(self.cfg.connect_timeout_sec),
                look_for_keys=bool(self.cfg.look_for_keys),
                allow_agent=bool(self.cfg.allow_agent),
            )
            stdin, stdout, stderr = client.exec_command(
                f"sudo -S -p '' {command}",
                timeout=float(self.cfg.command_timeout_sec) if self.cfg.command_timeout_sec > 0 else None,
            )
            stdin.write(self.cfg.password + "\n")
            stdin.flush()
            stdin.close()
            out = stdout.read().decode("utf-8", errors="replace")
            err = stderr.read().decode("utf-8", errors="replace")
            code = int(stdout.channel.recv_exit_status())
            return {"ok": code == 0, "backend": "paramiko", "exit_code": code, "stdout": out, "stderr": err}
        except Exception as exc:
            return {"ok": False, "backend": "paramiko", "exit_code": -1, "stdout": "", "stderr": str(exc)}
        finally:
            try:
                client.close()
            except Exception:
                pass

    def exec_remote(self, command: str) -> dict[str, Any]:
        backend = self.cfg.backend.strip().lower()
        if backend == "paramiko":
            return self.exec_paramiko(command)
        if backend == "openssh":
            return self.exec_openssh(command)
        if paramiko is not None and self.cfg.password:
            return self.exec_paramiko(command)
        return self.exec_openssh(command)

    def exec_many(self, commands: list[str]) -> dict[str, Any]:
        rows: list[dict[str, Any]] = []
        all_ok = True
        for idx, cmd in enumerate(commands, start=1):
            row = self.exec_remote(str(cmd or ""))
            row["index"] = idx
            row["command"] = str(cmd or "")
            rows.append(row)
            if not bool(row.get("ok")):
                all_ok = False
        return {"ok": all_ok, "count": len(rows), "results": rows}

    def connect_test(self) -> dict[str, Any]:
        try:
            with socket.create_connection((self.cfg.host, int(self.cfg.port)), timeout=float(self.cfg.connect_timeout_sec)):
                pass
            return {"ok": True, "host": self.cfg.host, "port": int(self.cfg.port), "message": "tcp_connect_ok"}
        except Exception as exc:
            return {"ok": False, "host": self.cfg.host, "port": int(self.cfg.port), "error": str(exc)}


class MCPSSHServer:
    def __init__(self):
        cfg_path = Path(os.getenv("SSH_CONFIG_PATH", str(DEFAULT_CONFIG_PATH))).expanduser().resolve()
        self.store = ConfigStore(cfg_path)

    def tool_schemas(self) -> list[dict[str, Any]]:
        return [
            {
                "name": "ssh_config_show",
                "description": "Show active SSH config with masked password",
                "inputSchema": {"type": "object", "properties": {"profile": {"type": "string"}}},
            },
            {
                "name": "ssh_config_save",
                "description": "Create or update an SSH profile in ssh_konfig.json",
                "inputSchema": {
                    "type": "object",
                    "properties": {
                        "patch": {"type": "object", "additionalProperties": True},
                        "profile": {"type": "string"},
                    },
                    "required": ["patch"],
                },
            },
            {
                "name": "ssh_config_delete",
                "description": "Delete an SSH profile from ssh_konfig.json",
                "inputSchema": {
                    "type": "object",
                    "properties": {
                        "profile": {"type": "string"},
                        "new_default_profile": {"type": "string"},
                    },
                    "required": ["profile"],
                },
            },
            {
                "name": "ssh_connect_test",
                "description": "Check TCP connect to configured host/port",
                "inputSchema": {"type": "object", "properties": {"profile": {"type": "string"}}},
            },
            {
                "name": "ssh_exec",
                "description": "Execute one command on remote host",
                "inputSchema": {
                    "type": "object",
                    "properties": {"command": {"type": "string"}, "profile": {"type": "string"}},
                    "required": ["command"],
                },
            },
            {
                "name": "ssh_exec_many",
                "description": "Execute many commands on remote host in sequence",
                "inputSchema": {
                    "type": "object",
                    "properties": {
                        "commands": {"type": "array", "items": {"type": "string"}},
                        "profile": {"type": "string"},
                    },
                    "required": ["commands"],
                },
            },
            {
                "name": "ssh_mcp_service_manage",
                "description": "Show status or restart an allowed WEB MCP system service",
                "inputSchema": {
                    "type": "object",
                    "properties": {
                        "service": {"type": "string", "description": "Service name such as web-mcp-http.service"},
                        "action": {"type": "string", "enum": ["status", "restart"]},
                        "profile": {"type": "string"},
                    },
                    "required": ["service", "action"],
                },
            },
            {
                "name": "mcp_apply_update",
                "description": "Apply deployed changes to one WEB MCP worker. The selected worker briefly reloads and resumes serving requests.",
                "inputSchema": {
                    "type": "object",
                    "properties": {
                        "worker": {"type": "string", "description": "MCP worker id, for example http, ftp, ssh or telegram"},
                        "profile": {"type": "string"},
                    },
                    "required": ["worker"],
                },
            },
        ]

    def call_tool(self, name: str, args: dict[str, Any]) -> dict[str, Any]:
        profile = str(args.get("profile") or "").strip() or None

        if name == "ssh_config_show":
            cfg, profile_name = self.store.load(profile)
            raw = self.store.load_raw()
            profiles = raw.get("profiles") if isinstance(raw, dict) else {}
            return {
                "ok": True,
                "server": SERVER_NAME,
                "config_path": str(self.store.path),
                "profile": profile_name,
                "format": str(raw.get("format") or CONFIG_FORMAT) if isinstance(raw, dict) else CONFIG_FORMAT,
                "default_profile": str(raw.get("default_profile") or profile_name) if isinstance(raw, dict) else profile_name,
                "available_profiles": sorted(list(profiles.keys())) if isinstance(profiles, dict) else [profile_name],
                "config": mask_config(cfg.to_dict()),
            }

        if name == "ssh_config_save":
            patch = args.get("patch")
            if not isinstance(patch, dict):
                raise RuntimeError("patch_must_be_object")
            saved, saved_profile = self.store.save_patch(patch, profile=profile)
            raw = self.store.load_raw()
            profiles = raw.get("profiles") if isinstance(raw, dict) else {}
            return {
                "ok": True,
                "server": SERVER_NAME,
                "config_path": str(self.store.path),
                "profile": saved_profile,
                "format": str(raw.get("format") or CONFIG_FORMAT) if isinstance(raw, dict) else CONFIG_FORMAT,
                "default_profile": str(raw.get("default_profile") or saved_profile) if isinstance(raw, dict) else saved_profile,
                "available_profiles": sorted(list(profiles.keys())) if isinstance(profiles, dict) else [saved_profile],
                "config": mask_config(saved.to_dict()),
            }

        if name == "ssh_config_delete":
            deleted_profile, default_profile = self.store.delete_profile(
                profile=profile,
                new_default_profile=str(args.get("new_default_profile") or "").strip() or None,
            )
            raw = self.store.load_raw()
            profiles = raw.get("profiles") if isinstance(raw, dict) else {}
            return {
                "ok": True,
                "server": SERVER_NAME,
                "config_path": str(self.store.path),
                "deleted_profile": deleted_profile,
                "default_profile": default_profile,
                "available_profiles": sorted(list(profiles.keys())) if isinstance(profiles, dict) else [],
            }

        cfg, profile_name = self.store.load(profile)
        cli = SSHClient(cfg)
        auth_err = cli.validate_auth()
        if auth_err:
            return {"ok": False, "server": SERVER_NAME, "error": auth_err}

        if name == "ssh_connect_test":
            out = cli.connect_test()
            return {"server": SERVER_NAME, "profile": profile_name, **out}

        if name == "ssh_exec":
            command = str(args.get("command") or "").strip()
            if not command:
                return {"ok": False, "server": SERVER_NAME, "error": "command_is_empty"}
            out = cli.exec_remote(command)
            return {"server": SERVER_NAME, "profile": profile_name, **out}

        if name == "ssh_exec_many":
            commands = args.get("commands")
            if not isinstance(commands, list):
                return {"ok": False, "server": SERVER_NAME, "error": "commands_must_be_array"}
            rows = [str(x or "") for x in commands]
            out = cli.exec_many(rows)
            return {"server": SERVER_NAME, "profile": profile_name, **out}

        if name == "ssh_mcp_service_manage":
            service = str(args.get("service") or "").strip()
            action = str(args.get("action") or "").strip().lower()
            if not re.fullmatch(r"web-mcp-[a-z0-9-]+\.service", service):
                return {"ok": False, "server": SERVER_NAME, "profile": profile_name, "error": "service_not_allowed"}
            if action == "status":
                out = cli.exec_remote(f"systemctl is-active {shlex.quote(service)}")
                return {"server": SERVER_NAME, "profile": profile_name, "service": service, "action": action, **out}
            if action == "restart":
                unit = f"mcp-restart-{service[:-8]}-{int(datetime.now(timezone.utc).timestamp())}"
                command = (
                    f"systemd-run --quiet --collect --on-active=2s --unit={shlex.quote(unit)} "
                    f"/usr/bin/systemctl restart {shlex.quote(service)}"
                )
                out = cli.exec_paramiko_sudo(command)
                return {"server": SERVER_NAME, "profile": profile_name, "service": service, "action": action, "scheduled": bool(out.get("ok")), **out}
            return {"ok": False, "server": SERVER_NAME, "profile": profile_name, "error": "unsupported_action"}

        if name == "mcp_apply_update":
            worker = str(args.get("worker") or "").strip().lower()
            if not re.fullmatch(r"[a-z0-9-]+", worker):
                return {"ok": False, "server": SERVER_NAME, "profile": profile_name, "error": "worker_not_allowed"}
            service = f"web-mcp-{worker}.service"
            unit = f"mcp-apply-{worker}-{int(datetime.now(timezone.utc).timestamp())}"
            command = (
                f"systemd-run --quiet --collect --on-active=2s --unit={shlex.quote(unit)} "
                f"/usr/bin/systemctl restart {shlex.quote(service)}"
            )
            out = cli.exec_paramiko_sudo(command)
            return {
                "server": SERVER_NAME,
                "profile": profile_name,
                "worker": worker,
                "applied": bool(out.get("ok")),
                **out,
            }

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
    server = MCPSSHServer()
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
    socket.setdefaulttimeout(20)
    raise SystemExit(run_stdio_server())
