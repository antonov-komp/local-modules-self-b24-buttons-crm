# TASK-014: Подтип «Запуск бизнес-процесса» в настройках поля bp_button_field

**Дата создания:** 2026-03-16 (UTC+3, Брест)  
**Статус:** Новая  
**Приоритет:** Высокий  
**Исполнитель:** Bitrix24 Программист (коробка, D7 + Vanilla JS)

## Описание

Пользовательское поле типа `bp_button_field` создаётся в разрезе сущности CRM (лиды, сделки, контакты, компании, **смарт-процессы**). В текущей реализации кнопка поддерживает один режим работы: открытие SidePanel по URL обработчика.

**Цель задачи:** расширить настройки поля новым подтипом **«Запуск бизнес-процесса»**. При выборе этого подтипа администратор должен видеть слайдер со списком шаблонов бизнес-процессов (БП), привязанных к типу сущности (`ENTITY_ID`), к которому относится поле.

**Ключевой фокус:** задача — не просто **выбрать и привязать** шаблон к кнопке, а обеспечить **реальный запуск** (старт) бизнес-процесса при нажатии кнопки пользователем в карточке CRM. То есть: пользователь нажимает кнопку → БП запускается для текущего элемента (лида, сделки, элемента смарт-процесса и т.д.).

Таким образом, кнопка сможет работать в двух режимах:
1. **URL обработчика** (текущий) — открытие SidePanel по заданному URL;
2. **Запуск бизнес-процесса** (новый) — **запуск** выбранного шаблона БП для текущего элемента сущности (реальное выполнение `CBPDocument::StartWorkflow` или открытие слайдера запуска БП с вводом параметров).

## Контекст

- Настройки поля отображаются в форме создания/редактирования пользовательского поля (компонент `main.field.config.detail`, режим `main.admin_settings`).
- Метод `BpButtonUserType::getSettingsHTML` формирует HTML блока настроек; при создании поля `ENTITY_ID` ещё не известен (поле создаётся для сущности, но контекст может передаваться через `$field` или `$additional`).
- При редактировании существующего поля доступны `$field['ENTITY_ID']` и `$field['ID']`.
- Шаблоны БП хранятся в `b_bp_workflow_template`; для выборки по типу документа используется `CBPWorkflowTemplateLoader::getList` или `getDocumentTypeStates` с фильтром по `DOCUMENT_TYPE`.
- Маппинг `ENTITY_ID` → document type для Bizproc: `CCrmBizProcHelper::ResolveDocumentType($entityTypeId)` возвращает `['crm', $docName, $ownerTypeName]`.
- Для смарт-процессов: `ENTITY_ID = CRM_DYNAMIC_{typeId}`; document type = `['crm', Dynamic::class, CCrmOwnerType::ResolveName($entityTypeId)]`.
- Документация: `../module_structure/settings_ui.md`, `TASK-009-field-config-ux-fixes`, `TASK-010-admin-field-binding-fix`.
- **Коробочная версия Bitrix24:** используется D7, модули `bizproc`, `crm`.

## Концепция запуска БП (ключевой раздел)

### Зачем нужен запуск, а не только привязка

Администратор выбирает шаблон БП в настройках поля. При нажатии кнопки в карточке CRM пользователь должен **запустить** этот БП для текущего элемента. Результат:

- Создаётся экземпляр workflow (запись в `b_bp_workflow_instance`);
- БП выполняется (задачи, роботы, последовательности и т.д.);
- Пользователь получает уведомление об успехе или ошибке.

### Два сценария запуска

| Сценарий | Условие | Действие |
|----------|---------|----------|
| **A. Прямой запуск** | Шаблон без параметров (`PARAMETERS` пуст), константы настроены | AJAX → `CBPDocument::StartWorkflow` на сервере → возврат `workflowId` → уведомление «БП запущен» |
| **B. Слайдер с параметрами** | Шаблон требует параметры или константы не настроены | Открыть слайдер `bizproc.workflow.start` (или `BX.Bizproc.Starter.beginStartWorkflow(templateId)`) — пользователь заполняет форму и нажимает «Запустить» |

