from __future__ import annotations

import argparse
import base64
import json
import os
import subprocess
import sys
import threading
import time
from dataclasses import dataclass
from datetime import datetime, timezone
from pathlib import Path
from typing import Any

try:
    from watchdog.events import FileMovedEvent, FileSystemEvent, FileSystemEventHandler  # type: ignore
    from watchdog.observers import Observer  # type: ignore
except Exception:
    Observer = None
    FileSystemEventHandler = object  # type: ignore
    FileSystemEvent = object  # type: ignore
    FileMovedEvent = object  # type: ignore

SERVER_NAME = "mcp-git"
SERVER_VERSION = "1.0.0"
PROTOCOL_VERSION = "2025-03-26"
CONFIG_FORMAT = "local_tools_git_multi_v1"
DEFAULT_CONFIG_PATH = (Path(__file__).resolve().parent / "git_konfig.json").resolve()
WATCHDOG_STATE_DIR = (Path(__file__).resolve().parent / "watchdog_state").resolve()
DEBOUNCE_SECONDS = 5.0
BLOCKED_NAMES = {
    "git_konfig.json",
    "ftp_konfig.json",
    "ssh_konfig.json",
    "config.toml",
    "agents.md",
    "skill.md",
}


def now_iso() -> str:
    return datetime.now(timezone.utc).isoformat()


def is_pid_alive(pid: int) -> bool:
    if pid <= 0:
        return False
    try:
        if os.name == "nt":
            out = subprocess.run(
                ["tasklist", "/FI", f"PID eq {pid}", "/FO", "CSV", "/NH"],
                capture_output=True,
                text=True,
                check=False,
            )
            return str(pid) in (out.stdout or "")
        os.kill(pid, 0)
        return True
    except Exception:
        return False


def watchdog_state_path(profile: str) -> Path:
    safe = "".join(ch if ch.isalnum() or ch in {"-", "_", "."} else "_" for ch in profile).strip("._") or "default"
    WATCHDOG_STATE_DIR.mkdir(parents=True, exist_ok=True)
    return WATCHDOG_STATE_DIR / f"watchdog_{safe}.json"


def profile_log_path(profile: str) -> Path:
    safe = "".join(ch if ch.isalnum() or ch in {"-", "_", "."} else "_" for ch in profile).strip("._") or "default"
    WATCHDOG_STATE_DIR.mkdir(parents=True, exist_ok=True)
    return WATCHDOG_STATE_DIR / f"watchdog_{safe}.log"


def mask_config(cfg: dict[str, Any]) -> dict[str, Any]:
    safe = dict(cfg)
    for k in ("password", "token", "auth_token", "http_password", "forgejo_admin_password"):
        if safe.get(k):
            safe[k] = "***"
    return safe


@dataclass
class GitConfig:
    local_dir: str
    remote_url: str
    remote_name: str = "origin"
    branch: str = "main"
    commit_message_template: str = "auto-update: {ts}"
    commit_author_name: str = ""
    commit_author_email: str = ""
    ssh_key_path: str = ""
    ssh_port: int = 0
    ssh_command: str = ""
    http_username: str = ""
    http_password: str = ""
    forgejo_api_url: str = ""
    forgejo_admin_username: str = ""
    forgejo_admin_password: str = ""
    excluded_dirs_text: str = ""
    excluded_exts_text: str = ""
    debounce_seconds: float = DEBOUNCE_SECONDS


