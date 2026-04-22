# TASK-015: Третий формат кнопки — запуск БП с параметром (строка)

**Дата создания:** 2026-04-22 (UTC+3, Брест)  
**Статус:** Новая  
**Приоритет:** Высокий  
**Исполнитель:** Bitrix24 Программист (коробка, D7 + Vanilla JS)

## Описание

В модуле уже есть два режима действия кнопки `bp_button_field`:
1. `url` — открытие SidePanel по URL;
2. `bp_launch` — запуск БП через стандартный `bizproc.workflow.starter`.

Нужно добавить **третий режим**: `bp_launch_with_params` (рабочее имя), где перед запуском БП пользователь вводит значение параметра в popup-окне, после чего БП запускается серверным методом `CBPDocument::StartWorkflow`.

Для MVP поддерживается **один параметр типа строка**.

## Контекст

- Текущая реализация запуска БП (`TASK-014`) использует `starter.beginStartWorkflow(templateId)` и не даёт контролируемый UX внутри кнопки для собственного popup.
- Новое требование: popup должен открываться **после клика по кнопке** и содержать:
  - название параметра (человекочитаемое, на русском, задаётся в админке);
  - поле ввода значения;
  - кнопку «Выполнить».
- В админке администратор задаёт:
  - системное имя параметра (латиница, например `comment_text`);
  - русский заголовок для формы ввода (например `Комментарий для запуска`).
- Запуск БП выполняется для текущего элемента CRM (лид/сделка/контакт/компания/смарт-процесс) с передачей:
  - `CBPDocument::PARAM_TAGRET_USER`;
  - пользовательского параметра `<PARAM_NAME> => <input_value>`.

## Модули и компоненты

### База данных и модель

- `local/modules/my.bpbutton/install/index.php`
  - миграция: добавить поля в `my_bpbutton_settings`:
    - `PARAM_NAME` `VARCHAR(100) NULL`
    - `PARAM_TITLE` `VARCHAR(255) NULL`
- `local/modules/my.bpbutton/lib/Internals/SettingsTable.php`
  - добавить поля `PARAM_NAME`, `PARAM_TITLE` в `getMap()`.

### Админка и валидация

- `local/modules/my.bpbutton/admin/bpbutton_edit.php`
  - добавить третий тип действия (`bp_launch_with_params`);
  - при выборе этого режима показывать поля:
    - «Имя параметра (EN)»;
    - «Название параметра (RU)».
- `local/modules/my.bpbutton/lib/Service/SettingsFormService.php`
  - валидация:
    - `ACTION_TYPE in [url, bp_launch, bp_launch_with_params]`;
    - `PARAM_NAME` обязательно для `bp_launch_with_params`;
    - `PARAM_NAME` только латиница/цифры/подчёркивание, первый символ — буква;
    - `PARAM_TITLE` обязательно для `bp_launch_with_params`.
  - сохранение `PARAM_NAME`, `PARAM_TITLE`.

### API и запуск БП

- `local/modules/my.bpbutton/lib/Service/ButtonService.php`
  - расширить конфиг действия:
    - при `bp_launch_with_params` возвращать:
      - `actionType = 'bp_launch_with_params'`;
      - `bpTemplateId`;
      - `paramMeta: { name, title, type: 'string' }`;
      - контекст элемента.
  - добавить метод серверного старта БП с параметрами:
    - проверка шаблона, прав и документа;
    - `CBPDocument::StartWorkflow(...)`.
- `local/modules/my.bpbutton/lib/Controller/ButtonController.php`
  - добавить action для старта:
    - `startBpWithParamsAction(string $entityId, int $elementId, int $fieldId, string $value): array`.
  - единый формат ответа и ошибок.
- `local/modules/my.bpbutton/tools/button_ajax.php`
  - роутинг по `action`:
    - `getConfig` (текущий сценарий);
    - `startBpWithParams` (новый сценарий).

### Frontend

- `local/modules/my.bpbutton/install/js/my.bpbutton/button.js`
  - при `actionType = bp_launch_with_params` открывать popup с формой ввода;
  - отправлять значение на backend;
  - показывать уведомления об успехе/ошибке.
- `local/modules/my.bpbutton/install/js/my.bpbutton/button.api.js`
  - добавить метод `startBpWithParams(...)`.
- `local/modules/my.bpbutton/install/js/my.bpbutton/button.state.js`
  - корректная блокировка кнопки на время выполнения.

### Локализация

- `local/modules/my.bpbutton/lang/ru/...`
  - подписи для нового режима;
  - подписи полей;
  - сообщения popup/валидации/ошибок.

## Зависимости

- `TASK-014-bp-launch-subtype-in-field-settings.md` — действующий режим запуска БП.
- `TASK-012-button-click-no-save.md` — клик кнопки не должен переключать Entity Editor.
- Модули Bitrix: `crm`, `bizproc`.

