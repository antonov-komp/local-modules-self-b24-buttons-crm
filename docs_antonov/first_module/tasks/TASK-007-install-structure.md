# TASK-007: install/uninstall и файловая структура модуля

**Дата создания:** 2026-03-16 (UTC+3, Брест)  
**Статус:** Новая  
**Приоритет:** Высокий  
**Исполнитель:** Bitrix24 Программист (коробка, D7)

---

## Описание

Задача описывает реализацию установки/удаления модуля «BP Button Field» и финализацию его файловой структуры в `/local/modules/my.bpbutton/`.

Нужно:

- сформировать целостное дерево файлов и директорий модуля;
- реализовать `install/index.php` (класс модуля) с корректной регистрацией:
  - самого модуля;
  - таблиц `my_bpbutton_settings` и `my_bpbutton_logs`;
  - событий `EVT-001..EVT-003` (`OnUserTypeBuildList`, `OnAfterUserFieldAdd`, `OnUserFieldDelete`);
  - admin‑меню и подключения JS/CSS ресурсов;
- реализовать `DoUninstall` с понятной политикой по таблицам и логам:
  - что удаляем;
  - что оставляем по умолчанию;
  - (опционально) выбор администратора в мастере удаления.

---

## Контекст

- Файловая структура и общая концепция модуля:
  - `module_structure/filesystem.md`;
  - `local_button.md` (master‑спецификация).
- Поведение при установке/удалении:
  - `module_structure/install_uninstall.md`.
- События ядра и модуля:
  - `api/events.md` (EVT‑001–EVT‑003).
- Модель данных и таблицы:
  - `architecture/data_model.md` (`my_bpbutton_settings`, `my_bpbutton_logs`);
  - `architecture/backend_d7.md` (ORM‑классы, слои backend).
- Задачи, зависящие от корректного install/uninstall:
  - `TASK-001-user-type` (user type, обработчики событий);
  - `TASK-002-admin-grid` (admin‑реестр по `SettingsTable`);
  - `TASK-003-ajax-api` (контроллер, namespace);
  - `TASK-004-logging` (таблица логов и сервисы);
  - `TASK-005-js-frontend`, `TASK-006-admin-ux` (подключение JS/CSS).

---

## Модули и компоненты

- `local/modules/my.bpbutton/` — корень модуля:
  - `install/index.php` — класс модуля (`CModule`/наследник), реализующий `DoInstall`/`DoUninstall`;
  - `install/db/mysql/install.sql` / `uninstall.sql` (опционально) — SQL‑скрипты создания/удаления таблиц;
  - `install/js/my.bpbutton/` — JS‑ресурсы (кнопка в CRM, админ‑UX);
  - `install/css/my.bpbutton/` — CSS‑ресурсы (при необходимости).

- `local/modules/my.bpbutton/lib/`:
  - `EventHandler.php` — обработчики событий (EVT‑001..003);
  - `Internals/SettingsTable.php` — ORM‑сущность для `my_bpbutton_settings`;
  - `Internals/LogsTable.php` — ORM‑сущность для `my_bpbutton_logs`;
  - `Controller/ButtonController.php` — AJAX‑контроллер (см. `TASK-003`);
  - `Service/ButtonService.php` — сервисный слой.

- `local/modules/my.bpbutton/admin/`:
  - `bpbutton_list.php` — список настроек;
  - `bpbutton_edit.php` — форма редактирования (если выносится отдельно);
  - `menu.php` — подключение пункта меню «Кнопки БП».

- `local/modules/my.bpbutton/lang/`:
  - языковые файлы для:
    - `install/index.php`;
    - admin‑страниц (`bpbutton_list.php`, `bpbutton_edit.php`, `menu.php`);
    - классов (`EventHandler`, `ButtonController`, `UserType` и т.п.).

---

## Зависимости

- Задача опирается на архитектурные документы:
  - `module_structure/filesystem.md` — целевой layout модуля;
  - `module_structure/install_uninstall.md` — требования к поведению установки/удаления;
  - `api/events.md` — список и регистрация обработчиков;
  - `architecture/data_model.md` — структура таблиц.
