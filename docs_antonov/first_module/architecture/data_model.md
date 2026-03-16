## Модель данных модуля «BP Button Field»

Документ описывает структуру хранения данных, связанную с модулем:

- Таблица `my_bpbutton_settings` и ORM-класс `My\BpButton\Internals\SettingsTable`.
- Связи с системными таблицами (`b_user_field` и др.).
- Планируемая таблица логов `my_bpbutton_logs` (для истории нажатий).

---

### 1. Таблица настроек `my_bpbutton_settings`

Назначение: хранить настройки логики для каждого пользовательского поля типа `bp_button_field`.

#### 1.1. Предлагаемая структура (DDL‑черновик)

```sql
CREATE TABLE my_bpbutton_settings (
    ID              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    FIELD_ID        INT UNSIGNED NOT NULL,       -- связь с b_user_field.ID
    ENTITY_ID       VARCHAR(50)     NULL,        -- при необходимости, ограничение на сущность CRM
    HANDLER_URL     VARCHAR(500)    NULL,        -- URL обработчика (iframe / внешний сервис)
    TITLE           VARCHAR(255)    NULL,        -- заголовок окна SidePanel
    WIDTH           VARCHAR(50)     NULL,        -- ширина окна (px или %, например '70%' или '1200')
    ACTIVE          CHAR(1)         NOT NULL DEFAULT 'Y',
    CREATED_AT      DATETIME        NOT NULL,
    UPDATED_AT      DATETIME        NOT NULL,

    PRIMARY KEY (ID),
    UNIQUE KEY UX_BPBTN_FIELD (FIELD_ID),
    KEY IX_BPBTN_ENTITY (ENTITY_ID)
);
```

Ключевые моменты:

- `FIELD_ID` связывает запись с конкретным пользовательским полем в `b_user_field`;
- `HANDLER_URL`, `TITLE`, `WIDTH` соответствуют настройкам, доступным в админском реестре;
- `ACTIVE` позволяет временно отключать кнопку без удаления поля;
- индексы:
  - `UX_BPBTN_FIELD` гарантирует, что у поля только одна запись настроек;
  - `IX_BPBTN_ENTITY` пригоден для будущих выборок по типу сущности.

#### 1.2. D7‑описание `SettingsTable`

Класс: `My\BpButton\Internals\SettingsTable` (наследует `DataManager`), пример полей:

- `ID` — `IntegerField`, primary, autocompletion;
- `FIELD_ID` — `IntegerField`, обязательное поле, уникальный индекс;
- `ENTITY_ID` — `StringField`, необязательно;
- `HANDLER_URL` — `StringField`, необязательно;
- `TITLE` — `StringField`, необязательно;
- `WIDTH` — `StringField`, необязательно;
- `ACTIVE` — `BooleanField` или `EnumField` (`Y`/`N`);
- `CREATED_AT`, `UPDATED_AT` — `DatetimeField` c автозаполнением.

Опционально:

- отношение `FIELD` к сущности `\Bitrix\Main\UserFieldTable` (или ручная связка через `FIELD_ID`);
- методы‑помощники для получения настроек по `FIELD_ID`.

#### 1.3. Черновой пример D7‑описания (псевдокод)

```php
namespace My\BpButton\Internals;

use Bitrix\Main\Entity;

class SettingsTable extends Entity\DataManager
{
    public static function getTableName()
    {
        return 'my_bpbutton_settings';
    }

    public static function getMap()
    {
        return [
            new Entity\IntegerField('ID', [
                'primary'      => true,
                'autocomplete' => true,
            ]),

            new Entity\IntegerField('FIELD_ID', [
                'required' => true,
            ]),

            new Entity\StringField('ENTITY_ID', [
                'validation' => function () {
                    return [
                        new Entity\Validator\Length(null, 50),
                    ];
                },
            ]),

            new Entity\StringField('HANDLER_URL', [
                'validation' => function () {
                    return [
                        new Entity\Validator\Length(null, 500),
                    ];
                },
            ]),

            new Entity\StringField('TITLE', [
                'validation' => function () {
                    return [
                        new Entity\Validator\Length(null, 255),
                    ];
                },
            ]),

            new Entity\StringField('WIDTH', [
                'validation' => function () {
                    return [
                        new Entity\Validator\Length(null, 50),
                    ];
                },
            ]),

            new Entity\BooleanField('ACTIVE', [
                'values'  => ['N', 'Y'],
                'default' => 'Y',
            ]),

            new Entity\DatetimeField('CREATED_AT', [
                'default_value' => new \Bitrix\Main\Type\DateTime(),
            ]),

            new Entity\DatetimeField('UPDATED_AT', [
                'default_value' => new \Bitrix\Main\Type\DateTime(),
            ]),
        ];
    }
}
```

Фактическая реализация может отличаться, но концептуально должна соответствовать описанной модели.

---

### 2. Таблица логов `my_bpbutton_logs` (план расширения)

Назначение: хранить историю нажатий кнопок и результат выполнения логики (будущая версия).

#### 2.1. Черновая структура

```sql
CREATE TABLE my_bpbutton_logs (
    ID              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    SETTINGS_ID     INT UNSIGNED NOT NULL,       -- связь с my_bpbutton_settings.ID
    FIELD_ID        INT UNSIGNED NOT NULL,       -- дублируется для удобства выборок
    ENTITY_ID       VARCHAR(50)     NOT NULL,    -- тип сущности (DEAL, LEAD и т.д.)
    ELEMENT_ID      INT UNSIGNED    NOT NULL,    -- ID элемента CRM
    USER_ID         INT UNSIGNED    NOT NULL,    -- кто нажал кнопку
    STATUS          VARCHAR(50)     NOT NULL,    -- результат (SUCCESS, ERROR, TIMEOUT и т.п.)
    MESSAGE         TEXT            NULL,        -- необязательное диагностическое сообщение
    CREATED_AT      DATETIME        NOT NULL,

    PRIMARY KEY (ID),
    KEY IX_BPBTN_LOG_FIELD (FIELD_ID),
    KEY IX_BPBTN_LOG_ENTITY (ENTITY_ID, ELEMENT_ID),
    KEY IX_BPBTN_LOG_USER (USER_ID)
);
```

Эта таблица не обязательна для первой версии, но её структура должна учитываться при
проектировании, чтобы:

- легко добавить запись логов при обработке нажатия;
- иметь возможность аналитики по сущностям, полям и пользователям.

---

### 3. Связи с системными таблицами Bitrix24

Основные связи:

- `my_bpbutton_settings.FIELD_ID` → `b_user_field.ID`:
  - обеспечивает соответствие настроек конкретному пользовательскому полю CRM;
  - используется обработчиками `OnAfterUserFieldAdd` / `OnUserFieldDelete`.
- (опционально) `my_bpbutton_logs.USER_ID` → `b_user.ID`:
  - позволяет строить отчёты по активности пользователей.

Дополнительно:

- при необходимости могут использоваться ссылки на таблицы CRM
  (например, `b_crm_deal`, `b_crm_lead`) через поля `ENTITY_ID` и `ELEMENT_ID`,
  но модуль не создаёт внешних ключей на уровне БД, чтобы не нарушать обновления ядра.

