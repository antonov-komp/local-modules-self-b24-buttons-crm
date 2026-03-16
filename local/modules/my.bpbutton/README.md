# Модуль my.bpbutton — Кнопка бизнес-процесса

## Назначение

Модуль добавляет пользовательский тип поля `bp_button_field` для CRM и смарт-процессов Bitrix24. Поле отображается как интерактивная кнопка, при нажатии открывающая SidePanel с настраиваемым URL (форма, отчёт, внешний сервис). Настройки кнопок централизованы в административном реестре.

## Установка

**Требования:** PHP 8.x, Bitrix24 коробочная версия.

**Шаги:**
1. Скопировать модуль в `/local/modules/my.bpbutton/`
2. Установить через Marketplace или «Настройки → Настройки продукта → Модули»
3. После установки: создать пользовательское поле типа «Кнопка управления логикой» в нужной сущности CRM
4. Настроить кнопку в админке: «Настройки → Мои модули → Кнопки БП»

## Основные точки входа

| Сценарий | Точка входа | Компонент |
|----------|-------------|-----------|
| Рендеринг кнопки в CRM | BpButtonUserType::getPublicViewHTML | ButtonHtmlRenderer |
| Клик по кнопке (API) | /bitrix/services/my.bpbutton/button/ajax.php | ButtonController::getConfigAction |
| Список настроек | my_bpbutton_bpbutton_list.php | bpbutton_list.php |
| Форма редактирования | my_bpbutton_bpbutton_edit.php | bpbutton_edit.php |
| Inline toggle активности | bpbutton_list_ajax.php | SettingsFormService::toggleActive |

## Структура модуля (кратко)

```
lib/
├── UserField/     — BpButtonUserType, ButtonHtmlRenderer
├── Service/       — ButtonService, SettingsResolver, SettingsFormService
├── Controller/    — ButtonController
├── Repository/    — SettingsRepository (опц.)
├── Helper/        — SecurityHelper, CrmAccessChecker (опц.)
└── Internals/     — SettingsTable, LogsTable

admin/
├── bpbutton_list.php       — Список настроек
├── bpbutton_edit.php       — Форма редактирования
├── bpbutton_list_ajax.php  — AJAX toggle_active
└── menu.php

install/
├── admin/                  — Прокси-файлы в /bitrix/admin/
│   ├── my_bpbutton_bpbutton_list.php
│   └── my_bpbutton_bpbutton_edit.php
└── js/my.bpbutton/         — JS Extension my_bpbutton.button
    ├── button.js            — Точка входа
    ├── button.state.js
    ├── button.utils.js
    ├── button.api.js
    ├── button.sidepanel.js
    ├── admin.list.js
    └── entity-editor.js
```

## JS Extension

Подключение: `Extension::load('my.bpbutton.button')` — загружает `button.js` и модули. Инициализация при клике по кнопке с `data-field-id`, `data-entity-id`, `data-element-id`.

## Документация

Полная документация: `docs_antonov/first_module/`

- `overview.md` — обзор модуля
- `architecture/backend_d7.md` — архитектура backend
- `architecture/layers-diagram.md` — схема слоёв и потоков данных
- `api/response_format.md` — формат ответов API
- `onboarding-checklist.md` — чек-лист для новых разработчиков