**Рекомендация:** для MVP реализовать **сценарий B** (слайдер) — он покрывает все случаи: параметры, константы, проверка прав. Не требуется отдельный action `startBp` на сервере: PHP возвращает `starterConfig` + `bpTemplateId`, JS открывает `BX.Bizproc.Starter.beginStartWorkflow(templateId)`. Сценарий A (прямой `CBPDocument::StartWorkflow` через AJAX) — опциональная оптимизация для шаблонов без параметров.

### Формат documentId для CRM (коробка)

Для CRM document ID — массив из 3 элементов: `[moduleId, entity, documentId]`.

| Сущность | documentId формат | Пример |
|----------|-------------------|--------|
| Лид | `['crm', 'CCrmDocumentLead', 'LEAD_{id}']` | `['crm', 'CCrmDocumentLead', 'LEAD_777']` |
| Сделка | `['crm', 'CCrmDocumentDeal', 'DEAL_{id}']` | `['crm', 'CCrmDocumentDeal', 'DEAL_123']` |
| Контакт | `['crm', 'CCrmDocumentContact', 'CONTACT_{id}']` | `['crm', 'CCrmDocumentContact', 'CONTACT_456']` |
| Компания | `['crm', 'CCrmDocumentCompany', 'COMPANY_{id}']` | `['crm', 'CCrmDocumentCompany', 'COMPANY_789']` |
| Смарт-процесс | `['crm', Dynamic::class, '{ownerTypeName}_{id}']` | `['crm', 'Bitrix\Crm\Integration\BizProc\Document\Dynamic', 'DYNAMIC_123_456']` |

`ownerTypeName` для смарт-процесса: `CCrmOwnerType::ResolveName($entityTypeId)` → например `DYNAMIC_123` (где 123 — typeId из `b_crm_dynamic_type`).

Получение documentId: `CCrmBizProcHelper::ResolveDocumentId($entityTypeId, $elementId)`.

## Модули и компоненты

### Настройки поля (форма создания/редактирования)

- `local/modules/my.bpbutton/lib/UserField/BpButtonUserType.php`  
  — метод `getSettingsHTML`: добавить выбор подтипа (URL / Запуск БП) и блок выбора шаблона БП.
- `local/modules/my.bpbutton/lib/Service/BpTemplateResolver.php` (новый)  
  — сервис получения списка шаблонов БП по `ENTITY_ID`.
- `local/modules/my.bpbutton/lib/Internals/SettingsTable.php`  
  — добавить поля `ACTION_TYPE` (enum: `url` | `bp_launch`), `BP_TEMPLATE_ID` (int, nullable).
- `local/modules/my.bpbutton/install/index.php`  
  — миграция БД: добавление колонок `ACTION_TYPE`, `BP_TEMPLATE_ID`.
- `local/modules/my.bpbutton/admin/bpbutton_edit.php`  
  — форма редактирования: отображение и редактирование `ACTION_TYPE`, `BP_TEMPLATE_ID`; при `ACTION_TYPE = bp_launch` — выбор шаблона (слайдер или выпадающий список).
- `local/modules/my.bpbutton/lib/Service/SettingsFormService.php`  
  — валидация и сохранение `ACTION_TYPE`, `BP_TEMPLATE_ID`.

### Запуск БП (клик по кнопке в карточке CRM)

- `local/modules/my.bpbutton/lib/Service/ButtonService.php`  
  — расширить `getSidePanelConfig` или добавить `getButtonActionConfig`: при `ACTION_TYPE = bp_launch` возвращать `actionType`, `bpTemplateId`, `starterConfig` вместо url/title/width.
