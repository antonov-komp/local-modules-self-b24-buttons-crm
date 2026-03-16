## События ядра и модуля для «BP Button Field»

Документ фиксирует все события Bitrix (ядра и модулей), на которые подписывается модуль `my.bpbutton`, а также внутренние события модуля (если используются).

Для каждого события указываются:
- источник события (модуль/класс);
- обработчик (класс/метод);
- назначение и side‑effects (что происходит при срабатывании).

---

### 1. Сводная таблица событий

| ID      | Источник события              | Событие                | Обработчик (класс::метод)                          | Назначение / side‑effects                                      |
|---------|-------------------------------|------------------------|----------------------------------------------------|-----------------------------------------------------------------|
| EVT-001 | `main`                        | `OnUserTypeBuildList`  | `My\BpButton\EventHandler::onUserTypeBuildList`    | Регистрация пользовательского типа `bp_button_field`.          |
| EVT-002 | `main`                        | `OnAfterUserFieldAdd`  | `My\BpButton\EventHandler::onAfterUserFieldAdd`    | Авто‑создание записи в `my_bpbutton_settings` при создании поля. |
| EVT-003 | `main`                        | `OnUserFieldDelete`    | `My\BpButton\EventHandler::onUserFieldDelete`      | Очистка записей в `my_bpbutton_settings` (и логов в будущем).  |
| EVT-004 | (модуль `my.bpbutton`, внутр.)| `OnButtonClick`*       | Сервисы/слушатели внутри модуля                   | (План) реакция на нажатие кнопки, интеграции с БП/роботами.    |

\* EVT‑004 зарезервировано как внутреннее событие модуля для будущих расширений; в первой версии может отсутствовать.

---

### 2. Описание ключевых событий

#### EVT-001 — Регистрация user type (`OnUserTypeBuildList`)

- **Источник:** модуль `main`, событие ядра `OnUserTypeBuildList`.
- **Обработчик:** `My\BpButton\EventHandler::onUserTypeBuildList`.
- **Вызов при install:**
  - регистрируется через `RegisterModuleDependences('main', 'OnUserTypeBuildList', 'my.bpbutton', 'My\BpButton\EventHandler', 'onUserTypeBuildList');`
- **Назначение:**
  - вернуть описание пользовательского типа:
    - `USER_TYPE_ID = 'bp_button_field'`;
    - `CLASS_NAME = My\BpButton\UserField\BpButtonUserType`;
    - описание/заголовок типа (через `Loc::getMessage`).
- **Side‑effects:**
  - появление нового типа пользовательского поля в интерфейсе CRM.

#### EVT-002 — Авто‑регистрация настроек (`OnAfterUserFieldAdd`)

- **Источник:** модуль `main`, событие `OnAfterUserFieldAdd`.
- **Обработчик:** `My\BpButton\EventHandler::onAfterUserFieldAdd`.
- **Назначение:**
  - отреагировать на создание пользовательского поля в CRM;
  - если:
    - `USER_TYPE_ID === 'bp_button_field'`;
    - поле относится к поддерживаемой сущности CRM;
  - то:
    - создать запись в `my_bpbutton_settings` через `SettingsTable` и/или `ButtonService`.

- **Типичный алгоритм:**
  1. Проверить тип поля и сущность (`ENTITY_ID`).
  2. Подготовить дефолтные значения:
     - `FIELD_ID` (из события);
     - `ENTITY_ID`;
     - `HANDLER_URL`, `TITLE`, `WIDTH` (пустые или шаблонные);
     - `ACTIVE = 'Y'`.
  3. Вызвать сервис:
     - `ButtonService::registerField($fieldData)` или аналог.

- **Side‑effects:**
  - в таблице `my_bpbutton_settings` создаётся запись, которая затем отображается в admin‑реестре.

#### EVT-003 — Очистка настроек (`OnUserFieldDelete`)

- **Источник:** модуль `main`, событие `OnUserFieldDelete`.
- **Обработчик:** `My\BpButton\EventHandler::onUserFieldDelete`.
- **Назначение:**
  - при удалении пользовательского поля типа `bp_button_field`:
    - удалить связанные записи в `my_bpbutton_settings`;
    - (в расширенной версии) удалить или пометить связанные записи логов.