class ConfigStore:
    def __init__(self, path: Path) -> None:
        self.path = path
        self._lock = threading.Lock()

    def _read_raw(self) -> dict[str, Any]:
        if not self.path.exists():
            base = {
                "format": CONFIG_FORMAT,
                "profiles": {
                    "default": {
                        "local_dir": "",
                        "remote_url": "",
                        "remote_name": "origin",
                        "branch": "main",
                        "commit_message_template": "auto-update: {ts}",
                        "commit_author_name": "",
                        "commit_author_email": "",
                        "ssh_key_path": "",
                        "ssh_port": 0,
                        "ssh_command": "",
                        "http_username": "",
                        "http_password": "",
                        "forgejo_api_url": "",
                        "forgejo_admin_username": "",
                        "forgejo_admin_password": "",
                        "excluded_dirs_text": "",
                        "excluded_exts_text": "",
                        "debounce_seconds": DEBOUNCE_SECONDS,
                    }
                },
                "default_profile": "default",
            }
            self.path.parent.mkdir(parents=True, exist_ok=True)
            self.path.write_text(json.dumps(base, ensure_ascii=False, indent=2), encoding="utf-8")
            return base
        try:
            return json.loads(self.path.read_text(encoding="utf-8"))
        except Exception:
            return {"format": CONFIG_FORMAT, "profiles": {}, "default_profile": "default"}

    def _write_raw(self, data: dict[str, Any]) -> None:
        self.path.write_text(json.dumps(data, ensure_ascii=False, indent=2), encoding="utf-8")

    def list_profiles(self) -> list[str]:
        data = self._read_raw()
        profiles = data.get("profiles") or {}
        return sorted(profiles.keys())

    def default_profile(self) -> str:
        data = self._read_raw()
        return str(data.get("default_profile") or "default")

    def raw(self) -> dict[str, Any]:
        return self._read_raw()

    def load(self, profile: str | None) -> tuple[GitConfig, str]:
        data = self._read_raw()
        profiles = data.get("profiles") or {}
        name = (profile or "").strip() or str(data.get("default_profile") or "default")
        if name not in profiles:
            raise RuntimeError(f"profile_not_found:{name}")
        raw = profiles[name] or {}
        cfg = GitConfig(
            local_dir=str(raw.get("local_dir") or "").strip(),
            remote_url=str(raw.get("remote_url") or "").strip(),
            remote_name=str(raw.get("remote_name") or "origin").strip() or "origin",
            branch=str(raw.get("branch") or "main").strip() or "main",
            commit_message_template=str(raw.get("commit_message_template") or "auto-update: {ts}"),
            commit_author_name=str(raw.get("commit_author_name") or ""),
            commit_author_email=str(raw.get("commit_author_email") or ""),
            ssh_key_path=str(raw.get("ssh_key_path") or ""),
            ssh_port=int(raw.get("ssh_port") or 0),
            ssh_command=str(raw.get("ssh_command") or ""),
            http_username=str(raw.get("http_username") or ""),
            http_password=str(raw.get("http_password") or ""),
            forgejo_api_url=str(raw.get("forgejo_api_url") or ""),
            forgejo_admin_username=str(raw.get("forgejo_admin_username") or ""),
            forgejo_admin_password=str(raw.get("forgejo_admin_password") or ""),
            excluded_dirs_text=str(raw.get("excluded_dirs_text") or ""),
            excluded_exts_text=str(raw.get("excluded_exts_text") or ""),
            debounce_seconds=float(raw.get("debounce_seconds") or DEBOUNCE_SECONDS),
        )
        return cfg, name

    def save(self, patch: dict[str, Any], profile: str | None) -> tuple[dict[str, Any], str]:
        with self._lock:
            data = self._read_raw()
            data["format"] = CONFIG_FORMAT
            profiles = dict(data.get("profiles") or {})
            name = (profile or "").strip() or str(data.get("default_profile") or "default")
            current = dict(profiles.get(name) or {})
            for k, v in (patch or {}).items():
                if k in BLOCKED_NAMES:
                    continue
                current[k] = v
            profiles[name] = current
            data["profiles"] = profiles
            if not data.get("default_profile"):
                data["default_profile"] = name
            self._write_raw(data)
            return current, name


