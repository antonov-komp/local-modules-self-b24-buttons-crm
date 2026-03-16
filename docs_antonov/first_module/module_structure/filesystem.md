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


