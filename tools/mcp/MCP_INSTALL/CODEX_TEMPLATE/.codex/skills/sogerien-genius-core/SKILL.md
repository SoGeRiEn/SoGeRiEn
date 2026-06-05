---
name: sogerien-genius-core
description: Build and refactor backend logic for Sogerien projects (PHP services, provider APIs, PostgreSQL JSONB, TableRenderer/Forms UI). Use for APIs, CRM/admin pages, proxy systems, auth/access flows, and for replacing overcomplicated code with reusable universal Sogerien components.
---

# Core Principle

Минимум кода, максимум результата.

Любое решение:
- закрывает задачу полностью;
- проще типового MVC-подхода;
- переиспользуется без дублирования;
- масштабируется через расширение JSON-структур, а не через разрастание схемы.

# Реальная Архитектура Проекта (обязательно)

Используй фактические контракты текущего проекта:

1. Bootstrap и entrypoints:
- `admin/index.php` и `api/index.php` подключают `sogerien/Sogerien.php`.
- Инициализация сервисов, БД, ключей и роутов делается в entrypoint.

2. Service locator:
- Все сервисы вызываются через `Sogerien::ServiceName()`.
- Регистрация новых сервисов только через `sogerien/Sogerien.php`.

3. Routing:
- File-based routing через `Sogerien::Routes()->add_template(...)->template()`.
- Страницы в `sogerien/page/*.php`.
- В проекте Pages сейчас в основном файловые, а не class-наследники.

4. API entry:
- `api/index.php` принимает `method` в формате `Service.method` и `data`.
- Текущий контракт ответа: `{"result":true|false, ...}`.
- В текущей реализации роутер API явно матчится минимум на `cyberyozh` и `infaticaio`.

# Universal Data Model (JSONB-first)

Основная модель хранения:
- таблица `sogerien`;
- ключевые поля: `table_name`, `name`, `table_index` JSONB, `table_value` JSONB, `status`, timestamps.

Правила:
- тип сущности определяется `table_name`;
- индексы для поиска в `table_index`;
- payload в `table_value`;
- soft-delete через `status`, без физического DELETE.

Статусы, которые реально используются:
- `actual`
- `archive`
- `delete`

Конфигурации прав:
- `table_name = 'config'`, `name = 'rules'`.
- `rules` содержит `roles` + permission entries.
- membership-коллекции в правах хранить как set-объекты: `{"admin": true}`.

Примечание по схеме:
- В `manual_database.php` есть также `orders` и `services` как отдельные таблицы.
- Для нового кода приоритет сохраняется за universal-table подходом, если нет прямой причины идти в отдельную таблицу.

# Security, Auth, Access

Используй только существующий стек:
- `AEAD` (libsodium, XChaCha20-Poly1305);
- `AccessToken` для токенов и cookie;
- `AccessCheck`/`Roles` для проверки прав.

Токен-пэйлоад совместим с текущим кодом:
- `user_id`
- `user_group`
- fingerprint/network поля (формируются через `InputRequest`).

Требования:
- SQL только через prepared statements (`DbController`/PDO prepare).
- HTML-вывод экранировать: `htmlspecialchars(..., ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')`.

# Reuse Map (не изобретать новое без причины)

Перед новым кодом сначала расширяй существующее:

1. Data/Auth:
- `Users`, `Roles`, `AccessCheck`, `AccessToken`, `AEAD`, `DbController`.

2. API providers:
- `API()->Cyberyozh()`
- `API()->InfaticaIo()`
- `API()->Proxysmartorg()`
- `API()->Iproxyonline()`
- `API()->Hypeproxyio()`
- `API()->Dataimpulsecom()`
- `API()->Stripe()`

3. UI:
- `TableRenderer` (+ inline multiselect save, filters, export, state in cookies)
- `Forms` (+ `show_if`, `linked_select`, calculator, order lines, CRUD modals)
- `Page` (+ shared assets/menu/lang integration)

4. Performance/cache:
- `Cache` (JSON file cache with update timestamps)
- `TableRendererCache` (render-cache HTML с atomic write)

5. i18n:
- `Lang` (runtime language resolution + cache files in `sogerien/i18n`).

# API/Page Response Contracts

В проекте используются два рабочих формата:

1. Внутренние page AJAX handlers:
- `{"ok": true|false, "error": "...", ...}`

2. `api/index.php` entry:
- `{"result": true|false, "data": ..., "error": "..."}`

Сохраняй существующий контракт для конкретного слоя, не смешивай форматы без необходимости.

# Decision Algorithm

Перед реализацией:

1. Можно решить через существующий сервис Sogerien? Делай так.
2. Можно расширить универсальный сервис (`Users`, `Roles`, `TableRenderer`, `Forms`, `API*`)? Расширяй.
3. Можно убрать слой/абстракцию? Убирай.
4. Можно хранить в `table_value`/`table_index` без новой схемы? Храни там.
5. Можно кешировать файлом быстрее и проще? Используй `Cache`/`TableRendererCache`.

Если решение становится сложнее, чем нужно для текущего контракта, упрощай.

# Extension Patterns

1. Новый сервис:
- создать `final class`;
- добавить strict types;
- зарегистрировать singleton-геттер в `sogerien/Sogerien.php`.

2. Новый admin endpoint:
- добавить `Routes()->add_template('url', '/page/file.php')`;
- сделать тонкий page-файл;
- бизнес-логику вынести в сервис.

3. Новое право доступа:
- добавить key в `config.rules` через `Roles`;
- использовать roles/users_id как set-объекты;
- проверять через `AccessCheck`.

4. Новое табличное UI-действие:
- сначала попытаться через `TableRenderer` formatters/actions/column_cell_types;
- если нужен inline-save, придерживаться текущей `action=update` механики.

# Anti-Patterns (запрещено)

- ORM.
- Laravel/Symfony-style abstraction layers.
- Дублирование одной и той же бизнес-логики в Page и API одновременно.
- SQL внутри frontend JS.
- Новые отдельные таблицы без доказанной необходимости.
- Кастомный UI-механизм, если задача решается `Forms`/`TableRenderer`.

# Output Style

- Кратко.
- Структурировано.
- Без воды.
- Сразу рабочее решение.
<!-- AUTO_DEPLOY_LOCAL_CHANGES_RULE -->
## Обязательное правило деплоя (глобально)
- После любого локального изменения файла (создание, редактирование, переименование) обязательно загрузить измененные файлы на сервер в рамках этой же задачи.
- Нельзя завершать задачу только локальными изменениями.
- Использовать tp_upload (точечная загрузка) или tp_sync (пакетная синхронизация).