- `local/modules/my.bpbutton/lib/Controller/ButtonController.php`  
  — расширить `getConfigAction`: вызывать новый метод сервиса, возвращать расширенный ответ. Опционально: добавить `startBpAction` для прямого запуска БП.
- `bitrix/services/my.bpbutton/button/ajax.php`  
  — endpoint (уже вызывает ButtonController::getConfigAction). При добавлении `startBpAction` — роутинг по параметру `action`.
- `local/modules/my.bpbutton/install/js/my.bpbutton/button.js`  
  — в `handleResponse`: при `data.actionType === 'bp_launch'` — загрузить `bizproc.workflow.starter`, вызвать `BX.Bizproc.Starter.showTemplates` + `beginStartWorkflow(templateId)` вместо `SidePanel.open`.
- `local/modules/my.bpbutton/install/js/my.bpbutton/button.api.js`  
  — без изменений (fetchConfig остаётся тем же).
- `local/modules/my.bpbutton/lang/ru/`  
  — языковые строки для новых подписей и сообщений.

## Зависимости

- **От других задач:**
  - `TASK-001-user-type` — регистрация типа `bp_button_field`;
  - `TASK-009-field-config-ux-fixes` — форма настроек поля;
  - `TASK-010-admin-field-binding-fix` — связь с настройками по FIELD_ID.
- **От ядра Bitrix24:**
  - модуль `bizproc` — `CBPWorkflowTemplateLoader`, `CBPDocument`, `CCrmBizProcHelper`;
  - модуль `crm` — `CCrmOwnerType`, `CCrmBizProcHelper`;
  - `Bitrix\Main\UserFieldTable` — `ENTITY_ID` пользовательского поля.

## Ступенчатые подзадачи

### 1. Миграция БД: поля ACTION_TYPE и BP_TEMPLATE_ID

**Файл:** `install/index.php`

Добавить в таблицу `my_bpbutton_settings`:
- `ACTION_TYPE` — `VARCHAR(20) NULL DEFAULT 'url'` (значения: `url`, `bp_launch`);
- `BP_TEMPLATE_ID` — `INT UNSIGNED NULL` (ID шаблона из `b_bp_workflow_template`).

Обновить `SettingsTable::getMap()`.

### 2. Создать сервис BpTemplateResolver

**Файл:** `lib/Service/BpTemplateResolver.php`

Сервис с методом `getTemplatesByEntityId(string $entityId): array`:
- Вход: `ENTITY_ID` (например, `CRM_DYNAMIC_123`, `CRM_LEAD`, `CRM_DEAL`).
- Выход: массив шаблонов `[['ID' => int, 'NAME' => string], ...]`.

**Логика:**
- Преобразовать `ENTITY_ID` в entity type id: использовать `CCrmOwnerType::ResolveID($entityId)`. Метод принимает строку (`CRM_LEAD`, `CRM_DEAL`, `CRM_DYNAMIC_123` и т.д.) и возвращает числовой entity type id. Для смарт-процессов `CRM_DYNAMIC_123` корректно преобразуется в соответствующий тип.
- Вызвать `CCrmBizProcHelper::ResolveDocumentType($entityTypeId)` → document type.
- Вызвать `CBPWorkflowTemplateLoader::getList` с фильтром:
  - `DOCUMENT_TYPE` = полученный document type (массив `[module, entity, documentType]`);
  - `ACTIVE = 'Y'`;
  - `AUTO_EXECUTE` < `CBPDocumentEventType::Automation` (т.е. ручные шаблоны, не роботы) — или `AUTO_EXECUTE = 0` (Manual). Роботы (Automation) запускаются автоматически по событиям, для кнопки нужны только ручные.
- Вернуть массив `[['ID' => $row['ID'], 'NAME' => $row['NAME']], ...]`.

**Обработка ошибок:**
- Если модуль `bizproc` или `crm` не подключён — вернуть пустой массив.
- Если `ENTITY_ID` не поддерживает БП — вернуть пустой массив.