class GitRunner:
    def __init__(self, cfg: GitConfig) -> None:
        self.cfg = cfg

    def env(self) -> dict[str, str]:
        env = dict(os.environ)
        ssh_cmd = self.cfg.ssh_command.strip()
        if not ssh_cmd and self.cfg.ssh_key_path:
            key = self.cfg.ssh_key_path.replace("\\", "/")
            ssh_cmd = f'ssh -i "{key}" -o IdentitiesOnly=yes -o StrictHostKeyChecking=accept-new'
        if ssh_cmd and self.cfg.ssh_port > 0 and " -p " not in f" {ssh_cmd} ":
            ssh_cmd = f"{ssh_cmd} -p {self.cfg.ssh_port}"
        elif not ssh_cmd and self.cfg.ssh_port > 0:
            ssh_cmd = f"ssh -p {self.cfg.ssh_port} -o StrictHostKeyChecking=accept-new"
        if ssh_cmd:
            env["GIT_SSH_COMMAND"] = ssh_cmd
        if self.cfg.http_username and self.cfg.http_password:
            basic = base64.b64encode(f"{self.cfg.http_username}:{self.cfg.http_password}".encode("utf-8")).decode("ascii")
            env["GIT_CONFIG_COUNT"] = "1"
            env["GIT_CONFIG_KEY_0"] = "http.extraHeader"
            env["GIT_CONFIG_VALUE_0"] = f"Authorization: Basic {basic}"
            env["GIT_TERMINAL_PROMPT"] = "0"
        return env

    def workdir(self) -> Path:
        if not self.cfg.local_dir:
            raise RuntimeError("local_dir_not_set")
        p = Path(self.cfg.local_dir).expanduser().resolve()
        if not p.exists():
            raise RuntimeError(f"local_dir_not_found:{p}")
        return p

    def run(self, args: list[str], check: bool = False, capture: bool = True, timeout: int | None = 600) -> dict[str, Any]:
        wd = self.workdir()
        proc = subprocess.run(
            ["git"] + args,
            cwd=str(wd),
            env=self.env(),
            capture_output=capture,
            text=True,
            timeout=timeout,
            check=False,
        )
        out = {
            "exit_code": int(proc.returncode),
            "stdout": (proc.stdout or "").strip(),
            "stderr": (proc.stderr or "").strip(),
            "args": ["git"] + args,
            "cwd": str(wd),
        }
        if check and proc.returncode != 0:
            raise RuntimeError(f"git_failed:{out['stderr'] or out['stdout']}")
        return out

    def ensure_identity(self) -> None:
        if self.cfg.commit_author_name:
            self.run(["config", "user.name", self.cfg.commit_author_name])
        if self.cfg.commit_author_email:
            self.run(["config", "user.email", self.cfg.commit_author_email])

    def ensure_remote(self) -> dict[str, Any]:
        remotes = self.run(["remote", "-v"])
        url = self.cfg.remote_url.strip()
        if not url:
            return {"ok": True, "action": "skip_no_url"}
        if self.cfg.remote_name in (remotes["stdout"] or ""):
            current = self.run(["remote", "get-url", self.cfg.remote_name])
            if current["exit_code"] == 0 and current["stdout"] != url:
                self.run(["remote", "set-url", self.cfg.remote_name, url], check=True)
                return {"ok": True, "action": "set-url", "url": url}
            return {"ok": True, "action": "exists", "url": url}
        self.run(["remote", "add", self.cfg.remote_name, url], check=True)
        return {"ok": True, "action": "add", "url": url}

    def is_dirty(self) -> bool:
        st = self.run(["status", "--porcelain"])
        return bool((st["stdout"] or "").strip())


class WatchdogSyncManager(FileSystemEventHandler):  # type: ignore[misc]
    def __init__(self, cfg: GitConfig, profile_name: str, log_path: Path) -> None:
        super().__init__()
        self.cfg = cfg
        self.profile_name = profile_name
        self.log_path = log_path
        self.runner = GitRunner(cfg)
        self._lock = threading.Lock()
        self._pending = False
        self._last_event_ts = 0.0
        self._stop = threading.Event()

    def log(self, message: str) -> None:
        try:
            with self.log_path.open("a", encoding="utf-8") as fh:
                fh.write(f"[{now_iso()}] {message}\n")
        except Exception:
            pass

    def _is_inside_git_dir(self, path: str) -> bool:
        norm = path.replace("\\", "/").lower()
        return "/.git/" in norm or norm.endswith("/.git")

    def on_any_event(self, event: FileSystemEvent) -> None:
        if getattr(event, "is_directory", False):
            return
        src = str(getattr(event, "src_path", "")) or ""
        dest = str(getattr(event, "dest_path", "") or "")
        if self._is_inside_git_dir(src) or (dest and self._is_inside_git_dir(dest)):
            return
        with self._lock:
            self._pending = True
            self._last_event_ts = time.time()

    def _commit_and_push(self) -> None:
        try:
            self.runner.ensure_identity()
            self.runner.ensure_remote()
            self.runner.run(["add", "-A"])
            if not self.runner.is_dirty_after_add():
                return
            ts = now_iso()
            msg = self.cfg.commit_message_template.replace("{ts}", ts)
            commit = self.runner.run(["commit", "-m", msg])
            if commit["exit_code"] != 0 and "nothing to commit" not in (commit["stdout"] + commit["stderr"]).lower():
                self.log(f"commit_failed: {commit['stderr'] or commit['stdout']}")
                return
            push = self.runner.run(["push", self.cfg.remote_name, f"HEAD:{self.cfg.branch}"], timeout=900)
            if push["exit_code"] != 0:
                self.log(f"push_failed: {push['stderr'] or push['stdout']}")
            else:
                self.log("push_ok")
        except Exception as exc:
            self.log(f"sync_error: {exc}")

    def run_forever(self) -> None:
        if Observer is None:
            raise RuntimeError("watchdog_not_installed")
        wd = self.runner.workdir()
        observer = Observer()
        observer.schedule(self, str(wd), recursive=True)
        observer.start()
        self.log(f"watchdog_started cwd={wd}")
        try:
            while not self._stop.is_set():
                time.sleep(1.0)
                with self._lock:
                    pending = self._pending
                    last_ts = self._last_event_ts
                if pending and (time.time() - last_ts) >= self.cfg.debounce_seconds:
                    with self._lock:
                        self._pending = False
                    self._commit_and_push()
        finally:
            observer.stop()
            observer.join(timeout=5)
            self.log("watchdog_stopped")


