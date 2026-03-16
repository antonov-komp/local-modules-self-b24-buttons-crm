## Структура файлов модуля «BP Button Field»

Документ описывает целевую файловую архитектуру модуля в `/local/modules/my.bpbutton/` и связанных директориях Bitrix24 (admin, js, css и т.п.).

Это **референс‑структура**: фактическая реализация может отличаться в деталях, но должна сохранять общую логику и разделение слоёв.

---

### 1. Общий обзор

```text
/local/modules/my.bpbutton/
├── admin/                  # Административные скрипты и меню
│   ├── bpbutton_list.php   # Реестр настроек кнопок (список)
│   ├── bpbutton_edit.php   # Форма редактирования одной настройки (опционально SidePanel)
│   ├── menu.php            # Подключение пункта меню «Кнопки БП»
│   └── .access.php         # Права доступа к admin‑скриптам
├── install/                # Установка и удаление модуля
│   ├── index.php           # Класс модуля (CModule) и сценарий install/uninstall
│   ├── step.php            # Шаги мастера установки (если используются)
│   └── version.php         # Версия модуля
├── lang/                   # Локализация
│   ├── ru/
│   │   ├── admin/
│   │   │   ├── bpbutton_list.php
│   │   │   └── bpbutton_edit.php
│   │   ├── lib/
│   │   │   ├── eventhandler.php
│   │   │   └── userfield/
│   │   │       └── bpbuttonusertype.php
│   │   └── install/
│   │       └── index.php
│   └── .description.php    # Описание модуля (название, описание, партнёр)
├── lib/                    # D7‑классы модуля
│   ├── Internals/          # ORM‑сущности
│   │   ├── SettingsTable.php
│   │   └── LogsTable.php   # планируемая сущность логов
│   ├── Service/            # Сервисный слой
│   │   └── ButtonService.php
│   ├── Controller/         # AJAX‑контроллеры (Engine)
│   │   └── ButtonController.php
│   ├── UserField/          # Пользовательский тип поля
│   │   └── BpButtonUserType.php
│   └── EventHandler.php    # Регистрация user type и обработчиков событий
├── tools/                  # Вспомогательные скрипты (если нужны)
│   └── bpbutton_test.php   # Технические/отладочные endpoints
├── options.php             # Страница настроек модуля (если предусматривается)
└── include.php             # Точка подключения модуля, автозагрузка и т.п.
```

Дополнительно, вне директории модуля могут располагаться:

```text
/bitrix/admin/my_bpbutton_list.php      # прокси‑файл admin для модуля
/bitrix/admin/my_bpbutton_edit.php

/local/js/my.bpbutton/                  # JS‑расширения модуля (если используются)
└── button.js

/local/css/my.bpbutton/                 # CSS‑стили для UI (по минимуму, опираясь на UI Kit)
└── button.css
```

---

### 2. Назначение ключевых директорий и файлов

#### 2.1. `lib/`

- `Internals/SettingsTable.php`
  - D7‑определение таблицы `my_bpbutton_settings`.
  - Содержит карту полей (`FIELD_ID`, `HANDLER_URL`, `TITLE`, `WIDTH`, `ACTIVE`, даты и т.п.).

- `Internals/LogsTable.php`
  - D7‑определение таблицы `my_bpbutton_logs` (будущая версия).

- `Service/ButtonService.php`
  - Бизнес‑логика вокруг настроек кнопок:
    - получение конфигурации по `FIELD_ID`;
    - подготовка данных для SidePanel;
    - (расширение) логирование кликов.

- `Controller/ButtonController.php`
  - Реализация `Bitrix\Main\Engine\Controller`:
    - `getConfigAction`;
    - (расширение) `logClickAction` и др.

- `UserField/BpButtonUserType.php`
  - Описание пользовательского типа `bp_button_field`:
    - регистрация типа (через `OnUserTypeBuildList`);
    - визуальные методы (`GetPublicViewHTML` и др.).

- `EventHandler.php`
  - Обработчики событий ядра:
    - регистрация user type;
    - `OnAfterUserFieldAdd` (создание записи в `SettingsTable`);
    - `OnUserFieldDelete` (очистка настроек/логов).

#### 2.2. `admin/`

- `bpbutton_list.php`
  - Реестр настроек кнопок:
    - таблица на базе `CAdminList`/`CAdminUiList`;
    - фильтры, сортировка, массовые действия.

- `bpbutton_edit.php`
  - Форма редактирования одной записи `my_bpbutton_settings`.

- `menu.php`
  - Регистрация пункта меню «Кнопки БП» в разделе «Настройки → Мои модули».

- `.access.php`
  - Ограничение доступа к admin‑файлам по правам модуля.

#### 2.3. `install/`

- `index.php`
  - Класс модуля (`class my_bpbutton extends CModule`):
    - методы `DoInstall`, `DoUninstall`;
    - регистрация/снятие обработчиков событий;
    - создание/удаление таблиц БД;
    - копирование admin‑файлов‑прокси в `/bitrix/admin/`.

- `version.php`
  - Номер версии и дата для системной информации Bitrix.

#### 2.4. `lang/`

- Локализация для:
  - admin‑страниц (`admin/bpbutton_list.php`, `admin/bpbutton_edit.php`, `menu.php`);
  - классов `lib/` (user type, EventHandler, контроллеры);
  - описания и настроек модуля (`install/index.php`, `options.php`).

---

### 3. Прокси‑admin‑файлы в `/bitrix/admin/`

Bitrix ожидает admin‑скрипты по пути `/bitrix/admin/*.php`. Рекомендуется использовать прокси:

```php
<?php
// /bitrix/admin/my_bpbutton_list.php
require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/my.bpbutton/admin/bpbutton_list.php');
```

Такая схема:
- сохраняет «чистоту» ядра (логика в `/local/modules/...`);
- упрощает обновления и перенос модуля.

---

### 4. JS и CSS ресурсы

- JS‑код работы кнопки:
  - может жить в `/local/js/my.bpbutton/button.js`;
  - подключается в карточке CRM через user type или через расширения Bitrix;
  - реализует контракт, описанный в `../architecture/frontend_ui.md`.

- CSS (по минимуму):
  - `/local/css/my.bpbutton/button.css`;
  - должен опираться на Bitrix24 UI Kit, а не заменять его.

## Файловая структура модуля «BP Button Field» в `/local/modules`

Целевая структура модуля в коробочной версии Битрикс24:

```text
/local/modules/my.bpbutton/
├── install/
│   ├── index.php                 # Логика установки/удаления модуля
│   ├── version.php               # Версия модуля и информация о поставщике
│   └── step.php                  # Мастер установки (при необходимости)
├── lib/
│   ├── Internals/
│   │   ├── SettingsTable.php     # D7 ORM-сущность my_bpbutton_settings
│   │   └── LogsTable.php         # (план) D7 ORM-сущность my_bpbutton_logs
│   ├── Controller/
│   │   └── ButtonController.php  # Bitrix\Main\Engine\Controller для AJAX‑запросов
│   ├── Service/
│   │   └── ButtonService.php     # Бизнес‑логика работы кнопок (конфигурация, условия, логирование)
│   ├── UserField/
│   │   └── BpButtonUserType.php  # Реализация пользовательского типа поля bp_button_field
│   └── EventHandler.php          # Регистрация и обработка событий (OnUserTypeBuildList и др.)
├── lang/
│   └── ru/
│       ├── install/index.php     # Языки для мастера установки
│       ├── lib/…                 # Языковые файлы для классов (UserField, Service, Controller)
│       ├── admin/bpbutton_list.php # Локализация колонок/кнопок в реестре
│       └── options.php           # Языки для страницы настроек модуля
├── options.php                   # Глобальные опции модуля (если требуются)
└── admin/
    ├── bpbutton_list.php         # Административный реестр полей «Кнопки БП»
    ├── bpbutton_list_ajax.php    # (опц.) обработчики AJAX для списка/форм
    └── menu.php                  # Пункт меню «Настройки → Мои модули → Кнопки БП»
```

---

### Назначение ключевых директорий и файлов

#### `/install`

- **`install/index.php`**  
  - класс модуля (`class my_bpbutton extends CModule`), реализующий:
    - `DoInstall()` — создание таблиц (`my_bpbutton_settings`, в будущем `my_bpbutton_logs`), регистрация событий (`OnUserTypeBuildList`, `OnAfterUserFieldAdd`, `OnUserFieldDelete`), установка admin‑файлов и меню;
    - `DoUninstall()` — отписка от событий, удаление admin‑файлов и (опционально) таблиц/данных модуля.
- **`install/version.php`**  
  - массив с версией модуля, датой и информацией о разработчике;
  - используется Bitrix для проверки обновлений.
- **`install/step.php`** (по необходимости)  
  - шаги мастера установки, если требуется интерактивный ввод параметров.

#### `/lib`

- **`lib/Internals/SettingsTable.php`**  
  - ORM‑класс D7 для таблицы `my_bpbutton_settings`;
  - слой данных для настроек кнопок (см. `architecture/data_model.md`).
- **`lib/Internals/LogsTable.php`** (план)  
  - ORM‑класс D7 для таблицы `my_bpbutton_logs`;
  - хранение истории нажатий (см. `TASK-004-logging.md`).
- **`lib/UserField/BpButtonUserType.php`**  
  - реализация пользовательского типа поля `bp_button_field`;
  - отвечает за описание типа и HTML‑представление кнопки в карточке CRM.
- **`lib/Service/ButtonService.php`**  
  - бизнес‑логика вокруг кнопок:
    - получение настроек из `SettingsTable`;
    - подготовка конфигурации SidePanel;
    - (в будущем) запись логов через `LogsTable`.
- **`lib/Controller/ButtonController.php`**  
  - `Bitrix\Main\Engine\Controller` для AJAX‑запросов от JS‑расширения;
  - проверяет сессию/права и возвращает конфигурацию SidePanel.
- **`lib/EventHandler.php`**  
  - регистрация и обработка событий:
    - `OnUserTypeBuildList` — регистрация `bp_button_field`;
    - `OnAfterUserFieldAdd` / `OnUserFieldDelete` — синхронизация с `SettingsTable`.

#### `/lang`

- Содержит локализации для всех PHP‑файлов модуля:
  - строки для мастера установки, описаний типов полей, admin‑форм, сообщений об ошибках.

#### `/admin`

- **`admin/bpbutton_list.php`**  
  - административный реестр полей «Кнопки БП»:
    - отображает список записей `my_bpbutton_settings`;
    - предоставляет формы редактирования настроек (URL, заголовок, ширина, активность).
- **`admin/bpbutton_list_ajax.php`** (если используется)  
  - обработчики AJAX‑действий для списка (массовые операции, быстрые правки).
- **`admin/menu.php`**  
  - описание пункта меню:
    - раздел «Настройки → Мои модули → Кнопки БП»;
    - привязка к правам модуля.

#### `options.php`

- Страница глобальных настроек модуля (если понадобятся):
  - общие флаги, влияющие на поведение всех кнопок;
  - использование стандартного механизма `CModule::IncludeModule` + `__AdmSettings*`.