- От выполнения задачи зависят:
  - возможность корректной установки модуля на чистую коробку;
  - корректная миграция между стендами (dev/stage/prod);
  - отсутствие «мусорных» таблиц и записей при удалении модуля.

---

## Ступенчатые подзадачи

### 1. Уточнить и зафиксировать дерево `/local/modules/my.bpbutton/`

1.1. Сформировать дерево директорий на основе `module_structure/filesystem.md`, включая:

- `install/` (index.php, js/, css/, db/…);
- `lib/` (EventHandler, Internals, Service, Controller, UserField и т.д.);
- `admin/` (список, формы, меню);
- `lang/` (структура по языкам и файлам).

1.2. Убедиться, что все пути, упомянутые в:

- `TASK-001..006`;
- `architecture/backend_d7.md`;
- `architecture/data_model.md`;

соответствуют фактической структуре (при расхождениях — согласовать и обновить документы/пути).

---

### 2. Реализация `install/index.php` (класс модуля)

2.1. Создать/доработать класс модуля:

- пространство имён и имя файла в соответствии с рекомендованным шаблоном Bitrix;
- свойства:
  - `MODULE_ID = 'my.bpbutton'`;
  - `MODULE_NAME`, `MODULE_DESCRIPTION`, `MODULE_VERSION`, `MODULE_VERSION_DATE`;
  - `PARTNER_NAME`, `PARTNER_URI` (по необходимости).

2.2. Реализовать `DoInstall()`:

- шаги (упрощённо):
  1. Регистрация модуля (`RegisterModule('my.bpbutton')`);
  2. Создание таблиц:
     - `my_bpbutton_settings` (минимум) — см. DDL в `architecture/data_model.md`;
     - `my_bpbutton_logs` (если логирование входит в первую установку, см. `TASK-004`);
  3. Регистрация обработчиков событий (см. `api/events.md`):
     - `main:OnUserTypeBuildList` → `My\BpButton\EventHandler::onUserTypeBuildList`;
     - `main:OnAfterUserFieldAdd` → `My\BpButton\EventHandler::onAfterUserFieldAdd`;
     - `main:OnUserFieldDelete` → `My\BpButton\EventHandler::onUserFieldDelete`;
  4. Регистрация admin‑меню и страниц:
     - подключение `admin/menu.php`;
  5. Регистрация JS/CSS:
     - `CJSCore::RegisterExt` для `my_bpbutton.button`, `my_bpbutton.admin_list` и т.п. (если требуется на этапе установки);
  6. (Опционально) шаги мастера установки (интерфейс выбора параметров).

2.3. Реализовать `DoUninstall()`:

- шаги:
  1. Снять обработчики событий:
     - `UnRegisterModuleDependences('main', 'OnUserTypeBuildList', 'my.bpbutton', 'My\BpButton\EventHandler', 'onUserTypeBuildList');`
     - аналогично для `OnAfterUserFieldAdd` и `OnUserFieldDelete`.
  2. Обработать таблицы:
     - политика по `my_bpbutton_settings` и `my_bpbutton_logs`:
       - либо удалять;
       - либо оставлять (по умолчанию или настраиваемо);
       - либо предлагать выбор через мастер (см. ниже).
  3. Удалить модуль:
     - `UnRegisterModule('my.bpbutton');`.

2.4. Языковые файлы:

- вынести все пользовательские тексты (название модуля, описания, сообщения мастера) в:
  - `local/modules/my.bpbutton/lang/ru/install/index.php`.

---

### 3. Создание и удаление таблиц `my_bpbutton_settings` и `my_bpbutton_logs`

3.1. **Создание таблиц**

- Использовать один из подходов (уточняется в `module_structure/install_uninstall.md`):
  - SQL‑скрипты:
    - `install/db/mysql/install.sql` с DDL для:
      - `my_bpbutton_settings`;
      - `my_bpbutton_logs` (если включается).
  - или D7‑`\Bitrix\Main\Application::getConnection()->isTableExists()` + `createDbTable` на базе ORM‑классов.

