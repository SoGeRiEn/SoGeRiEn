# MCP_MSSQL Skill

## Назначение
MCP server для Microsoft SQL Server через stdio JSON-RPC (MCP).

## Entry
- Process: `MCP_mssql`
- File: `MCP_MSSQL.py`

## Tools
- `mssql_config_show` - показать текущий конфиг (пароль маскируется)
- `mssql_config_save` - сохранить patch в `mssql_konfig.json`
- `mssql_connect_test` - тест подключения и проверочный `select`
- `mssql_query` - выполнить SQL запрос

## Пример patch для конфига
```json
{
  "patch": {
    "host": "127.0.0.1",
    "port": 1433,
    "database": "master",
    "username": "",
    "password": "",
    "driver": "ODBC Driver 18 for SQL Server",
    "encrypt": true,
    "trust_server_certificate": true,
    "connect_timeout_sec": 10
  }
}
```

## Зависимости
- `pyodbc` (если не установлен, `mssql_connect_test`/`mssql_query` вернут ошибку)
- установленный ODBC драйвер SQL Server (например, `ODBC Driver 18 for SQL Server`)