## Ступенчатые подзадачи

### 1. Добавить новый action type

1. Ввести новый тип действия: `bp_launch_with_params`.
2. Обновить валидацию и сохранение в `SettingsFormService`.
3. Добавить UI в `bpbutton_edit.php` (радио + поля параметра).

### 2. Расширить схему хранения настроек

1. Добавить поля `PARAM_NAME`, `PARAM_TITLE` в таблицу настроек (миграция).
2. Добавить поля в ORM-модель `SettingsTable`.
3. Обеспечить обратную совместимость для старых записей (`NULL` допустим).

### 3. Расширить контракт getConfig

1. В `ButtonService::getSidePanelConfig` (или новом методе) вернуть новый формат для `bp_launch_with_params`.
2. Обновить `ButtonController::getConfigAction` без регрессий для `url` и `bp_launch`.

### 4. Реализовать серверный запуск с параметром

1. Добавить `startBpWithParamsAction`.
2. Проверить сессию, права, активность кнопки, валидность шаблона.
3. Сформировать `$documentId` через CRM helper.
4. Вызвать:
   - `CBPDocument::StartWorkflow($templateId, $documentId, $arParameters, $errors)`.
5. В `$arParameters` передать:
   - `CBPDocument::PARAM_TAGRET_USER => 'user_' . $userId`;
   - `$paramName => $value`.
6. Вернуть `workflowId` или стандартизированную ошибку.

### 5. Реализовать popup ввода на фронте

1. При клике и `actionType=bp_launch_with_params` открыть popup (`BX.PopupWindow` или `BX.UI.Dialogs.MessageBox`).
2. Отрисовать:
   - заголовок поля (`paramMeta.title`);
   - input (string);
   - кнопку «Выполнить».
3. По кнопке:
   - валидация непустого значения;
   - AJAX `startBpWithParams`;
   - уведомление об успехе/ошибке;
   - закрытие popup при успехе.

### 6. Локализация и документация

1. Добавить lang-ключи RU.
2. Обновить `docs_antonov/first_module/api/response_format.md` (новый action).
3. Добавить запись в историю правок TASK.

## API-методы и материалы

### Серверный запуск БП

- `CBPDocument::StartWorkflow`  
  [Документация](https://dev.1c-bitrix.ru/api_help/bizproc/bizproc_classes/cbpdocument/startworkflow.php)

### Проверка прав на запуск

- `CBPDocument::canUserOperateDocument(...)`  
  (операция запуска шаблона для конкретного документа)

### Подготовка documentId для CRM

- `CCrmBizProcHelper::ResolveDocumentId($entityTypeId, $elementId)`
- `CCrmBizProcHelper::ResolveDocumentType($entityTypeId)`

### Текущий JS-стартер (для справки)

- `bizproc.workflow.starter` / `starter.beginStartWorkflow(templateId)`  
  (в этой задаче используется как reference, основной сценарий — собственный popup + серверный старт)

## Технические требования

- Только коробочная версия Bitrix24.
- Только один параметр, тип `string` (MVP).
- `PARAM_NAME` хранится и обрабатывается как системный ключ (EN).
- `PARAM_TITLE` используется только для UI (RU).
- Не ломать текущие режимы `url` и `bp_launch`.
- Единый формат ответа API:
  - успех: `{ success: true, data: {...} }`
  - ошибка: `{ success: false, error: { code, message } }`

## Критерии приёмки

- [ ] В админке появился третий режим: «Запуск БП с параметром».
- [ ] Для режима доступны поля `PARAM_NAME` и `PARAM_TITLE`.
- [ ] `PARAM_NAME` валидируется (латиница/цифры/`_`), `PARAM_TITLE` обязателен.
- [ ] При клике по кнопке открывается popup ввода.
- [ ] В popup отображается русский заголовок параметра и поле ввода строки.
- [ ] По кнопке «Выполнить» запускается БП для текущего элемента.
- [ ] Переданный параметр доступен в БП по имени `PARAM_NAME`.
- [ ] Режимы `url` и `bp_launch` работают без изменений.

## Тестирование

1. Создать/выбрать поле `bp_button_field` для сущности CRM.
2. В настройках кнопки выбрать `bp_launch_with_params`.
3. Указать:
   - `PARAM_NAME = comment_text`
   - `PARAM_TITLE = Комментарий`
4. Нажать кнопку в карточке сущности:
   - появляется popup;
   - заполнить строку;
   - нажать «Выполнить».
5. Проверить:
   - БП стартовал (`workflowId` в ответе/логах);
   - значение параметра пришло в шаблон БП.
6. Негативные кейсы:
   - пустое значение в popup;
   - невалидный `PARAM_NAME` в админке;
   - нет прав на запуск БП;
   - отключённый шаблон/удалённый шаблон.

## История правок

- 2026-04-22: Создана задача на третий формат кнопки — запуск БП с параметром (string) через popup ввода.
