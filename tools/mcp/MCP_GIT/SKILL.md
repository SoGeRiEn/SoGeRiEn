# MCP_GIT

Локальный MCP-сервер для git-операций по аналогии с `MCP_FTP`. Управляет несколькими профилями репозиториев и поддерживает auto commit + push через watchdog.

## Конфигурация
- Файл: `git_konfig.json` рядом со скриптом (`format: local_tools_git_multi_v1`).
- Профили в `profiles.<name>`:
  - `local_dir` - абсолютный путь к локальной репе
  - `remote_url` - URL для push/pull (ssh:// или https://)
  - `remote_name` - имя remote, по умолчанию `origin`
  - `branch` - ветка push/pull, по умолчанию `main`
  - `commit_message_template` - сообщение для авто-коммита, `{ts}` подменяется на ISO-время
  - `commit_author_name` / `commit_author_email` - identity, прописывается через `git config`
  - `ssh_key_path` - путь к приватному ключу для SSH push (формирует `GIT_SSH_COMMAND`)
  - `ssh_command` - готовая команда `GIT_SSH_COMMAND` (приоритет выше `ssh_key_path`)
  - `debounce_seconds` - таймаут от последнего изменения до автокоммита (по умолчанию 5)

## Доступные тулзы
- `git_config_show` / `git_config_save`
- `git_status`, `git_log`, `git_remote_tree`
- `git_pull`, `git_push`, `git_commit`, `git_sync`
- `git_clone` - клон в пустой `local_dir`
- `git_init_push` - инициализация репы + первый коммит + push
- `git_watchdog` - `start` / `status` / `stop` для авто-синка по событиям файловой системы

## Запуск
- Сервер: `python MCP_GIT.py --transport stdio`
- Watchdog в фоне: `python MCP_GIT.py --watchdog --profile <name>`
- Установка в Claude Desktop: добавить в `claude_desktop_config.json`:
  ```
  "MCP_GIT": {
      "command": "C:\\MCP_V3\\Python3143\\python.exe",
      "args": ["C:\\MCP_V3\\MCP_GIT\\MCP_GIT.py", "--transport", "stdio"]
  }
  ```

## Поведение watchdog
- Слушает события из `local_dir` (рекурсивно), игнорирует `.git/`.
- При накоплении изменений ждёт `debounce_seconds` тишины, затем делает `git add -A` + `git commit` + `git push`.
- Логи пишутся в `watchdog_state/watchdog_<profile>.log`.
