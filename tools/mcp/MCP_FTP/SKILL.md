# MCP_FTP Skill

## Назначение
MCP server для FTP/FTPS/SFTP через stdio JSON-RPC.

## Entry
- Process: `MCP_ftp`
- File: `MCP_FTP.py`
- Config: `ftp_konfig.json`

## Формат конфига
Используется только `local_tools_ftp_multi_v1`.

```json
{
  "format": "local_tools_ftp_multi_v1",
  "profiles": {
    "default": {
      "protocol": "FTP",
      "host": "",
      "port": 21,
      "username": "",
      "password": "",
      "timeout": 60,
      "retries": 3,
      "local_dir": ".",
      "remote_dir": "/",
      "excluded_dirs_text": "",
      "excluded_exts_text": ""
    }
  },
  "default_profile": "default"
}
```

Старый flat-конфиг не поддерживается.

## Выбор профиля
Приоритет:
1. `profile` в аргументах tool
2. `FTP_PROFILE`
3. `default_profile`

## Tools
- `ftp_config_show` - показать активный профиль и список профилей, пароль маскируется
- `ftp_config_save` - сохранить patch в `profiles.<profile>`
- `ftp_connect_test` - проверить подключение и `remote_dir`
- `ftp_remote_tree` - показать дерево удаленной директории
- `ftp_upload` - загрузить один файл, remote path строится из `local_dir -> remote_dir`
- `ftp_download` - скачать один удаленный файл
- `ftp_delete` - удалить удаленный файл или папку, для папок нужен `recursive: true`
- `ftp_sync` - загрузить весь `local_dir` в `remote_dir`
- `ftp_watchdog` - `start`, `status`, `stop` для непрерывной синхронизации

## Удаление
Пример удаления файла:
```json
{
  "profile": "proxymint.com",
  "remote_path": "/proxymint.com/assets/old.js"
}
```

Пример удаления папки:
```json
{
  "profile": "proxymint.com",
  "remote_path": "/proxymint.com/cache",
  "recursive": true
}
```

Корень `/` удалять нельзя.

## Правила загрузки
- `ftp_upload` принимает `local_path`.
- Файл должен лежать внутри `local_dir` активного профиля.
- Удаленный путь строится автоматически: `remote_dir + relative_path(local_path from local_dir)`.
- Перезапись на сервере автоматическая, без подтверждений.
- Текстовые файлы перед загрузкой очищаются от UTF-8 BOM и невидимых символов.

## Continuous sync
- Используй `ftp_watchdog` для настоящего sync-режима.
- Watchdog работает отдельным процессом с бесконечным циклом.
- Если watchdog запущен, не запускай ручной `ftp_upload` или `ftp_sync` по тому же профилю.
