from __future__ import annotations

import ftplib
import argparse
import json
import os
import posixpath
import queue
import socket
import stat
import subprocess
import sys
import tempfile
import threading
import time
from dataclasses import dataclass
from datetime import datetime, timezone
from pathlib import Path
from typing import Any

try:
    import paramiko  # type: ignore
except Exception:
    paramiko = None

try:
    from watchdog.events import FileMovedEvent, FileSystemEvent, FileSystemEventHandler  # type: ignore
    from watchdog.observers import Observer  # type: ignore
except Exception:
    Observer = None
    FileSystemEventHandler = object  # type: ignore
    FileSystemEvent = object  # type: ignore
    FileMovedEvent = object  # type: ignore

SERVER_NAME = "mcp-ftp"
SERVER_VERSION = "2.1.0"
PROTOCOL_VERSION = "2025-03-26"
CONFIG_FORMAT = "local_tools_ftp_multi_v1"
DEFAULT_CONFIG_PATH = (Path(__file__).resolve().parent / "ftp_konfig.json").resolve()
WATCHDOG_STATE_DIR = (Path(__file__).resolve().parent / "watchdog_state").resolve()
UPLOAD_RETRIES = 3
RETRYABLE_EXCEPTIONS = (socket.timeout, TimeoutError, EOFError, BrokenPipeError, ConnectionResetError, OSError)
BLOCKED_NAMES = {
    "ftp_konfig.json",
    "ssh_konfig.json",
    "postgres_konfig.json",
    "config.toml",
    "agents.md",
    "skill.md",
    "skills.md",
}


def now_iso() -> str:
    return datetime.now(timezone.utc).isoformat()


def normalize_remote(path: str) -> str:
    raw = str(path or "/").replace("\\", "/").strip() or "/"
    if not raw.startswith("/"):
        raw = f"/{raw}"
    return posixpath.normpath(raw).replace("\\", "/")


def normalize_rel(path: str) -> str:
    return str(path or "").replace("\\", "/").strip("/")


def watchdog_state_path(profile: str) -> Path:
    safe = "".join(ch if ch.isalnum() or ch in {"-", "_", "."} else "_" for ch in profile).strip("._")
    if not safe:
        safe = "default"
    WATCHDOG_STATE_DIR.mkdir(parents=True, exist_ok=True)
    return WATCHDOG_STATE_DIR / f"watchdog_{safe}.json"


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


def mask_config(cfg: dict[str, Any]) -> dict[str, Any]:
    safe = dict(cfg)
    if safe.get("password"):
        safe["password"] = "***"
    return safe


def profile_log_path(profile: str) -> Path:
    safe = "".join(ch if ch.isalnum() or ch in {"-", "_", "."} else "_" for ch in profile).strip("._") or "default"
    WATCHDOG_STATE_DIR.mkdir(parents=True, exist_ok=True)
    return WATCHDOG_STATE_DIR / f"watchdog_{safe}.log"


def parse_excluded_dirs(text: str) -> list[str]:
    out: list[str] = []
    for line in str(text or "").splitlines():
        item = normalize_rel(line)
        if item:
            out.append(item.lower())
    return out


def parse_excluded_exts(text: str) -> set[str]:
    out: set[str] = set()
    raw = str(text or "").replace("\n", ",")
    for item in raw.split(","):
        ext = item.strip().lower()
        if not ext:
            continue
        if not ext.startswith("."):
            ext = f".{ext}"
        out.add(ext)
    return out


def path_matches_excluded_dir(rel_path: str, excluded_dirs: list[str]) -> bool:
    rel = normalize_rel(rel_path).lower()
    if not rel:
        return False
    parts = rel.split("/")
    for ex in excluded_dirs:
        ex_parts = ex.split("/")
        if len(parts) < len(ex_parts):
            continue
        for i in range(len(parts) - len(ex_parts) + 1):
            if parts[i : i + len(ex_parts)] == ex_parts:
                return True
    return False


TEXT_EXTENSIONS = {
    ".txt",
    ".html",
    ".htm",
    ".css",
    ".js",
    ".mjs",
    ".cjs",
    ".ts",
    ".tsx",
    ".jsx",
    ".json",
    ".xml",
    ".csv",
    ".md",
    ".yml",
    ".yaml",
    ".php",
    ".py",
    ".sql",
    ".ini",
    ".conf",
    ".cfg",
    ".toml",
}


def sanitize_upload_file(local_path: Path) -> tuple[Path, Path | None, bool]:
    suffix = local_path.suffix.lower()
    if suffix not in TEXT_EXTENSIONS:
        return local_path, None, False
    raw = local_path.read_bytes()
    if b"\x00" in raw[:4096]:
        return local_path, None, False
    try:
        text = raw.decode("utf-8-sig")
    except UnicodeDecodeError:
        return local_path, None, False
    cleaned = text.replace("\ufeff", "")
    cleaned = cleaned.replace("\u200b", "").replace("\u200c", "").replace("\u200d", "").replace("\u2060", "")
    cleaned = "".join(ch for ch in cleaned if (ch in "\n\r\t" or ord(ch) >= 32))
    encoded = cleaned.encode("utf-8")
    if encoded == raw:
        return local_path, None, False
    fd, tmp_name = tempfile.mkstemp(prefix="mcpftp_clean_", suffix=suffix)
    os.close(fd)
    tmp = Path(tmp_name)
    tmp.write_bytes(encoded)
    return tmp, tmp, True