### 3. Расширить getSettingsHTML: выбор подтипа и слайдер шаблонов

**Файл:** `lib/UserField/BpButtonUserType.php`

В `getSettingsHTML`:
1. Добавить блок выбора подтипа (радио-кнопки или селект):
   - «URL обработчика» (`ACTION_TYPE = url`) — текущее поведение;
   - «Запуск бизнес-процесса» (`ACTION_TYPE = bp_launch`) — новый режим.
2. При `ACTION_TYPE = bp_launch`:
   - Получить `ENTITY_ID` из `$field['ENTITY_ID']` (при редактировании) или из контекста (при создании — если доступен).
   - Вызвать `BpTemplateResolver::getTemplatesByEntityId($entityId)`.
   - Вывести слайдер или выпадающий список с шаблонами БП.
   - Имя контрола: `{$htmlControlName}[BP_TEMPLATE_ID]` или через `SETTINGS` в `prepareSettings`.

**Особенности:**
- При создании поля `ENTITY_ID` может быть передан в `$additional` (контекст формы настройки полей сущности). Проверить структуру `$field` и `$additional` в момент вызова `getSettingsHTML`.
- Если `ENTITY_ID` недоступен при создании — показать сообщение: «Выберите шаблон БП после создания поля в разделе настроек модуля» или отложить выбор до первого сохранения.

### 4. Реализовать слайдер выбора шаблона БП

**Вариант A (простой):** выпадающий список `<select>` с шаблонами в `getSettingsHTML`. Подходит, если шаблонов немного.

**Вариант B (слайдер):** кнопка «Выбрать шаблон БП» открывает слайдер (BX.SidePanel или BX.UI.Dialogs) с компонентом `bizproc.workflow.start` в режиме выбора шаблона, либо кастомный слайдер со списком шаблонов из `BpTemplateResolver`.

**Рекомендация:** начать с варианта A (select) для MVP; при необходимости — доработать до слайдера.

### 5. prepareSettings: сохранение ACTION_TYPE и BP_TEMPLATE_ID

**Файл:** `lib/UserField/BpButtonUserType.php`

В `prepareSettings`:
- Извлечь из `$field['SETTINGS']` значения `ACTION_TYPE`, `BP_TEMPLATE_ID`.
- Сохранить в возвращаемый массив (настройки пользовательского поля хранятся в `b_user_field.SETTINGS` в сериализованном виде).
- **Важно:** настройки поля (`SETTINGS`) и настройки модуля (`my_bpbutton_settings`) — разные хранилища. Необходимо синхронизировать:
  - либо хранить `ACTION_TYPE` и `BP_TEMPLATE_ID` только в `my_bpbutton_settings` (при сохранении поля вызывать обновление SettingsTable);
  - либо дублировать в оба хранилища для согласованности.

**Предлагаемая схема:** `ACTION_TYPE` и `BP_TEMPLATE_ID` хранятся в `my_bpbutton_settings`. При отображении `getSettingsHTML` для существующего поля — читать из SettingsTable по `FIELD_ID`. При сохранении поля — обработчик `OnBeforeUserFieldUpdate` или аналогичный обновляет `my_bpbutton_settings` из данных формы (если форма настроек поля передаёт эти данные).

**Уточнение:** форма настроек поля (`main.field.config.detail`) сохраняет данные в `b_user_field`. Модуль `my.bpbutton` использует отдельную таблицу `my_bpbutton_settings` и ссылку «Настроить кнопку» на `bpbutton_edit.php`. Поэтому логично:
- Подтип и выбор шаблона БП настраиваются в **двух местах**:
  1. В `getSettingsHTML` — базовая настройка при создании/редактировании поля (если Bitrix передаёт контекст ENTITY_ID);
  2. В `bpbutton_edit.php` — полная настройка в разделе «Кнопки БП».
