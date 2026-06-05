# MCP_MYSQL Skill

## Назначение
MCP server для MySQL через stdio JSON-RPC (MCP).

## Entry
- Process: `MCP_mysql`
- File: `MCP_MYSQL.py`

## Tools
- `mysql_config_show` - показать текущий конфиг (пароль маскируется)
- `mysql_config_save` - сохранить patch в `mysql_konfig.json`
- `mysql_connect_test` - тест подключения и проверочный `select`
- `mysql_query` - выполнить SQL запрос

## Пример patch для конфига
```json
{
  "patch": {
    "host": "127.0.0.1",
    "port": 3306,
    "database": "app",
    "username": "root",
    "password": "secret",
    "charset": "utf8mb4",
    "connect_timeout_sec": 10
  }
}
```

## Зависимости
- `PyMySQL` (если не установлен, `mysql_connect_test`/`mysql_query` вернут ошибку)