@dataclass
class FTPConfig:
    protocol: str
    host: str
    port: int
    username: str
    password: str
    timeout: int
    retries: int
    local_dir: str
    remote_dir: str
    excluded_dirs_text: str
    excluded_exts_text: str

    @staticmethod
    def defaults() -> "FTPConfig":
        return FTPConfig(
            protocol="FTP",
            host="",
            port=21,
            username="",
            password="",
            timeout=60,
            retries=3,
            local_dir=".",
            remote_dir="/",
            excluded_dirs_text="",
            excluded_exts_text="",
        )

    def to_dict(self) -> dict[str, Any]:
        return {
            "protocol": self.protocol,
            "host": self.host,
            "port": self.port,
            "username": self.username,
            "password": self.password,
            "timeout": self.timeout,
            "retries": self.retries,
            "local_dir": self.local_dir,
            "remote_dir": self.remote_dir,
            "excluded_dirs_text": self.excluded_dirs_text,
            "excluded_exts_text": self.excluded_exts_text,
        }

    @staticmethod
    def from_dict(raw: dict[str, Any]) -> "FTPConfig":
        proto = str(raw.get("protocol") or "FTP").upper().strip()
        if proto not in {"FTP", "FTPS", "SFTP"}:
            raise ValueError(f"unsupported_protocol:{proto}")
        default_port = 21 if proto == "FTP" else (990 if proto == "FTPS" else 22)
        return FTPConfig(
            protocol=proto,
            host=str(raw.get("host") or "").strip(),
            port=int(raw.get("port") or default_port),
            username=str(raw.get("username") or "").strip(),
            password=str(raw.get("password") or ""),
            timeout=max(10, int(raw.get("timeout") or 60)),
            retries=max(1, int(raw.get("retries") or UPLOAD_RETRIES)),
            local_dir=str(raw.get("local_dir") or ".").strip() or ".",
            remote_dir=normalize_remote(str(raw.get("remote_dir") or "/")),
            excluded_dirs_text=str(raw.get("excluded_dirs_text") or ""),
            excluded_exts_text=str(raw.get("excluded_exts_text") or ""),
        )


class ConfigStore:
    def __init__(self, path: Path):
        self.path = path

    def _load_raw(self) -> dict[str, Any]:
        if not self.path.is_file():
            return self._default_data()
        data = json.loads(self.path.read_text(encoding="utf-8"))
        if not isinstance(data, dict):
            raise RuntimeError("config_root_must_be_object")
        self._validate_data(data)
        return data

    @staticmethod
    def _default_data() -> dict[str, Any]:
        return {
            "format": CONFIG_FORMAT,
            "profiles": {"default": FTPConfig.defaults().to_dict()},
            "default_profile": "default",
        }

    @staticmethod
    def _validate_data(data: dict[str, Any]) -> None:
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

    def _pick_profile_name(self, data: dict[str, Any], profile: str | None = None) -> str:
        profiles = data.get("profiles")
        wanted = str(profile or os.getenv("FTP_PROFILE") or data.get("default_profile") or "").strip()
        if wanted and isinstance(profiles, dict) and wanted in profiles:
            return wanted
        if wanted:
            raise RuntimeError(f"profile_not_found:{wanted}")
        return str(next(iter(profiles.keys()), "default"))

    def load(self, profile: str | None = None) -> tuple[FTPConfig, str]:
        defaults = FTPConfig.defaults().to_dict()
        data = self._load_raw()
        profiles = data.get("profiles")
        profile_name = self._pick_profile_name(data, profile)
        profile_data = profiles.get(profile_name) if isinstance(profiles, dict) and isinstance(profiles.get(profile_name), dict) else {}
        cfg = FTPConfig.from_dict({**defaults, **profile_data})
        return cfg, profile_name

    def save_patch(self, patch: dict[str, Any], profile: str | None = None) -> tuple[FTPConfig, str]:
        data = self._load_raw()
        profiles = data.get("profiles")
        if not isinstance(profiles, dict):
            raise RuntimeError("profiles_required")

        target_profile = str(profile or patch.get("profile") or data.get("default_profile") or "").strip()
        if not target_profile:
            raise RuntimeError("profile_required")

        base = profiles.get(target_profile)
        if not isinstance(base, dict):
            base = FTPConfig.defaults().to_dict()

        for key, value in patch.items():
            skey = str(key)
            if skey.startswith("__") or skey == "profile":
                continue
            if skey in {"profiles", "format"}:
                continue
            if skey == "default_profile":
                data["default_profile"] = str(value).strip() or target_profile
                continue
            base[skey] = value

        profiles[target_profile] = FTPConfig.from_dict(base).to_dict()
        if not str(data.get("default_profile") or "").strip():
            data["default_profile"] = target_profile
        if str(data.get("default_profile")) not in profiles:
            raise RuntimeError(f"default_profile_not_found:{data.get('default_profile')}")
        data["format"] = CONFIG_FORMAT

        self.path.parent.mkdir(parents=True, exist_ok=True)
        self.path.write_text(json.dumps(data, ensure_ascii=False, indent=2), encoding="utf-8")
        cfg, name = self.load(target_profile)
        return cfg, name


