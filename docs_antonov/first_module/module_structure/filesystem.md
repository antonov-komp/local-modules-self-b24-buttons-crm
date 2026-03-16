## Структура файлов модуля «BP Button Field»

Документ описывает итоговую файловую архитектуру модуля в `/local/modules/my.bpbutton/` после рефакторинга (этапы 1–4, TASK-REF-001 … TASK-REF-004).

---

### 1. Общий обзор

```text
/local/modules/my.bpbutton/
├── admin/                      # Административные скрипты и меню
│   ├── bpbutton_list.php       # Реестр настроек кнопок (только список)
│   ├── bpbutton_edit.php       # Форма редактирования одной настройки
│   ├── bpbutton_list_ajax.php # AJAX toggle_active
│   └── menu.php               # Пункт меню «Кнопки БП»
├── install/                   # Установка и ресурсы модуля
│   ├── index.php              # Класс модуля (CModule), install/uninstall
│   ├── version.php            # Версия модуля
│   ├── admin/                 # Прокси-файлы в /bitrix/admin/
│   │   ├── my_bpbutton_bpbutton_list.php
│   │   └── my_bpbutton_bpbutton_edit.php
│   └── js/my.bpbutton/        # JS Extension my_bpbutton.button
│       ├── button.js          # Точка входа
│       ├── button.state.js
│       ├── button.utils.js
│       ├── button.api.js
│       ├── button.sidepanel.js
│       ├── admin.list.js
│       └── entity-editor.js
├── lang/                      # Локализация
│   └── ru/
│       ├── admin/
│       ├── lib/
│       └── install/
├── lib/                       # D7-классы модуля
│   ├── UserField/
│   │   ├── BpButtonUserType.php    # Тонкая обёртка
│   │   └── ButtonHtmlRenderer.php  # Генерация HTML кнопки
│   ├── Service/
│   │   ├── ButtonService.php
│   │   ├── SettingsResolver.php     # Настройки отображения (BUTTON_TEXT, BUTTON_SIZE)
│   │   └── SettingsFormService.php # Валидация и сохранение формы админки
│   ├── Controller/
│   │   └── ButtonController.php
│   ├── Repository/
│   │   └── SettingsRepository.php  # (опц.) Централизованный доступ к SettingsTable
│   ├── Helper/
│   │   ├── SecurityHelper.php
│   │   └── CrmAccessChecker.php     # (опц.) Проверка прав CRM
│   ├── Internals/
│   │   ├── SettingsTable.php
│   │   └── LogsTable.php
│   └── EventHandler.php
└── include.php               # Точка подключения модуля
```

---

### 2. Назначение ключевых директорий и файлов

#### 2.1. `lib/`

**UserField/**
- `BpButtonUserType.php` — описание пользовательского типа `bp_button_field`, регистрация, делегирование в ButtonHtmlRenderer.
- `ButtonHtmlRenderer.php` — генерация HTML кнопки с `data-*` атрибутами, использует SettingsResolver для BUTTON_TEXT, BUTTON_SIZE.

**Service/**
- `ButtonService.php` — бизнес-логика: getSidePanelConfig, logClick, работа с SettingsTable и LogsTable.
- `SettingsResolver.php` — получение настроек отображения (BUTTON_TEXT, BUTTON_SIZE) для UserField, с кешированием.
- `SettingsFormService.php` — валидация и сохранение формы админки, toggleActive, getIdByFieldId.

**Controller/**
- `ButtonController.php` — Bitrix\Main\Engine\Controller, getConfigAction: проверка сессии/прав, вызов ButtonService, возврат JSON.

**Repository/** (опционально)
- `SettingsRepository.php` — централизованный доступ к SettingsTable для чтения.

**Helper/**
- `SecurityHelper.php` — безопасное логирование, маскировка чувствительных данных.
- `CrmAccessChecker.php` — проверка прав на чтение CRM-сущности (entityId, elementId).

**Internals/**
- `SettingsTable.php` — D7 ORM для `my_bpbutton_settings`.
- `LogsTable.php` — D7 ORM для `my_bpbutton_logs`.

**EventHandler.php** — регистрация user type, OnAfterUserFieldAdd, OnUserFieldDelete.

#### 2.2. `admin/`

- `bpbutton_list.php` — реестр настроек кнопок (CAdminUiList), фильтры, сортировка.
- `bpbutton_edit.php` — форма редактирования одной записи, использует SettingsFormService.
- `bpbutton_list_ajax.php` — обработчик AJAX для toggle_active.
- `menu.php` — пункт меню «Настройки → Мои модули → Кнопки БП».

#### 2.3. `install/`

- `index.php` — класс модуля, InstallDB, InstallEvents, InstallFiles, InstallJS, InstallMenu.
- `version.php` — версия и дата.
- `admin/` — прокси-файлы, копируемые в `/bitrix/admin/`.
- `js/my.bpbutton/` — JS Extension: button.js (точка входа), модули state, utils, api, sidepanel, admin.list, entity-editor.

#### 2.4. `lang/`

Локализация для admin, lib, install.

---

### 3. Прокси-admin-файлы в `/bitrix/admin/`

Bitrix ожидает admin-скрипты по пути `/bitrix/admin/*.php`. Модуль копирует прокси:

- `my_bpbutton_bpbutton_list.php` → `require modules/my.bpbutton/admin/bpbutton_list.php`
- `my_bpbutton_bpbutton_edit.php` → `require modules/my.bpbutton/admin/bpbutton_edit.php`

---

### 4. JS Extension

- **ID:** `my.bpbutton.button`
- **Точка входа:** `install/js/my.bpbutton/button.js`
- **Модули:** button.state.js, button.utils.js, button.api.js, button.sidepanel.js, admin.list.js, entity-editor.js
- **Подключение:** `Extension::load('my.bpbutton.button')` в карточке CRM / Entity Editor

---

### 5. API

- **Endpoint:** `/bitrix/services/my.bpbutton/button/ajax.php`
- **Метод:** `ButtonController::getConfigAction`
- **Формат ответов:** см. `api/response_format.md`

---

### 6. Таблица namespaces и путей

| Класс | Namespace | Путь к файлу |
|-------|-----------|--------------|
| BpButtonUserType | My\BpButton\UserField | lib/UserField/BpButtonUserType.php |
| ButtonHtmlRenderer | My\BpButton\UserField | lib/UserField/ButtonHtmlRenderer.php |
| ButtonService | My\BpButton\Service | lib/Service/ButtonService.php |
| SettingsResolver | My\BpButton\Service | lib/Service/SettingsResolver.php |
| SettingsFormService | My\BpButton\Service | lib/Service/SettingsFormService.php |
| ButtonController | My\BpButton\Controller | lib/Controller/ButtonController.php |
| EventHandler | My\BpButton | lib/EventHandler.php |
| SettingsTable | My\BpButton\Internals | lib/Internals/SettingsTable.php |
| LogsTable | My\BpButton\Internals | lib/Internals/LogsTable.php |
| SecurityHelper | My\BpButton\Helper | lib/Helper/SecurityHelper.php |
| SettingsRepository | My\BpButton\Repository | lib/Repository/SettingsRepository.php |
| CrmAccessChecker | My\BpButton\Helper | lib/Helper/CrmAccessChecker.php |

**Проверка:** Все пути соответствуют автозагрузке в `install/index.php` (Loader::registerAutoLoadClasses). Расхождений нет.