- Либо: расширить `getSettingsHTML` так, чтобы при выборе «Запуск БП» отображался inline-блок выбора шаблона (без перехода на другую страницу), а данные сохранялись через `prepareSettings` в `SETTINGS` поля, и при сохранении поля — обработчик `OnAfterUserFieldAdd`/`OnBeforeUserFieldUpdate` синхронизирует с `my_bpbutton_settings`.

**Итоговая схема:**
- `getSettingsHTML` выводит подтип и (при `bp_launch`) список шаблонов. Значения берутся из `SettingsTable` по `FIELD_ID` (если поле существует) или из `$field['SETTINGS']` (при создании).
- Контролы: `settings[ACTION_TYPE]`, `settings[BP_TEMPLATE_ID]` — имена, которые Bitrix передаёт в `prepareSettings`.
- В `prepareSettings` сохраняем в `SETTINGS` поля.
- Обработчик `OnBeforeUserFieldUpdate` / `OnAfterUserFieldUpdate` обновляет `my_bpbutton_settings` из `SETTINGS` (ACTION_TYPE, BP_TEMPLATE_ID).

### 6. Обновить SettingsFormService и bpbutton_edit.php

- В `SettingsFormService::validate` добавить валидацию `ACTION_TYPE`, `BP_TEMPLATE_ID`.
- В `SettingsFormService::save` сохранять `ACTION_TYPE`, `BP_TEMPLATE_ID`.
- В `bpbutton_edit.php` добавить блок выбора подтипа и шаблона БП (аналогично getSettingsHTML, но с полным доступом к ENTITY_ID из SettingsTable).

### 7. Логика кнопки: запуск БП вместо SidePanel (детализация)

**Файлы:** `lib/Service/ButtonService.php`, `lib/Controller/ButtonController.php` (или аналог), JS (`button.api.js`, `button.sidepanel.js`, `button.js` — обработчик клика).

#### 7.1. Изменение контракта API (ajax.php)

Текущий endpoint `/bitrix/services/my.bpbutton/button/ajax.php` возвращает `getSidePanelConfig` (url, title, width). Расширить:

- Добавить в ответ поле `actionType`: `'url'` | `'bp_launch'`.
- При `actionType = 'bp_launch'` добавить:
  - `bpTemplateId` — ID шаблона;
  - `starterConfig` — объект для `BX.Bizproc.Starter` (опционально): `{ signedDocumentType, signedDocumentId }` — см. `CCrmBizProcHelper::getBpStarterConfig($entityTypeId, $elementId)`.

#### 7.2. Алгоритм на стороне PHP (ButtonService)

```php
// Псевдокод getButtonActionConfig()
if (($settings['ACTION_TYPE'] ?? 'url') === 'bp_launch' && ($templateId = (int)($settings['BP_TEMPLATE_ID'] ?? 0)) > 0) {
    $entityTypeId = $this->resolveEntityTypeIdFromEntityId($settings['ENTITY_ID']);
    $documentId = CCrmBizProcHelper::ResolveDocumentId($entityTypeId, $elementId);
    $starterConfig = CCrmBizProcHelper::getBpStarterConfig($entityTypeId, $elementId);
    return [
        'actionType' => 'bp_launch',
        'bpTemplateId' => $templateId,
        'starterConfig' => $starterConfig,
        'documentId' => $documentId, // или signedDocumentId для JS
    ];
}
return ['actionType' => 'url', 'url' => ..., 'title' => ..., 'width' => ...];
```

#### 7.3. Алгоритм на стороне JS (обработчик клика)

**Важно:** в `handleResponse` проверять `actionType` **до** проверки `url`, т.к. при `bp_launch` поле `url` будет пустым.