class FTPClient:
    def __init__(self, cfg: FTPConfig):
        self.cfg = cfg

    def validate_auth(self) -> str:
        if not self.cfg.host:
            return "host_missing"
        if not self.cfg.username:
            return "username_missing"
        if not self.cfg.password:
            return "password_missing"
        return ""

    def connect_ftp(self) -> ftplib.FTP:
        proto = self.cfg.protocol
        if proto not in {"FTP", "FTPS"}:
            raise RuntimeError(f"not_ftp_protocol:{proto}")
        cli = ftplib.FTP_TLS() if proto == "FTPS" else ftplib.FTP()
        cli.connect(self.cfg.host, self.cfg.port, timeout=float(self.cfg.timeout))
        cli.login(self.cfg.username, self.cfg.password)
        if proto == "FTPS":
            cli.prot_p()
        cli.encoding = "utf-8"
        return cli

    def connect_sftp(self):
        if paramiko is None:
            raise RuntimeError("paramiko_not_installed_for_sftp")
        ssh = paramiko.SSHClient()  # type: ignore[attr-defined]
        ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())  # type: ignore[attr-defined]
        ssh.connect(
            hostname=self.cfg.host,
            port=self.cfg.port,
            username=self.cfg.username,
            password=self.cfg.password,
            timeout=float(self.cfg.timeout),
            banner_timeout=float(self.cfg.timeout),
            auth_timeout=float(self.cfg.timeout),
        )
        return ssh, ssh.open_sftp()

    def upload_file_ftp(self, lp: Path, rp: str) -> None:
        last_exc: Exception | None = None
        for _ in range(max(1, self.cfg.retries)):
            ftp = self.connect_ftp()
            try:
                with lp.open("rb") as fh:
                    ftp.storbinary(f"STOR {rp}", fh)
                ftp.quit()
                return
            except RETRYABLE_EXCEPTIONS as exc:
                last_exc = exc
                try:
                    ftp.close()
                except Exception:
                    pass
            except Exception:
                try:
                    ftp.close()
                except Exception:
                    pass
                raise
        if last_exc is not None:
            raise last_exc
        raise RuntimeError("ftp_upload_failed_without_exception")

    def upload_file_sftp(self, lp: Path, rp: str) -> None:
        last_exc: Exception | None = None
        for _ in range(max(1, self.cfg.retries)):
            ssh, sftp = self.connect_sftp()
            try:
                sftp.put(str(lp), rp)
                sftp.close()
                ssh.close()
                return
            except RETRYABLE_EXCEPTIONS as exc:
                last_exc = exc
                try:
                    sftp.close()
                except Exception:
                    pass
                try:
                    ssh.close()
                except Exception:
                    pass
            except Exception:
                try:
                    sftp.close()
                except Exception:
                    pass
                try:
                    ssh.close()
                except Exception:
                    pass
                raise
        if last_exc is not None:
            raise last_exc
        raise RuntimeError("sftp_upload_failed_without_exception")


