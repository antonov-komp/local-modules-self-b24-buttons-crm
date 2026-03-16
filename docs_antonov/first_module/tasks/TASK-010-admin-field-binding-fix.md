# TASK-010: Связь раздела администрирования «Кнопки БП» с полями bp_button_field

**Дата создания:** 2026-03-16 (UTC+3, Брест)  
**Статус:** Новая  
**Приоритет:** Высокий  
**Исполнитель:** Bitrix24 Программист (коробка, D7 + Vanilla JS)

## Описание

Задача описывает исправление связи между разделом администрирования «Кнопки БП» и пользовательскими полями типа `bp_button_field`. В текущей реализации:

- Ссылка «Настроить кнопку» в форме настроек поля ведёт на неверную запись (передаётся FIELD_ID вместо ID записи SettingsTable).
- Текст кнопки в карточке CRM не берётся из настроек — всегда отображается значение из языковых файлов.
- В реестре колонка «Поле» показывает только ID вместо названия поля (FIELD_NAME).

В результате администратор не может корректно настроить HANDLER_URL, TITLE, WIDTH — настройки «не применяются» к кнопкам в CRM.

## Контекст

- Анализ причин выполнен в `ANALYSIS-010-admin-field-binding.md`.
- Реестр настроек реализован в `TASK-002-admin-grid`.
- Форма настроек поля — в `TASK-009-field-config-ux-fixes`.
- Таблица `my_bpbutton_settings`: `ID` (PK, автоинкремент), `FIELD_ID` (ID пользовательского поля), `ENTITY_ID`, `HANDLER_URL`, `TITLE`, `WIDTH`, `ACTIVE`.

## Модули и компоненты

- `local/modules/my.bpbutton/lib/UserField/BpButtonUserType.php`  
  — метод `getSettingsHTML` (ссылка), метод `getPublicViewHTML` (текст кнопки).
- `local/modules/my.bpbutton/admin/bpbutton_list.php`  
  — форма редактирования (поддержка параметра FIELD_ID), список (join с UserFieldTable).
- `local/modules/my.bpbutton/lib/Internals/SettingsTable.php`  
  — добавление поля `BUTTON_TEXT` (миграция).
- `local/modules/my.bpbutton/install/index.php`  
  — миграция БД для нового поля (если добавляется).
- `local/modules/my.bpbutton/lang/ru/admin/bpbutton_list.php`  
  — языковые строки для нового поля формы.

## Зависимости

- **От других задач:**
  - `TASK-001-user-type` — регистрация типа `bp_button_field`;
  - `TASK-002-admin-grid` — реестр настроек;
  - `TASK-009-field-config-ux-fixes` — форма настроек поля.
- **От ядра Bitrix24:**
  - `Bitrix\Main\UserFieldTable` — для join в реестре;
  - `Bitrix\Main\Entity\ReferenceField` — для runtime-поля.

## Ступенчатые подзадачи

### 1. Исправить ссылку «Настроить кнопку» в getSettingsHTML

**Файл:** `lib/UserField/BpButtonUserType.php`

- Изменить формирование URL: вместо `ID=` передавать `FIELD_ID=`:
  ```php
  $settingsUrl = '/bitrix/admin/my_bpbutton_bpbutton_list.php?lang=' . LANGUAGE_ID . '&FIELD_ID=' . $fieldId . '&action=edit';
  ```

**Файл:** `admin/bpbutton_list.php` (блок формы редактирования, после получения `$id` и `$action`)

- Добавить обработку параметра `FIELD_ID`:
  ```php
  $id = (int)$request->get('ID');
  $fieldIdParam = (int)$request->get('FIELD_ID');

  if ($fieldIdParam > 0) {
      $settingsRow = SettingsTable::getList([
          'filter' => ['=FIELD_ID' => $fieldIdParam],
          'limit' => 1,
      ])->fetch();
      if ($settingsRow && !empty($settingsRow['ID'])) {
          $id = (int)$settingsRow['ID'];
      }
  }
  ```
- Убедиться, что дальнейшая логика формы использует `$id` как primary key записи SettingsTable.

### 2. Всегда показывать название поля в реестре

**Файл:** `admin/bpbutton_list.php`

- Добавить runtime join с `UserFieldTable` **всегда** (не только при `find_field_query`):
  ```php
  $runtime['UF'] = new Entity\ReferenceField(
      'UF',
      UserFieldTable::class,
      ['=this.FIELD_ID' => 'ref.ID'],
      ['join_type' => 'left']
  );
  ```