- **Типичный алгоритм:**
  1. Проверить, что удаляемое поле — `bp_button_field`.
  2. По `FIELD_ID` вызвать:
     - `SettingsTable::deleteByFieldId($fieldId)` (статический метод/сервис).
  3. (Расширение) Обработка логов:
     - в зависимости от политики может:
       - удалять логи;
       - помечать их как архивные;
       - оставлять без изменений.

- **Side‑effects:**
  - отсутствие «висящих» записей настроек для несуществующих полей;
  - согласованность admin‑реестра с реальными пользовательскими полями.

---

### 3. Внутренние события модуля (план на будущее)

#### EVT-004 — Внутреннее событие `OnButtonClick`

- **Статус:** планируемое, опциональное.
- **Идея:**
  - при успешной конфигурации и/или нажатии кнопки модуль может генерировать внутреннее событие:
    - `OnButtonClick` с параметрами (`FIELD_ID`, `ENTITY_ID`, `ELEMENT_ID`, `USER_ID`, `STATUS`, `CONTEXT`).
  - слушатели этого события могут:
    - запускать доп. бизнес‑логику;
    - интегрироваться с бизнес‑процессами/роботами;
    - записывать логи в `my_bpbutton_logs`.

- **Реализация:**
  - через `\Bitrix\Main\Event`:

```php
$event = new \Bitrix\Main\Event(
    'my.bpbutton',
    'OnButtonClick',
    $eventData
);
$event->send();
```

- **Side‑effects:**
  - слабосвязанная интеграция с другими компонентами / модулями.

---

### 4. Регистрация и снятие обработчиков при install/uninstall

См. также `../module_structure/install_uninstall.md`.

При установке (`DoInstall`):

```php
RegisterModuleDependences(
    'main',
    'OnUserTypeBuildList',
    'my.bpbutton',
    'My\BpButton\EventHandler',
    'onUserTypeBuildList'
);

RegisterModuleDependences(
    'main',
    'OnAfterUserFieldAdd',
    'my.bpbutton',
    'My\BpButton\EventHandler',
    'onAfterUserFieldAdd'
);

RegisterModuleDependences(
    'main',
    'OnUserFieldDelete',
    'my.bpbutton',
    'My\BpButton\EventHandler',
    'onUserFieldDelete'
);
```

При удалении (`DoUninstall`):

```php
UnRegisterModuleDependences(
    'main',
    'OnUserTypeBuildList',
    'my.bpbutton',
    'My\BpButton\EventHandler',
    'onUserTypeBuildList'
);

UnRegisterModuleDependences(
    'main',
    'OnAfterUserFieldAdd',
    'my.bpbutton',
    'My\BpButton\EventHandler',
    'onAfterUserFieldAdd'
);

UnRegisterModuleDependences(
    'main',
    'OnUserFieldDelete',
    'my.bpbutton',
    'My\BpButton\EventHandler',
    'onUserFieldDelete'
);
```

---

### 5. Чек‑лист ревью событий

1. **Полнота регистрации**
   - [ ] Все обработчики событий, описанные в `events.md`, зарегистрированы в `DoInstall`.
   - [ ] Все обработчики корректно снимаются в `DoUninstall`.

2. **Изоляция логики**
   - [ ] Обработчики событий минимальны по логике и делегируют работу в сервисный слой (`ButtonService`).
   - [ ] Нет тяжёлых операций (многократные запросы, сложные вычисления) непосредственно в обработчиках.

3. **Безопасность**
   - [ ] В обработчиках не происходит модификации данных CRM, не описанной в архитектуре.
   - [ ] Ошибки внутри обработчиков логируются и не ломают критичные процессы ядра.

4. **Согласованность с документацией**
   - [ ] Фактический список событий совпадает с таблицей EVT‑ID в данном документе.
   - [ ] При добавлении новых событий в коде обновляется `events.md`.

## События ядра и модуля «BP Button Field»

Здесь фиксируются все используемые события:

- `main:OnUserTypeBuildList` — регистрация типа пользовательского поля `bp_button_field`.
- `main:OnAfterUserFieldAdd` — автоматическое создание записи в `SettingsTable`.
- `main:OnUserFieldDelete` — каскадная очистка настроек при удалении поля.

Ниже для каждого события описаны:

- обработчик и его расположение (класс/метод);
- сценарий использования;
- влияние на бизнес‑логику и данные (псевдокод шагов).

---

### 1. `main:OnUserTypeBuildList` — регистрация типа `bp_button_field`

- **Событие ядра:** `main:OnUserTypeBuildList`.
- **Обработчик:** `My\BpButton\EventHandler::onUserTypeBuildList`.
- **Файл:** `local/modules/my.bpbutton/lib/EventHandler.php`.