class WatchdogSyncManager:
    def __init__(self, cfg: FTPConfig, profile: str, log_path: Path):
        if Observer is None:
            raise RuntimeError("watchdog_not_installed")
        self.cfg = cfg
        self.profile = profile
        self.log_path = log_path
        self.local_root = Path(cfg.local_dir).expanduser().resolve()
        self.remote_root = normalize_remote(cfg.remote_dir)
        self.excluded_dirs = parse_excluded_dirs(cfg.excluded_dirs_text)
        self.excluded_exts = parse_excluded_exts(cfg.excluded_exts_text)
        self.queue: queue.Queue[tuple[str, str, int]] = queue.Queue()
        self.pending_uploads: set[str] = set()
        self.pending_deletes: set[str] = set()
        self.pending_lock = threading.Lock()
        self.client = FTPClient(cfg)
        self.stop_event = threading.Event()
        self.observer: Any = None
        self.worker: threading.Thread | None = None

    def log(self, message: str) -> None:
        line = f"{datetime.now().isoformat()} [{self.profile}] {message}\n"
        self.log_path.parent.mkdir(parents=True, exist_ok=True)
        with self.log_path.open("a", encoding="utf-8") as fh:
            fh.write(line)

    def rel_from_abs(self, abs_path: str) -> str | None:
        try:
            rel = Path(abs_path).resolve().relative_to(self.local_root).as_posix()
            rel = normalize_rel(rel)
            return rel or None
        except Exception:
            return None

    def is_excluded(self, rel_path: str) -> bool:
        rel = normalize_rel(rel_path)
        if not rel:
            return True
        if path_matches_excluded_dir(rel, self.excluded_dirs):
            return True
        if Path(rel).suffix.lower() in self.excluded_exts:
            return True
        return False

    def enqueue_upload(self, rel_path: str) -> None:
        rel = normalize_rel(rel_path)
        if not rel or self.is_excluded(rel):
            return
        with self.pending_lock:
            if rel in self.pending_uploads:
                return
            self.pending_uploads.add(rel)
        self.queue.put(("upload", rel, 0))

    def enqueue_delete(self, rel_path: str) -> None:
        rel = normalize_rel(rel_path)
        if not rel or self.is_excluded(rel):
            return
        with self.pending_lock:
            if rel in self.pending_deletes:
                return
            self.pending_deletes.add(rel)
        self.queue.put(("delete", rel, 0))

    def _release_pending(self, action: str, rel_path: str) -> None:
        with self.pending_lock:
            if action == "upload":
                self.pending_uploads.discard(rel_path)
            if action == "delete":
                self.pending_deletes.discard(rel_path)

    def _requeue(self, action: str, rel_path: str, retries: int, delay: float) -> None:
        def delayed() -> None:
            time.sleep(delay)
            if self.stop_event.is_set():
                self._release_pending(action, rel_path)
                return
            self.queue.put((action, rel_path, retries))

        threading.Thread(target=delayed, daemon=True).start()

    @staticmethod
    def _file_stable_fast(path: Path, window: float = 0.05) -> bool:
        try:
            s1 = path.stat()
            time.sleep(window)
            s2 = path.stat()
            return s1.st_size == s2.st_size and s1.st_mtime_ns == s2.st_mtime_ns
        except Exception:
            return False

    def _handle_upload(self, rel_path: str, retries: int) -> None:
        lp = self.local_root / rel_path
        rp = normalize_remote(posixpath.join(self.remote_root, rel_path))
        if not lp.exists() or not lp.is_file():
            self._release_pending("upload", rel_path)
            return
        if not self._file_stable_fast(lp, window=0.05) and retries < 20:
            self._requeue("upload", rel_path, retries + 1, 0.05)
            return
        try:
            if self.cfg.protocol in {"FTP", "FTPS"}:
                ftp = self.client.connect_ftp()
                try:
                    self._ensure_ftp_dirs(ftp, posixpath.dirname(rp))
                finally:
                    try:
                        ftp.quit()
                    except Exception:
                        pass
                self.client.upload_file_ftp(lp, rp)
            else:
                ssh, sftp = self.client.connect_sftp()
                try:
                    self._ensure_sftp_dirs(sftp, posixpath.dirname(rp))
                finally:
                    try:
                        sftp.close()
                    except Exception:
                        pass
                    try:
                        ssh.close()
                    except Exception:
                        pass
                self.client.upload_file_sftp(lp, rp)
            self.log(f"UPLOAD {rel_path}")
            self._release_pending("upload", rel_path)
        except PermissionError:
            if retries < 20:
                self._requeue("upload", rel_path, retries + 1, 0.05)
                return
            self._release_pending("upload", rel_path)
        except Exception as exc:
            if retries < 10:
                self.log(f"RETRY {rel_path}: {exc}")
                self._requeue("upload", rel_path, retries + 1, 0.2)
                return
            self._release_pending("upload", rel_path)
            self.log(f"ERROR_UPLOAD {rel_path}: {exc}")

    @staticmethod
    def _ensure_ftp_dirs(ftp: ftplib.FTP, remote_dir: str) -> None:
        cur = "/"
        for part in [x for x in normalize_remote(remote_dir).split("/") if x]:
            cur = posixpath.join(cur, part)
            try:
                ftp.mkd(cur)
            except Exception:
                pass

    @staticmethod
    def _ensure_sftp_dirs(sftp: Any, remote_dir: str) -> None:
        cur = "/"
        for part in [x for x in normalize_remote(remote_dir).split("/") if x]:
            cur = posixpath.join(cur, part)
            try:
                sftp.stat(cur)
            except Exception:
                try:
                    sftp.mkdir(cur)
                except Exception:
                    pass

    def _handle_delete(self, rel_path: str, retries: int) -> None:
        rp = normalize_remote(posixpath.join(self.remote_root, rel_path))
        try:
            if self.cfg.protocol in {"FTP", "FTPS"}:
                ftp = self.client.connect_ftp()
                try:
                    try:
                        ftp.delete(rp)
                    except Exception:
                        pass
                finally:
                    try:
                        ftp.quit()
                    except Exception:
                        pass
            else:
                ssh, sftp = self.client.connect_sftp()
                try:
                    try:
                        sftp.remove(rp)
                    except Exception:
                        pass
                finally:
                    try:
                        sftp.close()
                    except Exception:
                        pass
                    try:
                        ssh.close()
                    except Exception:
                        pass
            self.log(f"DELETE {rel_path}")
            self._release_pending("delete", rel_path)
        except Exception as exc:
            if retries < 10:
                self._requeue("delete", rel_path, retries + 1, 0.2)
                return
            self._release_pending("delete", rel_path)
            self.log(f"ERROR_DELETE {rel_path}: {exc}")

    def _worker_loop(self) -> None:
        while not self.stop_event.is_set():
            try:
                action, rel_path, retries = self.queue.get(timeout=0.1)
            except queue.Empty:
                continue
            if action == "upload":
                self._handle_upload(rel_path, retries)
            elif action == "delete":
                self._handle_delete(rel_path, retries)
            else:
                self._release_pending(action, rel_path)

    def start(self) -> None:
        if not self.local_root.exists() or not self.local_root.is_dir():
            raise RuntimeError(f"local_dir_not_found:{self.local_root}")
        handler = LocalChangeHandler(self)
        obs = Observer(timeout=0.05)
        obs.schedule(handler, str(self.local_root), recursive=True)
        obs.start()
        self.observer = obs
        self.worker = threading.Thread(target=self._worker_loop, daemon=True)
        self.worker.start()
        self.log(f"START local={self.local_root} remote={self.remote_root}")

    def run_forever(self) -> None:
        self.start()
        while not self.stop_event.is_set():
            time.sleep(1.0)


class LocalChangeHandler(FileSystemEventHandler):
    def __init__(self, manager: WatchdogSyncManager):
        super().__init__()
        self.manager = manager

    def on_created(self, event: FileSystemEvent) -> None:
        if getattr(event, "is_directory", False):
            return
        rel = self.manager.rel_from_abs(str(getattr(event, "src_path", "")))
        if rel:
            self.manager.enqueue_upload(rel)

    def on_modified(self, event: FileSystemEvent) -> None:
        if getattr(event, "is_directory", False):
            return
        rel = self.manager.rel_from_abs(str(getattr(event, "src_path", "")))
        if rel:
            self.manager.enqueue_upload(rel)

    def on_deleted(self, event: FileSystemEvent) -> None:
        if getattr(event, "is_directory", False):
            return
        rel = self.manager.rel_from_abs(str(getattr(event, "src_path", "")))
        if rel:
            self.manager.enqueue_delete(rel)

    def on_moved(self, event: FileMovedEvent) -> None:
        if getattr(event, "is_directory", False):
            return
        old_rel = self.manager.rel_from_abs(str(getattr(event, "src_path", "")))
        new_rel = self.manager.rel_from_abs(str(getattr(event, "dest_path", "")))
        if old_rel:
            self.manager.enqueue_delete(old_rel)
        if new_rel:
            self.manager.enqueue_upload(new_rel)