1. Вызвать `ButtonApi.fetchConfig({ entityId, elementId, fieldId })`.
2. Если `response.success && response.data.actionType === 'bp_launch'`:
   - Загрузить `BX.Runtime.loadExtension('bizproc.workflow.starter')`.
   - Создать `new Starter(data.starterConfig)` (signedDocumentType, signedDocumentId из ответа).
   - Вызвать `starter.beginStartWorkflow(data.bpTemplateId)` — откроется слайдер Bitrix с формой запуска БП (параметры, константы — при необходимости). Пользователь нажимает «Запустить» в слайдере.
   - По завершении — `State.setIdle(buttonEl)`, опционально уведомление «БП запущен».
3. Иначе (actionType = 'url' или отсутствует) — текущая логика: `ButtonSidePanel.open(url, title, width, context)`.

#### 7.4. Серверный action startBp (опционально, для сценария A)

Если реализуется **прямой запуск** (сценарий A) для шаблонов без параметров — добавить action в `ButtonController`:

- Вход: `templateId`, `entityId`, `elementId`, `fieldId`, `sessid`.
- Проверки: сессия, права на чтение CRM-сущности, `CBPDocument::canUserOperateDocument(CBPCanUserOperateOperation::StartWorkflow, $userId, $documentId, ['WorkflowTemplateId' => $templateId])`.
- Проверка: шаблон не имеет параметров (`PARAMETERS` пуст), константы настроены (`isConstantsTuned`).
- Вызов: `$errors = []; $wfId = CBPDocument::StartWorkflow($templateId, $documentId, [CBPDocument::PARAM_TAGRET_USER => 'user_' . $userId], $errors);`
- Ответ: `{ success: true, data: { workflowId: $wfId } }` или `{ success: false, error: { code, message } }`.

**Важно:** `arParameters` должен содержать `CBPDocument::PARAM_TAGRET_USER => 'user_' . $GLOBALS['USER']->GetID()` — иначе возможны ошибки при выполнении БП.

Для MVP (сценарий B) этот action **не требуется**.

### 8. Локализация

Добавить в `lang/ru/`:
- `BPBUTTON_ACTION_TYPE_URL` — «URL обработчика»;
- `BPBUTTON_ACTION_TYPE_BP_LAUNCH` — «Запуск бизнес-процесса»;
- `BPBUTTON_BP_TEMPLATE_SELECT` — «Выберите шаблон БП»;
- `BPBUTTON_BP_TEMPLATE_EMPTY` — «Нет шаблонов БП для данной сущности»;
- `BPBUTTON_BP_TEMPLATE_SELECT_HINT` — «Шаблоны отображаются для типа смарт-процесса, к которому привязано поле».

## API-методы Bitrix24 (коробка)

### Получение шаблонов

- **CBPWorkflowTemplateLoader::getList** — получение списка шаблонов по document type.
  - Фильтр: `DOCUMENT_TYPE` = `['crm', $docName, $ownerTypeName]`, `ACTIVE = 'Y'`.
  - Для ручного запуска: `AUTO_EXECUTE < CBPDocumentEventType::Automation` (или `AUTO_EXECUTE = 0` для Manual).
- **CBPWorkflowTemplateLoader::getDocumentTypeStates** — альтернатива, возвращает шаблоны + состояния для документа.

### Маппинг сущностей

- **CCrmBizProcHelper::ResolveDocumentType($entityTypeId)** — маппинг entity type → document type `[module, entity, documentType]`.
- **CCrmBizProcHelper::ResolveDocumentId($entityTypeId, $ownerId)** — формирование document ID `[module, entity, documentId]`.
- **CCrmOwnerType::ResolveID($entityId)** — преобразование строки `CRM_DYNAMIC_123` → числовой entity type id.
- **CCrmOwnerType::DynamicTypeId($typeId)** — для смарт-процессов: typeId из `b_crm_dynamic_type` → entity type id.

### Запуск БП (ключевой метод)

**CBPDocument::StartWorkflow** — документация: https://dev.1c-bitrix.ru/api_help/bizproc/bizproc_classes/cbpdocument/startworkflow.php

