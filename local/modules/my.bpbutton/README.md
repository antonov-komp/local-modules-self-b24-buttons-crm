# Модуль my.bpbutton — Кнопка бизнес-процесса

## Назначение

Модуль добавляет пользовательский тип поля `bp_button_field` для CRM и смарт-процессов **коробочного Bitrix24**. Поле отображается как интерактивная кнопка и может выполнять один из нескольких сценариев: открыть URL в SidePanel либо запустить бизнес-процесс напрямую или с параметром.

Облачная версия Bitrix24 в рамках этого модуля не поддерживается.

## Текущие режимы действия

На baseline `28f73db` реализованы четыре `ACTION_TYPE`:

| ACTION_TYPE | Назначение |
|---|---|
| `url` | Открыть настроенный URL в SidePanel |
| `bp_launch` | Запустить выбранный бизнес-процесс |
| `bp_launch_with_params` | Запросить строковое значение и передать его в параметр БП |
| `bp_launch_with_button_params` | Показать набор вариантов-кнопок, передать выбранное значение и запустить БП |

## Установка

**Требования:** PHP 8.x, Bitrix24 коробочная версия.

**Шаги:**
1. Скопировать модуль в `/local/modules/my.bpbutton/`.
2. Установить через «Настройки → Настройки продукта → Модули».
3. Создать пользовательское поле типа «Кнопка управления логикой» в нужной сущности CRM/смарт-процесса.
4. Настроить кнопку в административном реестре модуля.

## Основные точки входа

| Сценарий | Точка входа | Компонент |
|---|---|---|
| Регистрация user type | `EventHandler::onUserTypeBuildList()` | `BpButtonUserType` |
| Рендеринг кнопки | `BpButtonUserType::getPublicViewHTML()` | `ButtonHtmlRenderer` |
| AJAX runtime | `/local/modules/my.bpbutton/tools/button_ajax.php` | `ButtonController` |
| Runtime-логика | `ButtonController` | `ButtonService` |
| Список настроек | `my_bpbutton_bpbutton_list.php` | `admin/bpbutton_list.php` |
| Форма редактирования | `my_bpbutton_bpbutton_edit.php` | `admin/bpbutton_edit.php` |
| Inline toggle активности | `bpbutton_list_ajax.php` | `SettingsFormService::toggleActive()` |

## Backend-цепочка

```text
EventHandler
    -> BpButtonUserType
    -> ButtonHtmlRenderer
    -> JS runtime
    -> ButtonController
    -> ButtonService
    -> SettingsRepository / Bizproc / LogsTable
```

Текущий `ButtonService` маршрутизирует все четыре типа действий. После этапа стабилизации планируется отдельный архитектурный рефакторинг action-specific логики.

## Структура модуля (кратко)

```text
lib/
├── Controller/    — ButtonController
├── Helper/        — SecurityHelper, CrmAccessChecker
├── Internals/     — SettingsTable, LogsTable
├── Repository/    — SettingsRepository
├── Service/       — ButtonService, SettingsResolver, SettingsFormService,
│                    BpTemplateResolver, EntityNameResolver
├── UserField/     — BpButtonUserType, ButtonHtmlRenderer
└── EventHandler.php

admin/
├── bpbutton_list.php
├── bpbutton_edit.php
├── bpbutton_list_ajax.php
└── menu.php

install/
├── admin/
└── js/my.bpbutton/
    ├── button.js
    ├── button.state.js
    ├── button.utils.js
    ├── button.api.js
    ├── button.sidepanel.js
    ├── admin.list.js
    └── entity-editor.js

tools/
├── button_ajax.php
└── check_hide_bp_tab.php
```

## JS runtime

`Extension::load('my_bpbutton.button')` подключает runtime-модули кнопки.

Основной frontend entrypoint — `button.js`. Сетевой слой вынесен в `button.api.js`, который обращается к:

```text
/local/modules/my.bpbutton/tools/button_ajax.php
```

Backend не должен доверять `userId`, полученному из DOM; фактический пользователь и права проверяются серверной стороной.

## Текущий этап развития

После восстановления проекта 29.10.2026 baseline зафиксирован на commit `28f73db`.

Следующая задача — `TASK-017-stabilization-baseline.md`. До её завершения новые action type не добавляются.

Основные цели TASK-017:

- regression/smoke четырёх существующих режимов;
- исправление найденных технических дефектов;
- проверка логирования и cache semantics;
- фиксация стратегии миграций БД без `ALTER TABLE` в runtime;
- подготовка TASK-018 Action Architecture.

## Документация

Полная документация: `docs_antonov/first_module/`.

- `overview.md` — обзор модуля и текущих режимов;
- `restart-2026-10-29.md` — контрольная точка восстановления и roadmap;
- `tasks/TASK-017-stabilization-baseline.md` — текущая задача;
- `architecture/backend_d7.md` — backend-архитектура;
- `architecture/layers-diagram.md` — схема слоёв и потоков данных;
- `api/response_format.md` — формат API-ответов;
- `onboarding-checklist.md` — чек-лист разработчика.