def _is_dirty_after_add(self: GitRunner) -> bool:
    diff = self.run(["diff", "--cached", "--quiet"])
    return diff["exit_code"] == 1


GitRunner.is_dirty_after_add = _is_dirty_after_add  # type: ignore[attr-defined]


class MCPGitServer:
    def __init__(self) -> None:
        cfg_path = Path(os.getenv("GIT_CONFIG_PATH", str(DEFAULT_CONFIG_PATH))).expanduser().resolve()
        self.store = ConfigStore(cfg_path)

    def tool_schemas(self) -> list[dict[str, Any]]:
        return [
            {
                "name": "git_config_show",
                "description": "Show active git profile config with masked secrets",
                "inputSchema": {"type": "object", "properties": {"profile": {"type": "string"}}},
            },
            {
                "name": "git_config_save",
                "description": "Save partial config patch into git_konfig.json",
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
                "name": "git_status",
                "description": "Show git status of profile local_dir",
                "inputSchema": {"type": "object", "properties": {"profile": {"type": "string"}}},
            },
            {
                "name": "git_log",
                "description": "Show last N commits",
                "inputSchema": {
                    "type": "object",
                    "properties": {
                        "profile": {"type": "string"},
                        "limit": {"type": "integer", "minimum": 1, "maximum": 200},
                    },
                },
            },
            {
                "name": "git_remote_tree",
                "description": "List remote branches and tags",
                "inputSchema": {"type": "object", "properties": {"profile": {"type": "string"}}},
            },
            {
                "name": "git_pull",
                "description": "Pull from remote into current branch",
                "inputSchema": {"type": "object", "properties": {"profile": {"type": "string"}}},
            },
            {
                "name": "git_push",
                "description": "Push current branch to remote",
                "inputSchema": {
                    "type": "object",
                    "properties": {
                        "profile": {"type": "string"},
                        "set_upstream": {"type": "boolean"},
                    },
                },
            },
            {
                "name": "git_commit",
                "description": "Stage all changes and create a commit",
                "inputSchema": {
                    "type": "object",
                    "properties": {
                        "profile": {"type": "string"},
                        "message": {"type": "string"},
                        "add_all": {"type": "boolean"},
                    },
                },
            },
            {
                "name": "git_sync",
                "description": "Add all changes, commit and push in one step",
                "inputSchema": {
                    "type": "object",
                    "properties": {
                        "profile": {"type": "string"},
                        "message": {"type": "string"},
                    },
                },
            },
            {
                "name": "git_clone",
                "description": "Clone remote_url into local_dir if local_dir is empty",
                "inputSchema": {"type": "object", "properties": {"profile": {"type": "string"}}},
            },
            {
                "name": "git_init_push",
                "description": "Initialize repo at local_dir, commit all, set remote and push",
                "inputSchema": {
                    "type": "object",
                    "properties": {
                        "profile": {"type": "string"},
                        "first_commit_message": {"type": "string"},
                    },
                },
            },
            {
                "name": "git_watchdog",
                "description": "Start/stop/status of auto commit+push watchdog for the profile",
                "inputSchema": {
                    "type": "object",
                    "properties": {
                        "action": {"type": "string", "enum": ["start", "status", "stop"]},
                        "profile": {"type": "string"},
                    },
                    "required": ["action"],
                },
            },
        ]

    def watchdog_status(self, profile_name: str) -> dict[str, Any]:
        state_file = watchdog_state_path(profile_name)
        if not state_file.is_file():
            return {"ok": True, "server": SERVER_NAME, "profile": profile_name, "running": False}
        try:
            data = json.loads(state_file.read_text(encoding="utf-8"))
        except Exception:
            return {"ok": True, "server": SERVER_NAME, "profile": profile_name, "running": False}
        pid = int(data.get("pid") or 0)
        running = is_pid_alive(pid)
        return {
            "ok": True,
            "server": SERVER_NAME,
            "profile": profile_name,
            "running": running,
            "pid": pid,
            "started_at": data.get("started_at"),
            "log_path": data.get("log_path"),
            "state_path": str(state_file),
        }

    def watchdog_start(self, profile_name: str) -> dict[str, Any]:
        if Observer is None:
            return {"ok": False, "server": SERVER_NAME, "profile": profile_name, "error": "watchdog_not_installed"}
        st = self.watchdog_status(profile_name)
        if st.get("running"):
            return st
        state_file = watchdog_state_path(profile_name)
        log_path = profile_log_path(profile_name)
        cmd = [sys.executable, str(Path(__file__).resolve()), "--watchdog", "--profile", profile_name]
        flags = 0
        if os.name == "nt":
            flags = int(getattr(subprocess, "DETACHED_PROCESS", 0)) | int(getattr(subprocess, "CREATE_NEW_PROCESS_GROUP", 0))
        with log_path.open("a", encoding="utf-8") as lf:
            proc = subprocess.Popen(  # noqa: S603
                cmd,
                cwd=str(Path(__file__).resolve().parent),
                stdout=lf,
                stderr=lf,
                stdin=subprocess.DEVNULL,
                creationflags=flags,
                close_fds=True,
            )
        state = {"profile": profile_name, "pid": int(proc.pid), "started_at": now_iso(), "log_path": str(log_path)}
        state_file.write_text(json.dumps(state, ensure_ascii=False, indent=2), encoding="utf-8")
        return self.watchdog_status(profile_name)

    def watchdog_stop(self, profile_name: str) -> dict[str, Any]:
        st = self.watchdog_status(profile_name)
        state_file = watchdog_state_path(profile_name)
        if not st.get("running"):
            if state_file.exists():
                try:
                    state_file.unlink()
                except Exception:
                    pass
            return {"ok": True, "server": SERVER_NAME, "profile": profile_name, "running": False, "stopped": True}
        pid = int(st.get("pid") or 0)
        try:
            if os.name == "nt":
                subprocess.run(["taskkill", "/PID", str(pid), "/T", "/F"], capture_output=True, text=True, check=False)
            else:
                os.kill(pid, 15)
        except Exception:
            pass
        time.sleep(0.2)
        if state_file.exists():
            try:
                state_file.unlink()
            except Exception:
                pass
        return {"ok": True, "server": SERVER_NAME, "profile": profile_name, "running": is_pid_alive(pid), "stopped": True}

    def call_tool(self, name: str, args: dict[str, Any]) -> dict[str, Any]:
        if name == "git_config_show":
            cfg, profile_name = self.store.load(args.get("profile"))
            raw = self.store.raw().get("profiles", {}).get(profile_name, {})
            return {
                "ok": True,
                "server": SERVER_NAME,
                "config_path": str(self.store.path),
                "profile": profile_name,
                "format": CONFIG_FORMAT,
                "default_profile": self.store.default_profile(),
                "available_profiles": self.store.list_profiles(),
                "config": mask_config(raw),
            }

        if name == "git_config_save":
            patch = args.get("patch") or {}
            if not isinstance(patch, dict):
                raise RuntimeError("patch_must_be_object")
            new_cfg, profile_name = self.store.save(patch, args.get("profile"))
            return {
                "ok": True,
                "server": SERVER_NAME,
                "profile": profile_name,
                "config": mask_config(new_cfg),
                "default_profile": self.store.default_profile(),
            }

        cfg, profile_name = self.store.load(args.get("profile"))
        runner = GitRunner(cfg)

        if name == "git_status":
            try:
                st = runner.run(["status", "--porcelain=v1", "-b"])
                head = runner.run(["rev-parse", "--abbrev-ref", "HEAD"])
                return {"ok": True, "server": SERVER_NAME, "profile": profile_name, "branch": head["stdout"], "status_lines": st["stdout"].splitlines(), "raw": st}
            except Exception as exc:
                return {"ok": False, "server": SERVER_NAME, "profile": profile_name, "error": str(exc)}

        if name == "git_log":
            limit = int(args.get("limit") or 10)
            try:
                lg = runner.run(["log", f"-{limit}", "--pretty=format:%H|%an|%ae|%ai|%s"])
                rows = []
                for line in (lg["stdout"] or "").splitlines():
                    parts = line.split("|", 4)
                    if len(parts) == 5:
                        rows.append({"hash": parts[0], "author_name": parts[1], "author_email": parts[2], "date": parts[3], "subject": parts[4]})
                return {"ok": True, "server": SERVER_NAME, "profile": profile_name, "commits": rows}
            except Exception as exc:
                return {"ok": False, "server": SERVER_NAME, "profile": profile_name, "error": str(exc)}

        if name == "git_remote_tree":
            try:
                runner.ensure_remote()
                heads = runner.run(["ls-remote", "--heads", cfg.remote_name])
                tags = runner.run(["ls-remote", "--tags", cfg.remote_name])
                branches = [ln.split()[-1] for ln in (heads["stdout"] or "").splitlines() if ln.strip()]
                tag_refs = [ln.split()[-1] for ln in (tags["stdout"] or "").splitlines() if ln.strip()]
                return {"ok": True, "server": SERVER_NAME, "profile": profile_name, "remote": cfg.remote_name, "branches": branches, "tags": tag_refs}
            except Exception as exc:
                return {"ok": False, "server": SERVER_NAME, "profile": profile_name, "error": str(exc)}

        if name == "git_pull":
            try:
                runner.ensure_remote()
                pull = runner.run(["pull", cfg.remote_name, cfg.branch], timeout=900)
                return {"ok": pull["exit_code"] == 0, "server": SERVER_NAME, "profile": profile_name, **pull}
            except Exception as exc:
                return {"ok": False, "server": SERVER_NAME, "profile": profile_name, "error": str(exc)}

        if name == "git_push":
            try:
                runner.ensure_remote()
                push_args = ["push", cfg.remote_name, f"HEAD:{cfg.branch}"]
                if bool(args.get("set_upstream")):
                    push_args = ["push", "-u", cfg.remote_name, f"HEAD:{cfg.branch}"]
                push = runner.run(push_args, timeout=900)
                return {"ok": push["exit_code"] == 0, "server": SERVER_NAME, "profile": profile_name, **push}
            except Exception as exc:
                return {"ok": False, "server": SERVER_NAME, "profile": profile_name, "error": str(exc)}

        if name == "git_commit":
            message = str(args.get("message") or "").strip() or cfg.commit_message_template.replace("{ts}", now_iso())
            add_all = bool(args.get("add_all", True))
            try:
                runner.ensure_identity()
                if add_all:
                    runner.run(["add", "-A"])
                if not runner.is_dirty_after_add():
                    return {"ok": True, "server": SERVER_NAME, "profile": profile_name, "noop": True, "message": "nothing_to_commit"}
                commit = runner.run(["commit", "-m", message])
                return {"ok": commit["exit_code"] == 0, "server": SERVER_NAME, "profile": profile_name, **commit, "commit_message": message}
            except Exception as exc:
                return {"ok": False, "server": SERVER_NAME, "profile": profile_name, "error": str(exc)}

        if name == "git_sync":
            message = str(args.get("message") or "").strip() or cfg.commit_message_template.replace("{ts}", now_iso())
            try:
                runner.ensure_identity()
                runner.ensure_remote()
                runner.run(["add", "-A"])
                noop = not runner.is_dirty_after_add()
                if not noop:
                    runner.run(["commit", "-m", message], check=True)
                push = runner.run(["push", cfg.remote_name, f"HEAD:{cfg.branch}"], timeout=900)
                return {"ok": push["exit_code"] == 0, "server": SERVER_NAME, "profile": profile_name, "commit_noop": noop, "commit_message": message, "push": push}
            except Exception as exc:
                return {"ok": False, "server": SERVER_NAME, "profile": profile_name, "error": str(exc)}

        if name == "git_clone":
            try:
                if not cfg.remote_url:
                    return {"ok": False, "server": SERVER_NAME, "profile": profile_name, "error": "remote_url_not_set"}
                local = Path(cfg.local_dir).expanduser().resolve()
                local.mkdir(parents=True, exist_ok=True)
                if any(local.iterdir()):
                    return {"ok": False, "server": SERVER_NAME, "profile": profile_name, "error": "local_dir_not_empty"}
                env = dict(os.environ)
                env.update(runner.env())
                clone = subprocess.run(
                    ["git", "clone", cfg.remote_url, str(local)],
                    capture_output=True, text=True, env=env, check=False, timeout=900,
                )
                return {"ok": clone.returncode == 0, "server": SERVER_NAME, "profile": profile_name, "exit_code": clone.returncode, "stdout": (clone.stdout or "").strip(), "stderr": (clone.stderr or "").strip()}
            except Exception as exc:
                return {"ok": False, "server": SERVER_NAME, "profile": profile_name, "error": str(exc)}

        if name == "git_init_push":
            first_message = str(args.get("first_commit_message") or "first_commit").strip()
            try:
                wd = runner.workdir()
                git_dir = wd / ".git"
                if not git_dir.exists():
                    runner.run(["init", "-b", cfg.branch], check=True)
                runner.ensure_identity()
                runner.ensure_remote()
                runner.run(["add", "-A"])
                if runner.is_dirty_after_add():
                    runner.run(["commit", "-m", first_message], check=True)
                push = runner.run(["push", "-u", cfg.remote_name, f"HEAD:{cfg.branch}"], timeout=900)
                return {"ok": push["exit_code"] == 0, "server": SERVER_NAME, "profile": profile_name, "init_done": True, "push": push}
            except Exception as exc:
                return {"ok": False, "server": SERVER_NAME, "profile": profile_name, "error": str(exc)}

        if name == "git_watchdog":
            action = str(args.get("action") or "").strip().lower()
            if action == "status":
                return self.watchdog_status(profile_name)
            if action == "start":
                return self.watchdog_start(profile_name)
            if action == "stop":
                return self.watchdog_stop(profile_name)
            return {"ok": False, "server": SERVER_NAME, "profile": profile_name, "error": "unknown_watchdog_action"}

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
    write_message({
        "jsonrpc": "2.0",
        "id": req_id,
        "result": {"content": [{"type": "text", "text": text}], "isError": not bool(data.get("ok"))},
    })