- DDL брать из `architecture/data_model.md`:
  - учесть первичные ключи и индексы:
    - `UX_BPBTN_FIELD`, `IX_BPBTN_ENTITY` для настроек;
    - индексы по `FIELD_ID`, `ENTITY_ID + ELEMENT_ID`, `USER_ID` для логов.

3.2. **Удаление/сохранение таблиц при `DoUninstall`**

- Определить и задокументировать политику:
  - по умолчанию **рекомендуется**:
    - `my_bpbutton_settings` — удалить;
    - `my_bpbutton_logs` — по решению:
      - либо оставить (как исторические данные);
      - либо удалить (по явному согласию администратора).

- Реализовать:
  - либо отдельным шагом мастера (`DoUninstall` с пошаговым интерфейсом);
  - либо простым поведением по умолчанию (без UI).

3.3. Проверки:

- при повторной установке модуля:
  - корректно обрабатывается ситуация, когда таблицы уже существуют или, наоборот, отсутствуют;
  - нет фатальных ошибок при `CREATE TABLE IF NOT EXISTS` / проверках существования таблиц.

---

### 4. Регистрация обработчиков событий (EVT‑001..EVT‑003)

4.1. В `DoInstall` зарегистрировать обработчики в соответствии с `api/events.md`:

- `main:OnUserTypeBuildList` → `My\BpButton\EventHandler::onUserTypeBuildList`;
- `main:OnAfterUserFieldAdd` → `My\BpButton\EventHandler::onAfterUserFieldAdd`;
- `main:OnUserFieldDelete` → `My\BpButton\EventHandler::onUserFieldDelete`.

4.2. В `DoUninstall` — обязательно снять все зарегистрированные зависимости.

4.3. Проверить:

- что фактический список зарегистрированных обработчиков совпадает с таблицей событий в `api/events.md`;
- что обработчики не дублируются при повторных установках/обновлениях.

---

### 5. Admin‑меню и admin‑страницы

5.1. **Меню**

- Реализовать `admin/menu.php`:
  - добавить пункт «Кнопки БП» в раздел «Настройки → Мои модули»;
  - использовать `GetGroupRight('my.bpbutton')` для проверки прав:
    - доступ только у администраторов модуля.

5.2. **Подключение в install**

- Убедиться, что:
  - admin‑меню и файлы корретно подхватываются ядром при установке модуля (через стандартный механизм Bitrix для модулей из `/local/modules`);
  - никаких дополнительных ручных регистраций, кроме общепринятых шагов, не требуется.

5.3. **Языковые файлы меню**

- `local/modules/my.bpbutton/lang/ru/admin/menu.php`:
  - содержит локализованное название пункта меню и описание.

---

### 6. Подключение JS/CSS

6.1. **Регистрация JS‑расширений**

- В `install/index.php` или отдельном helper’е:
  - зарегистрировать через `CJSCore::RegisterExt`:
    - `my_bpbutton.button` — JS‑логика кнопки в CRM (`TASK-005`);
    - `my_bpbutton.admin_list` — JS‑улучшения админ‑раздела (`TASK-006`).

- Для каждого расширения задать:
  - `js` — путь к файлу в `install/js/my.bpbutton/`;
  - `rel` — зависимости (`main.core`, `ui.buttons`, `ui.sidepanel`, `ui.notification` и т.п.).

6.2. **CSS‑файлы (если есть)**

- Аналогично зарегистрировать CSS:
  - в `install/css/my.bpbutton/`;
  - подключить только там, где это нужно:
    - для кнопки в CRM (если требуются свои классы поверх UI Kit);
    - для админ‑страниц (минимальные стили).

6.3. **Точки подключения JS/CSS**

- Для CRM:
  - в user type (`BpButtonUserType`) или через `EventHandler`:
    - подключать `my_bpbutton.button` только на страницах карточек CRM, где есть поле `bp_button_field`.

- Для админки:
  - в `bpbutton_list.php`/`bpbutton_edit.php`:
    - подключать `my_bpbutton.admin_list` и соответствующий CSS.

---

## API‑методы

Задача не добавляет новых REST‑методов или публичных API.