```php
string CBPDocument::StartWorkflow(
    int $workflowTemplateId,   // ID шаблона из b_bp_workflow_template
    array $documentId,         // ['crm', 'CCrmDocumentLead', 'LEAD_123']
    array $arParameters,       // параметры запуска (обязательно PARAM_TAGRET_USER)
    array &$arErrors           // по ссылке, заполняется при ошибках
);
```

**Параметры запуска (`$arParameters`):**
- `CBPDocument::PARAM_TAGRET_USER` => `'user_' . $userId` — **обязательно** для корректной работы БП.
- Дополнительные параметры — если шаблон имеет `PARAMETERS` (входные параметры), они передаются сюда (ключ = имя параметра).

**Возврат:** ID запущенного workflow (строка) или пустая строка при ошибке. Ошибки в `$arErrors`: `[['code' => int, 'message' => string, 'file' => string], ...]`.

### Проверка прав

- **CBPDocument::canUserOperateDocument(CBPCanUserOperateOperation::StartWorkflow, $userId, $documentId, ['WorkflowTemplateId' => $templateId])** — проверка права пользователя на запуск конкретного шаблона для документа.

### Константы и параметры шаблона

- **CBPWorkflowTemplateLoader::isConstantsTuned($templateId)** — проверка, настроены ли константы шаблона. Если `false` — запуск может быть невозможен до настройки в админке.
- Шаблон может иметь `PARAMETERS` — входные параметры при запуске. Если есть — нужно либо передать значения в `arParameters`, либо показать форму пользователю (слайдер).

### JS API (BX.Bizproc.Starter)

Расширение: `bizproc.workflow.starter` (подключается через `BX.Runtime.loadExtension`).

- **new BX.Bizproc.Starter({ signedDocumentType, signedDocumentId })** — создание экземпляра. Параметры из `CCrmBizProcHelper::getBpStarterConfig($entityTypeId, $elementId)`.
- **starter.beginStartWorkflow(templateId)** — открывает слайдер запуска БП для указанного шаблона. Если шаблон требует параметры — пользователь заполняет форму в слайдере и нажимает «Запустить». Возвращает Promise, при успехе в событии `onAfterStartWorkflow` передаётся `workflowId`.

**Пример использования в button.js:**
```javascript
if (data.actionType === 'bp_launch' && data.bpTemplateId && data.starterConfig) {
    BX.Runtime.loadExtension('bizproc.workflow.starter').then(function(exports) {
        var Starter = exports.Starter;
        var starter = new Starter(data.starterConfig);
        starter.beginStartWorkflow(data.bpTemplateId).then(function() {
            State.notify('Бизнес-процесс запущен');
        }).catch(function() {});
    });
    State.setIdle(buttonEl);
    return;
}
```

Документация: https://dev.1c-bitrix.ru/api_help/, модули `bizproc`, `crm`.

## Технические требования

- Коробочная версия Bitrix24, PHP 8.x, D7 ORM.
- Модули `bizproc` и `crm` должны быть установлены для работы подтипа «Запуск БП».
- При отсутствии модулей — подтип «Запуск БП» не отображать или показывать с сообщением «Требуется модуль bizproc».
- Слайдер: использовать стандартные компоненты Bitrix24 (BX.SidePanel, BX.UI) или простой `<select>` для MVP.
- Обратная совместимость: при `ACTION_TYPE = null` или `url` — поведение как сейчас (SidePanel по URL).

## Критерии приёмки

- [ ] В настройках поля `bp_button_field` отображается выбор подтипа: «URL обработчика» и «Запуск бизнес-процесса».
- [ ] При выборе «Запуск бизнес-процесса» отображается слайдер или выпадающий список с шаблонами БП для `ENTITY_ID` поля.
- [ ] Шаблоны БП фильтруются по типу сущности (смарт-процесс, лид, сделка и т.д.).
- [ ] Выбранный шаблон сохраняется в `my_bpbutton_settings.BP_TEMPLATE_ID`.
- [ ] При нажатии кнопки в карточке CRM в режиме «Запуск БП» выполняется запуск выбранного шаблона БП для текущего элемента.
- [ ] Для сущностей без шаблонов БП выводится сообщение «Нет шаблонов БП для данной сущности».
- [ ] Все новые тексты локализованы.
- [ ] Нет регрессии: режим «URL обработчика» работает как прежде.