- Добавить в `$select` поле `UF_FIELD_NAME` (или `UF.FIELD_NAME` через selectMap) для всех запросов списка.
- Сохранить фильтр по `find_field_query` через `%UF.FIELD_NAME`.
- В цикле вывода строк использовать `$row['UF_FIELD_NAME']` для колонки «Поле»; при отсутствии — fallback на `ID=` + FIELD_ID.

### 3. Добавить поле BUTTON_TEXT и использовать его для текста кнопки

**Вариант A (рекомендуемый):** добавить поле `BUTTON_TEXT` в `SettingsTable`

- Миграция: добавить колонку `BUTTON_TEXT VARCHAR(255) NULL` в `my_bpbutton_settings`.
- В `SettingsTable::getMap()` добавить `Entity\StringField('BUTTON_TEXT', [...])`.
- В форме редактирования (`admin/bpbutton_list.php`) добавить поле ввода «Текст кнопки»; при сохранении — в `SettingsTable::update`.
- В `BpButtonUserType::getPublicViewHTML`: при `field['ID'] > 0` загружать запись из `SettingsTable` по `FIELD_ID`; если `BUTTON_TEXT` не пустой — использовать его, иначе — `Loc::getMessage` как fallback.
- Учесть производительность: при рендере нескольких полей на одной странице — кешировать настройки в статической переменной по `FIELD_ID` или использовать batch-запрос.

**Вариант B (упрощённый):** использовать существующее поле `TITLE`

- В `getPublicViewHTML` загружать настройки и использовать `TITLE` для текста кнопки (если не пусто).
- Минус: `TITLE` семантически — заголовок SidePanel; смешение может запутать. Рекомендуется Вариант A.

### 4. Обновить EventHandler::onAfterUserFieldAdd

- При создании записи в `SettingsTable` добавить `BUTTON_TEXT` => null (если поле добавлено).

### 5. Локализация

- Добавить в `lang/ru/admin/bpbutton_list.php` строки для поля «Текст кнопки» (если используется BUTTON_TEXT).
- Добавить подсказку: «Текст, отображаемый на кнопке в карточке CRM. Если пусто — используется значение по умолчанию.»

## Технические требования

- Коробочная версия Bitrix24, PHP 8.x, D7 ORM.
- Миграция БД — через `install/index.php` (проверка `isTableExists`, `queryExecute` для `ALTER TABLE` при обновлении модуля) или отдельный скрипт миграции.
- Кеширование в `getPublicViewHTML`: не делать отдельный запрос к БД для каждой кнопки на странице; использовать статический кеш по `FIELD_ID` в рамках одного запроса.

## Критерии приёмки

- [ ] Ссылка «Настроить кнопку» в форме настроек поля открывает форму редактирования **правильной** записи настроек (по FIELD_ID).
- [ ] В реестре «Кнопки БП» колонка «Поле» всегда показывает название поля (FIELD_NAME) из `b_user_field`, а не только `ID=...`.
- [ ] Текст кнопки в карточке CRM берётся из настроек (BUTTON_TEXT или TITLE), если задан; иначе — из языковых файлов.
- [ ] При сохранении HANDLER_URL, TITLE, WIDTH в форме редактирования настройки корректно применяются при нажатии кнопки (SidePanel открывается с нужным URL, заголовком и шириной).
- [ ] Нет регрессии: редактирование из списка (по ID записи) продолжает работать.
- [ ] Все новые тексты интерфейса локализованы.

## Тестирование

- **Сценарий 1:** CRM → Настройки → Пользовательские поля → создать поле типа «Кнопка бизнес‑процесса» → сохранить → в блоке настроек нажать «Настроить кнопку». Ожидание: открывается форма редактирования настроек для этого поля.
- **Сценарий 2:** Админка → Кнопки БП → проверить, что в колонке «Поле» отображается название поля (например, `UF_CRM_XXX`), а не только `ID=123`.
- **Сценарий 3:** В форме редактирования настроек задать HANDLER_URL, TITLE, WIDTH, BUTTON_TEXT → сохранить → открыть карточку CRM с этим полем. Ожидание: на кнопке отображается заданный текст; при нажатии SidePanel открывается с заданным URL, заголовком и шириной.
- **Сценарий 4:** Редактирование из списка (клик «Редактировать» по строке) — форма открывается и сохраняет данные корректно.

## История правок

- 2026-03-16: Создана задача на основе ANALYSIS-010-admin-field-binding.md.