def run_stdio_server() -> int:
    server = MCPGitServer()
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
            success_result(req_id, {
                "protocolVersion": PROTOCOL_VERSION,
                "serverInfo": {"name": SERVER_NAME, "version": SERVER_VERSION},
                "capabilities": {"tools": {}},
            })
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
                a = params.get("arguments") or {}
                if not isinstance(a, dict):
                    raise RuntimeError("arguments_must_be_object")
                out = server.call_tool(tool_name, a)
                tool_call_result(req_id, out)
            except Exception as exc:
                tool_call_result(req_id, {"ok": False, "server": SERVER_NAME, "error": str(exc)})
            continue

        if req_id is not None:
            error_result(req_id, -32601, f"method_not_found:{method}")

    return 0


def run_watchdog(profile: str | None) -> int:
    store = ConfigStore(Path(os.getenv("GIT_CONFIG_PATH", str(DEFAULT_CONFIG_PATH))).expanduser().resolve())
    cfg, profile_name = store.load(profile)
    manager = WatchdogSyncManager(cfg, profile_name, profile_log_path(profile_name))
    manager.run_forever()
    return 0


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(prog="MCP_GIT")
    parser.add_argument("--transport", default="stdio")
    parser.add_argument("--watchdog", action="store_true")
    parser.add_argument("--profile", default=None)
    args = parser.parse_args(argv)
    if str(args.transport).strip().lower() != "stdio":
        raise RuntimeError(f"unsupported_transport:{args.transport}")
    if args.watchdog:
        return run_watchdog(args.profile)
    return run_stdio_server()


if __name__ == "__main__":
    raise SystemExit(main())