#### 1.1. Сценарий использования

- Вызывается при инициализации пользовательских типов полей в системе.
- Обработчик должен добавить описание нового типа поля `bp_button_field`, чтобы:
  - он появился в списке доступных типов при создании пользовательских полей в CRM;
  - был связан с классом `BpButtonUserType` для отображения в карточке.

#### 1.2. Влияние на бизнес‑логику и данные

- Без регистрации этого события:
  - администратор не увидит тип `bp_button_field` в интерфейсе;
  - модуль не сможет создавать кнопки через пользовательские поля.

#### 1.3. Псевдокод обработки

```php
public static function onUserTypeBuildList(): ?array
{
    return [
        'USER_TYPE_ID'  => 'bp_button_field',
        'CLASS_NAME'    => My\BpButton\UserField\BpButtonUserType::class,
        'DESCRIPTION'   => Loc::getMessage('MY_BPBTN_USER_TYPE_DESCRIPTION'),
        // дополнительные колбэки user type
    ];
}
```

---

### 2. `main:OnAfterUserFieldAdd` — создание записи в `SettingsTable`

- **Событие ядра:** `main:OnAfterUserFieldAdd`.
- **Обработчик:** `My\BpButton\EventHandler::onAfterUserFieldAdd`.
- **Файл:** `local/modules/my.bpbutton/lib/EventHandler.php`.

#### 2.1. Сценарий использования

- Срабатывает после добавления нового пользовательского поля в систему.
- Если поле относится к CRM и имеет тип `bp_button_field`, модуль должен:
  - автоматически создать запись в `my_bpbutton_settings` (`SettingsTable`);
  - связать её с `FIELD_ID` из `b_user_field`.

#### 2.2. Влияние на бизнес‑логику и данные

- Обеспечивает синхронизацию между:
  - системной записью пользовательского поля (`b_user_field`);
  - настройками модуля (`my_bpbutton_settings`).
- Благодаря этому поле **сразу появляется** в административном реестре модуля.

#### 2.3. Псевдокод обработки

```php
public static function onAfterUserFieldAdd(array $field): void
{
    // 1. Проверяем тип пользовательского поля
    if ($field['USER_TYPE_ID'] !== 'bp_button_field') {
        return;
    }

    // 2. Проверяем, что поле относится к CRM / нужной сущности (при необходимости)
    if (!self::isCrmField($field)) {
        return;
    }

    // 3. Создаём запись в SettingsTable через сервисный слой
    ButtonService::onFieldCreated($field);
}
```

> Детальная логика создания записи (дефолтные значения, ENTITY_ID и т.д.) описывается в `architecture/backend_d7.md` и `architecture/data_model.md`.

---

### 3. `main:OnUserFieldDelete` — каскадная очистка настроек

- **Событие ядра:** `main:OnUserFieldDelete`.
- **Обработчик:** `My\BpButton\EventHandler::onUserFieldDelete`.
- **Файл:** `local/modules/my.bpbutton/lib/EventHandler.php`.

#### 3.1. Сценарий использования

- Срабатывает при удалении пользовательского поля.
- Если удаляется поле типа `bp_button_field`, необходимо:
  - удалить соответствующую запись в `my_bpbutton_settings`;
  - при необходимости — очистить связанные логи в `my_bpbutton_logs`.

#### 3.2. Влияние на бизнес‑логику и данные

- Предотвращает:
  - накопление «осиротевших» записей в таблицах модуля;
  - появление неиспользуемых кнопок в админ‑реестре.

#### 3.3. Псевдокод обработки

```php
public static function onUserFieldDelete(array $field): void
{
    if ($field['USER_TYPE_ID'] !== 'bp_button_field') {
        return;
    }

    // 1. Получаем FIELD_ID
    $fieldId = (int)$field['ID'];

    // 2. Удаляем настройки и (опционально) логи через сервисный слой
    ButtonService::onFieldDeleted($fieldId);
}
```

---

### 4. Связь событий с архитектурой

- Подробное описание слоёв backend и обработки событий — в `architecture/backend_d7.md`.
- Работа с таблицами `my_bpbutton_settings` и `my_bpbutton_logs` — в `architecture/data_model.md`.
- Поведение пользовательского типа и кнопки в карточке CRM — в `ui_ux/crm_card_button.md` и `tasks/TASK-001-user-type.md`.


