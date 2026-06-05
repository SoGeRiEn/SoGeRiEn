# MCP_PHP Skill

## Назначение
MCP server для статической валидации PHP-файлов без php binary (`php -l` не требуется).

## Entry
- Process: `MCP_php`
- File: `MCP_PHP.py`

## Tools
- `php_config_show` - показать текущий конфиг валидатора
- `php_config_save` - сохранить patch в `php_konfig.json`
- `php_validate_file` - проверить один PHP-файл
- `php_validate_many` - проверить список PHP-файлов

## Что проверяется
- наличие PHP open tag (`<?php` / `<?=`)
- баланс `() [] {}`
- незакрытые `'`/`"`
- незакрытые block comments `/* ... */`
- незакрытые heredoc/nowdoc
- предупреждение при short open tag `<?`

## Ограничение
Это статическая эвристика. Без `php -l` 100% парсинг-гарантии нет.