## Тестирование

- **Сценарий 1:** Создать поле `bp_button_field` для смарт-процесса с настроенными шаблонами БП. Выбрать подтип «Запуск бизнес-процесса», выбрать шаблон. Сохранить. Открыть карточку элемента, нажать кнопку — БП должен запуститься.
- **Сценарий 2:** Создать поле для сущности без шаблонов БП. Выбрать «Запуск БП» — должен отображаться пустой список с сообщением.
- **Сценарий 3:** Переключить подтип обратно на «URL обработчика» — кнопка должна открывать SidePanel по URL.
- **Сценарий 4:** Редактировать настройки в `bpbutton_edit.php` — подтип и шаблон должны сохраняться и отображаться корректно.

## Примеры кода (опционально)

### BpTemplateResolver::getTemplatesByEntityId (псевдокод)

```php
public function getTemplatesByEntityId(string $entityId): array
{
    if (!Loader::includeModule('bizproc') || !Loader::includeModule('crm')) {
        return [];
    }
    $entityTypeId = $this->resolveEntityTypeId($entityId);
    if ($entityTypeId <= 0) {
        return [];
    }
    $documentType = CCrmBizProcHelper::ResolveDocumentType($entityTypeId);
    if (!$documentType) {
        return [];
    }
    $templates = [];
    $res = CBPWorkflowTemplateLoader::getList(
        ['SORT' => 'ASC', 'NAME' => 'ASC'],
        [
            'DOCUMENT_TYPE' => $documentType,
            'ACTIVE' => 'Y',
        ]
    );
    while ($row = $res->Fetch()) {
        $templates[] = ['ID' => (int)$row['ID'], 'NAME' => (string)$row['NAME']];
    }
    return $templates;
}
```

### resolveEntityTypeId

```php
private function resolveEntityTypeId(string $entityId): int
{
    $entityTypeId = CCrmOwnerType::ResolveID($entityId);
    return ($entityTypeId > 0) ? $entityTypeId : 0;
}
```

## Граничные случаи и ошибки

- **Шаблон удалён:** если `BP_TEMPLATE_ID` указывает на несуществующий шаблон — при запуске `CBPDocument::StartWorkflow` вернёт ошибку. Обработать в UI: «Шаблон БП не найден. Обновите настройки кнопки».
- **Константы не настроены:** `CBPWorkflowTemplateLoader::isConstantsTuned($templateId) === false` — слайдер `beginStartWorkflow` покажет ошибку «Требуется настройка констант». Администратор должен настроить константы в разделе БП.
- **Нет прав на запуск:** `CBPDocument::canUserOperateDocument(StartWorkflow, ...)` вернёт false — показать «Недостаточно прав для запуска БП».
- **Модуль bizproc отключён:** не показывать подтип «Запуск БП» или показывать с сообщением «Требуется модуль bizproc».
- **Расширение bizproc.workflow.starter не загружается:** в JS обработать reject Promise, показать уведомление «Не удалось открыть форму запуска БП».

## История правок

- 2026-03-16: Создана задача. Требование: подтип «Запуск бизнес-процесса» и слайдер с шаблонами БП по ENTITY_ID в настройках поля.
- 2026-03-16: Детализация: добавлен раздел «Концепция запуска БП», фокус на реальный запуск (не только привязку). Расширены API-методы, алгоритмы PHP/JS, граничные случаи. Формат documentId для CRM. Интеграция с BX.Bizproc.Starter.
