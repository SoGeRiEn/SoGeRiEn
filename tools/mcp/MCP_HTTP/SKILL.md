# MCP_HTTP Skill

## Назначение
MCP server для HTTP-запросов через stdio JSON-RPC (MCP).

## Entry
- Process: `MCP_http`
- File: `MCP_HTTP.py`

## Tools
- `http_config_show` - показать текущий конфиг
- `http_config_save` - сохранить patch в `http_konfig.json`
- `http_connect_test` - GET запрос на `ping_url` из конфига
- `http_request` - выполнить произвольный HTTP запрос

## Пример patch для конфига
```json
{
  "patch": {
    "ping_url": "https://httpbin.org/get",
    "timeout_sec": 15,
    "follow_redirects": true
  }
}
```

## Пример запроса
```json
{
  "url": "https://httpbin.org/post",
  "method": "POST",
  "headers": {
    "Content-Type": "application/json"
  },
  "body": "{\"hello\":\"world\"}"
}
```

## Зависимости
- HTTP client: `aiohttp`
