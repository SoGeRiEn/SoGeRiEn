# MCP_SSH Skill

## Назначение
MCP server для выполнения SSH-команд через stdio JSON-RPC.

## Entry
- Process: `MCP_ssh`
- File: `MCP_SSH.py`
- Config: `ssh_konfig.json`

## Формат конфига
Используется только `local_tools_ssh_multi_v1`.

```json
{
  "format": "local_tools_ssh_multi_v1",
  "profiles": {
    "default": {
      "host": "",
      "port": 22,
      "username": "",
      "password": "",
      "key_path": "",
      "known_hosts_path": "",
      "strict_host_key_checking": false,
      "connect_timeout_sec": 20,
      "command_timeout_sec": 600,
      "allow_agent": false,
      "look_for_keys": false,
      "backend": ""
    }
  },
  "default_profile": "default"
}
```

Старый flat-конфиг не поддерживается.

## Выбор профиля
Приоритет:
1. `profile` в аргументах tool
2. `SSH_PROFILE`
3. `default_profile`

## Tools
- `ssh_config_show` - показать активный профиль и список профилей, пароль маскируется
- `ssh_config_save` - создать или изменить профиль в `profiles.<profile>`
- `ssh_config_delete` - удалить профиль, кроме последнего
- `ssh_connect_test` - TCP тест подключения к `host:port`
- `ssh_exec` - выполнить одну команду на удаленном хосте
- `ssh_exec_many` - выполнить несколько команд последовательно

## Пример создания или изменения профиля
```json
{
  "profile": "new",
  "patch": {
    "host": "1.2.3.4",
    "port": 22,
    "username": "root",
    "password": "secret",
    "backend": "paramiko",
    "connect_timeout_sec": 20,
    "command_timeout_sec": 600
  }
}
```

## Пример удаления профиля
```json
{
  "profile": "new",
  "new_default_profile": "default"
}
```

## Зависимости
- OpenSSH: системный `ssh`
- Paramiko backend: Python package `paramiko`