class MCPFTPServer:
    def __init__(self):
        cfg_path = Path(os.getenv("FTP_CONFIG_PATH", str(DEFAULT_CONFIG_PATH))).expanduser().resolve()
        self.store = ConfigStore(cfg_path)

    def tool_schemas(self) -> list[dict[str, Any]]:
        return [
            {
                "name": "ftp_config_show",
                "description": "Show active FTP/SFTP config with masked password",
                "inputSchema": {
                    "type": "object",
                    "properties": {
                        "profile": {"type": "string"},
                    },
                },
            },
            {
                "name": "ftp_config_save",
                "description": "Save partial FTP/SFTP config patch into ftp_konfig.json",
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
                "name": "ftp_connect_test",
                "description": "Check auth and list configured remote_dir",
                "inputSchema": {
                    "type": "object",
                    "properties": {
                        "profile": {"type": "string"},
                    },
                },
            },
            {
                "name": "ftp_remote_tree",
                "description": "List remote directory tree",
                "inputSchema": {
                    "type": "object",
                    "properties": {
                        "remote_dir": {"type": "string"},
                        "recursive": {"type": "boolean"},
                        "max_depth": {"type": "integer", "minimum": 1, "maximum": 20},
                        "profile": {"type": "string"},
                    },
                },
            },
            {
                "name": "ftp_upload",
                "description": "Upload one local file with automatic overwrite (remote path is derived from local_dir -> remote_dir)",
                "inputSchema": {
                    "type": "object",
                    "properties": {
                        "local_path": {"type": "string"},
                        "profile": {"type": "string"},
                    },
                    "required": ["local_path"],
                },
            },
            {
                "name": "ftp_write_text",
                "description": "Create or overwrite a remote text file from provided content",
                "inputSchema": {
                    "type": "object",
                    "properties": {
                        "remote_path": {"type": "string"},
                        "content": {"type": "string"},
                        "profile": {"type": "string"},
                    },
                    "required": ["remote_path", "content"],
                },
            },
            {
                "name": "ftp_write_file",
                "description": "Alias of ftp_write_text. Create or overwrite a remote text file from provided content",
                "inputSchema": {
                    "type": "object",
                    "properties": {
                        "remote_path": {"type": "string"},
                        "content": {"type": "string"},
                        "profile": {"type": "string"},
                    },
                    "required": ["remote_path", "content"],
                },
            },
            {
                "name": "ftp_put",
                "description": "Alias of ftp_write_text. Put text content into a remote file",
                "inputSchema": {
                    "type": "object",
                    "properties": {
                        "remote_path": {"type": "string"},
                        "text": {"type": "string"},
                        "content": {"type": "string"},
                        "profile": {"type": "string"},
                    },
                    "required": ["remote_path"],
                },
            },
            {
                "name": "write_remote_file",
                "description": "Alias of ftp_write_text. Write text content to a remote FTP/SFTP file",
                "inputSchema": {
                    "type": "object",
                    "properties": {
                        "remote_path": {"type": "string"},
                        "content": {"type": "string"},
                        "text": {"type": "string"},
                        "profile": {"type": "string"},
                    },
                    "required": ["remote_path"],
                },
            },
            {
                "name": "create_remote_file",
                "description": "Alias of ftp_write_text. Create or overwrite a remote FTP/SFTP text file",
                "inputSchema": {
                    "type": "object",
                    "properties": {
                        "remote_path": {"type": "string"},
                        "content": {"type": "string"},
                        "text": {"type": "string"},
                        "profile": {"type": "string"},
                    },
                    "required": ["remote_path"],
                },
            },
            {
                "name": "ftp_download",
                "description": "Download one remote file",
                "inputSchema": {
                    "type": "object",
                    "properties": {
                        "remote_path": {"type": "string"},
                        "local_path": {"type": "string"},
                        "profile": {"type": "string"},
                    },
                    "required": ["remote_path"],
                },
            },
            {
                "name": "ftp_delete",
                "description": "Delete one remote file or directory",
                "inputSchema": {
                    "type": "object",
                    "properties": {
                        "remote_path": {"type": "string"},
                        "recursive": {"type": "boolean"},
                        "profile": {"type": "string"},
                    },
                    "required": ["remote_path"],
                },
            },
            {
                "name": "ftp_sync",
                "description": "Upload local_dir to remote_dir with automatic overwrite",
                "inputSchema": {
                    "type": "object",
                    "properties": {
                        "profile": {"type": "string"},
                        "full_scan": {"type": "boolean"},
                    },
                },
            },
            {
                "name": "ftp_watchdog",
                "description": "Start, stop, or show status for continuous FTP/SFTP sync",
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

    def validate_deploy_paths(self, local_dir: Path, remote_dir: str) -> None:
        if not local_dir.exists() or not local_dir.is_dir():
            raise RuntimeError(f"local_dir_not_found:{local_dir}")

    def ensure_ftp_dirs(self, ftp: ftplib.FTP, remote_dir: str) -> None:
        cur = "/"
        for part in [x for x in normalize_remote(remote_dir).split("/") if x]:
            cur = posixpath.join(cur, part)
            try:
                ftp.mkd(cur)
            except Exception:
                pass

    def ensure_sftp_dirs(self, sftp, remote_dir: str) -> None:
        cur = "/"
        for part in [x for x in normalize_remote(remote_dir).split("/") if x]:
            cur = posixpath.join(cur, part)
            try:
                sftp.stat(cur)
            except Exception:
                try:
                    sftp.mkdir(cur)
                except Exception:
                    pass

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
        state = {
            "profile": profile_name,
            "pid": int(proc.pid),
            "started_at": now_iso(),
            "log_path": str(log_path),
        }
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

    def list_remote_tree_ftp(self, ftp: ftplib.FTP, remote_dir: str, recursive: bool, max_depth: int) -> list[dict[str, Any]]:
        out: list[dict[str, Any]] = []

        def walk(base: str, depth: int) -> None:
            for name, facts in ftp.mlsd(base):
                if name in {".", ".."}:
                    continue
                kind = (facts or {}).get("type", "unknown")
                full = normalize_remote(posixpath.join(base, name))
                out.append({"path": full, "type": kind, "facts": facts or {}})
                if recursive and kind == "dir" and depth < max_depth:
                    walk(full, depth + 1)

        walk(remote_dir, 1)
        return out

    def list_remote_tree_sftp(self, sftp, remote_dir: str, recursive: bool, max_depth: int) -> list[dict[str, Any]]:
        out: list[dict[str, Any]] = []

        def walk(base: str, depth: int) -> None:
            for item in sftp.listdir_attr(base):
                name = item.filename
                if name in {".", ".."}:
                    continue
                full = normalize_remote(posixpath.join(base, name))
                mode = item.st_mode
                kind = "dir" if stat.S_ISDIR(mode) else "file"
                out.append({"path": full, "type": kind, "size": int(item.st_size), "mtime": int(item.st_mtime)})
                if recursive and kind == "dir" and depth < max_depth:
                    walk(full, depth + 1)

        walk(remote_dir, 1)
        return out

    def delete_remote_ftp(self, ftp: ftplib.FTP, remote_path: str, recursive: bool) -> dict[str, Any]:
        deleted_files = 0
        deleted_dirs = 0

        def delete_dir(path: str) -> None:
            nonlocal deleted_files, deleted_dirs
            try:
                entries = list(ftp.mlsd(path))
            except Exception as exc:
                if not recursive:
                    raise RuntimeError(f"remote_directory_requires_recursive:{path}") from exc
                entries = []
            for name, facts in entries:
                if name in {".", ".."}:
                    continue
                child = normalize_remote(posixpath.join(path, name))
                if (facts or {}).get("type") == "dir":
                    delete_dir(child)
                else:
                    ftp.delete(child)
                    deleted_files += 1
            ftp.rmd(path)
            deleted_dirs += 1

        try:
            ftp.delete(remote_path)
            deleted_files += 1
        except Exception as file_exc:
            if not recursive:
                raise RuntimeError(f"remote_delete_failed:{remote_path}:{file_exc}") from file_exc
            delete_dir(remote_path)

        return {"deleted_files": deleted_files, "deleted_dirs": deleted_dirs}

    def delete_remote_sftp(self, sftp: Any, remote_path: str, recursive: bool) -> dict[str, Any]:
        deleted_files = 0
        deleted_dirs = 0

        def delete_dir(path: str) -> None:
            nonlocal deleted_files, deleted_dirs
            for item in sftp.listdir_attr(path):
                name = item.filename
                if name in {".", ".."}:
                    continue
                child = normalize_remote(posixpath.join(path, name))
                if stat.S_ISDIR(item.st_mode):
                    delete_dir(child)
                else:
                    sftp.remove(child)
                    deleted_files += 1
            sftp.rmdir(path)
            deleted_dirs += 1

        try:
            mode = sftp.stat(remote_path).st_mode
            if stat.S_ISDIR(mode):
                if not recursive:
                    raise RuntimeError(f"remote_directory_requires_recursive:{remote_path}")
                delete_dir(remote_path)
            else:
                sftp.remove(remote_path)
                deleted_files += 1
        except RuntimeError:
            raise
        except Exception as exc:
            raise RuntimeError(f"remote_delete_failed:{remote_path}:{exc}") from exc

        return {"deleted_files": deleted_files, "deleted_dirs": deleted_dirs}

    def upload_dir(self, cfg: FTPConfig, local_dir: Path, remote_dir: str, sync_mode: bool, full_scan: bool = False) -> dict[str, Any]:
        self.validate_deploy_paths(local_dir, remote_dir)

        uploaded = 0
        skipped = 0
        failed: list[str] = []
        excluded_dirs = parse_excluded_dirs(cfg.excluded_dirs_text)
        excluded_exts = parse_excluded_exts(cfg.excluded_exts_text)

        if cfg.protocol in {"FTP", "FTPS"}:
            ftp = FTPClient(cfg).connect_ftp()
            uploader = FTPClient(cfg)
            remote_meta: dict[str, tuple[int, int]] = {}
            if sync_mode and full_scan:
                for item in self.list_remote_tree_ftp(ftp, normalize_remote(remote_dir), recursive=True, max_depth=20):
                    if item.get("type") == "file":
                        facts = item.get("facts") or {}
                        size = int(facts.get("size") or 0)
                        mtime = int(facts.get("modify") or 0)
                        remote_meta[str(item["path"])] = (size, mtime)

            for lp in local_dir.rglob("*"):
                if not lp.is_file():
                    continue
                rel = lp.relative_to(local_dir).as_posix()
                if path_matches_excluded_dir(rel, excluded_dirs):
                    skipped += 1
                    continue
                if lp.suffix.lower() in excluded_exts:
                    skipped += 1
                    continue
                rp = normalize_remote(posixpath.join(remote_dir, rel))
                if sync_mode and rp in remote_meta:
                    l_size = int(lp.stat().st_size)
                    r_size = remote_meta[rp][0]
                    if l_size == r_size:
                        skipped += 1
                        continue
                self.ensure_ftp_dirs(ftp, posixpath.dirname(rp))
                try:
                    uploader.upload_file_ftp(lp, rp)
                    uploaded += 1
                except Exception as exc:
                    failed.append(f"{rp}: {exc}")
            ftp.quit()
        else:
            ssh, sftp = FTPClient(cfg).connect_sftp()
            uploader = FTPClient(cfg)
            remote_meta: dict[str, tuple[int, int]] = {}
            if sync_mode and full_scan:
                for item in self.list_remote_tree_sftp(sftp, normalize_remote(remote_dir), recursive=True, max_depth=20):
                    if item.get("type") == "file":
                        remote_meta[str(item["path"])] = (int(item.get("size") or 0), int(item.get("mtime") or 0))

            for lp in local_dir.rglob("*"):
                if not lp.is_file():
                    continue
                rel = lp.relative_to(local_dir).as_posix()
                if path_matches_excluded_dir(rel, excluded_dirs):
                    skipped += 1
                    continue
                if lp.suffix.lower() in excluded_exts:
                    skipped += 1
                    continue
                rp = normalize_remote(posixpath.join(remote_dir, rel))
                if sync_mode and rp in remote_meta:
                    l_size = int(lp.stat().st_size)
                    r_size = remote_meta[rp][0]
                    if l_size == r_size:
                        skipped += 1
                        continue
                self.ensure_sftp_dirs(sftp, posixpath.dirname(rp))
                try:
                    uploader.upload_file_sftp(lp, rp)
                    uploaded += 1
                except Exception as exc:
                    failed.append(f"{rp}: {exc}")
            sftp.close()
            ssh.close()

        return {
            "ok": len(failed) == 0,
            "uploaded_files": uploaded,
            "skipped_files": skipped,
            "failed_files": len(failed),
            "failed_sample": failed[:20],
            "local_dir": str(local_dir),
            "remote_dir": normalize_remote(remote_dir),
        }

    def call_tool(self, name: str, args: dict[str, Any]) -> dict[str, Any]:
        profile = str(args.get("profile") or "").strip() or None
        cfg, profile_name = self.store.load(profile)
        err = FTPClient(cfg).validate_auth()

        if name == "ftp_config_show":
            raw = self.store._load_raw()
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

        if name == "ftp_config_save":
            patch = args.get("patch")
            if not isinstance(patch, dict):
                raise RuntimeError("patch_must_be_object")
            saved, saved_profile = self.store.save_patch(patch, profile=profile)
            raw = self.store._load_raw()
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

        if name == "ftp_watchdog":
            action = str(args.get("action") or "").strip().lower()
            if action == "status":
                return self.watchdog_status(profile_name)
            if err:
                return {"ok": False, "server": SERVER_NAME, "profile": profile_name, "error": err}
            if action == "start":
                return self.watchdog_start(profile_name)
            if action == "stop":
                return self.watchdog_stop(profile_name)
            return {"ok": False, "server": SERVER_NAME, "profile": profile_name, "error": f"unsupported_watchdog_action:{action}"}

        if err:
            return {"ok": False, "server": SERVER_NAME, "error": err}

        if name == "ftp_sync":
            try:
                result = self.upload_dir(
                    cfg,
                    Path(cfg.local_dir).expanduser().resolve(),
                    cfg.remote_dir,
                    sync_mode=True,
                    full_scan=bool(args.get("full_scan") or False),
                )
                return {"server": SERVER_NAME, "profile": profile_name, **result}
            except Exception as exc:
                return {"ok": False, "server": SERVER_NAME, "profile": profile_name, "error": str(exc)}

        if name == "ftp_connect_test":
            try:
                if cfg.protocol in {"FTP", "FTPS"}:
                    ftp = FTPClient(cfg).connect_ftp()
                    ftp.nlst(cfg.remote_dir)
                    ftp.quit()
                else:
                    ssh, sftp = FTPClient(cfg).connect_sftp()
                    sftp.listdir(cfg.remote_dir)
                    sftp.close()
                    ssh.close()
                return {"ok": True, "server": SERVER_NAME, "profile": profile_name, "protocol": cfg.protocol, "host": cfg.host, "remote_dir": cfg.remote_dir}
            except Exception as exc:
                return {"ok": False, "server": SERVER_NAME, "error": str(exc)}

        if name == "ftp_remote_tree":
            remote_dir = normalize_remote(str(args.get("remote_dir") or cfg.remote_dir))
            recursive = bool(args.get("recursive") or False)
            max_depth = int(args.get("max_depth") or 3)
            try:
                if cfg.protocol in {"FTP", "FTPS"}:
                    ftp = FTPClient(cfg).connect_ftp()
                    entries = self.list_remote_tree_ftp(ftp, remote_dir, recursive=recursive, max_depth=max_depth)
                    ftp.quit()
                else:
                    ssh, sftp = FTPClient(cfg).connect_sftp()
                    entries = self.list_remote_tree_sftp(sftp, remote_dir, recursive=recursive, max_depth=max_depth)
                    sftp.close()
                    ssh.close()
                return {"ok": True, "server": SERVER_NAME, "profile": profile_name, "remote_dir": remote_dir, "entries_total": len(entries), "entries": entries}
            except Exception as exc:
                return {"ok": False, "server": SERVER_NAME, "error": str(exc)}

        if name == "ftp_download":
            remote_path = normalize_remote(str(args.get("remote_path") or ""))
            if remote_path == "/":
                raise RuntimeError("remote_path_missing")
            local_path = str(args.get("local_path") or "").strip()
            lp = Path(local_path).expanduser().resolve() if local_path else (Path(cfg.local_dir).expanduser().resolve() / Path(remote_path).name)
            lp.parent.mkdir(parents=True, exist_ok=True)
            try:
                if cfg.protocol in {"FTP", "FTPS"}:
                    ftp = FTPClient(cfg).connect_ftp()
                    with lp.open("wb") as fh:
                        ftp.retrbinary(f"RETR {remote_path}", fh.write)
                    ftp.quit()
                else:
                    ssh, sftp = FTPClient(cfg).connect_sftp()
                    sftp.get(remote_path, str(lp))
                    sftp.close()
                    ssh.close()
                return {"ok": True, "server": SERVER_NAME, "profile": profile_name, "remote_path": remote_path, "local_path": str(lp)}
            except Exception as exc:
                return {"ok": False, "server": SERVER_NAME, "error": str(exc)}

        if name == "ftp_delete":
            remote_path = normalize_remote(str(args.get("remote_path") or ""))
            if remote_path == "/":
                return {"ok": False, "server": SERVER_NAME, "profile": profile_name, "error": "refuse_delete_remote_root"}
            recursive = bool(args.get("recursive") or False)
            try:
                if cfg.protocol in {"FTP", "FTPS"}:
                    ftp = FTPClient(cfg).connect_ftp()
                    result = self.delete_remote_ftp(ftp, remote_path, recursive=recursive)
                    ftp.quit()
                else:
                    ssh, sftp = FTPClient(cfg).connect_sftp()
                    result = self.delete_remote_sftp(sftp, remote_path, recursive=recursive)
                    sftp.close()
                    ssh.close()
                return {
                    "ok": True,
                    "server": SERVER_NAME,
                    "profile": profile_name,
                    "remote_path": remote_path,
                    "recursive": recursive,
                    **result,
                }
            except Exception as exc:
                return {"ok": False, "server": SERVER_NAME, "profile": profile_name, "error": str(exc)}

        if name == "ftp_upload":
            local_path = str(args.get("local_path") or "").strip()
            if not local_path:
                return {"ok": False, "server": SERVER_NAME, "error": "local_path_required"}
            lp = Path(local_path).expanduser().resolve()
            if not lp.is_file():
                return {"ok": False, "server": SERVER_NAME, "error": f"local_file_not_found:{lp}"}
            local_root = Path(cfg.local_dir).expanduser().resolve()
            try:
                rel = lp.relative_to(local_root).as_posix()
            except Exception:
                return {"ok": False, "server": SERVER_NAME, "error": f"local_path_outside_local_dir:{lp}"}
            target = normalize_remote(posixpath.join(cfg.remote_dir, rel))
            upload_path, tmp_path, sanitized = sanitize_upload_file(lp)
            try:
                if cfg.protocol in {"FTP", "FTPS"}:
                    ftp = FTPClient(cfg).connect_ftp()
                    self.ensure_ftp_dirs(ftp, posixpath.dirname(target))
                    FTPClient(cfg).upload_file_ftp(upload_path, target)
                    ftp.quit()
                else:
                    ssh, sftp = FTPClient(cfg).connect_sftp()
                    self.ensure_sftp_dirs(sftp, posixpath.dirname(target))
                    FTPClient(cfg).upload_file_sftp(upload_path, target)
                    sftp.close()
                    ssh.close()
                return {
                    "ok": True,
                    "server": SERVER_NAME,
                    "profile": profile_name,
                    "local_path": str(lp),
                    "remote_path": target,
                    "sanitized_before_upload": sanitized,
                }
            except Exception as exc:
                return {"ok": False, "server": SERVER_NAME, "error": str(exc)}
            finally:
                if tmp_path is not None:
                    try:
                        tmp_path.unlink(missing_ok=True)
                    except Exception:
                        pass

        if name in {"ftp_write_text", "ftp_write_file", "ftp_put", "write_remote_file", "create_remote_file"}:
            remote_path = normalize_remote(str(args.get("remote_path") or "").strip())
            content = str(args.get("content") if args.get("content") is not None else args.get("text") or "")
            if not remote_path or remote_path == "/":
                return {"ok": False, "server": SERVER_NAME, "error": "remote_path_required"}
            tmp_path: Path | None = None
            try:
                suffix = Path(remote_path).suffix or ".txt"
                fd, tmp_name = tempfile.mkstemp(prefix="mcpftp_write_", suffix=suffix)
                os.close(fd)
                tmp_path = Path(tmp_name)
                tmp_path.write_text(content, encoding="utf-8")
                if cfg.protocol in {"FTP", "FTPS"}:
                    ftp = FTPClient(cfg).connect_ftp()
                    self.ensure_ftp_dirs(ftp, posixpath.dirname(remote_path))
                    FTPClient(cfg).upload_file_ftp(tmp_path, remote_path)
                    ftp.quit()
                else:
                    ssh, sftp = FTPClient(cfg).connect_sftp()
                    self.ensure_sftp_dirs(sftp, posixpath.dirname(remote_path))
                    FTPClient(cfg).upload_file_sftp(tmp_path, remote_path)
                    sftp.close()
                    ssh.close()
                return {
                    "ok": True,
                    "server": SERVER_NAME,
                    "profile": profile_name,
                    "remote_path": remote_path,
                    "bytes": len(content.encode("utf-8")),
                }
            except Exception as exc:
                return {"ok": False, "server": SERVER_NAME, "profile": profile_name, "error": str(exc)}
            finally:
                if tmp_path is not None:
                    try:
                        tmp_path.unlink(missing_ok=True)
                    except Exception:
                        pass

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
            "result": {
                "content": [{"type": "text", "text": text}],
                "isError": not bool(data.get("ok")),
            },
        }
    )


def run_stdio_server() -> int:
    server = MCPFTPServer()
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


def run_watchdog(profile: str | None) -> int:
    store = ConfigStore(Path(os.getenv("FTP_CONFIG_PATH", str(DEFAULT_CONFIG_PATH))).expanduser().resolve())
    cfg, profile_name = store.load(profile)
    err = FTPClient(cfg).validate_auth()
    if err:
        raise RuntimeError(err)
    manager = WatchdogSyncManager(cfg, profile_name, profile_log_path(profile_name))
    manager.run_forever()
    return 0


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(prog="MCP_FTP")
    parser.add_argument("--transport", default="stdio", help="MCP transport, only stdio is supported")
    parser.add_argument("--watchdog", action="store_true", help="run continuous sync worker")
    parser.add_argument("--profile", default=None, help="profile name from ftp_konfig.json")
    args = parser.parse_args(argv)
    if str(args.transport).strip().lower() != "stdio":
        raise RuntimeError(f"unsupported_transport:{args.transport}")
    if args.watchdog:
        return run_watchdog(args.profile)
    return run_stdio_server()


if __name__ == "__main__":
    socket.setdefaulttimeout(None)
    raise SystemExit(main())
