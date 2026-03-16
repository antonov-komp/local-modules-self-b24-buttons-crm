# TASK-005: JS‑фронтенд кнопки в карточке CRM

**Дата создания:** 2026-03-16 (UTC+3, Брест)  
**Статус:** Новая  
**Приоритет:** Высокий  
**Исполнитель:** Bitrix24 Программист (Vanilla JS)

---

## Описание

Задача описывает реализацию JS‑слоя для пользовательского типа поля `bp_button_field` в карточках CRM и смарт‑процессов.

Нужно разработать JS‑расширение, которое:

- находит HTML‑кнопки пользовательского типа по CSS‑классам и `data-*` атрибутам;
- управляет состояниями кнопки (`idle`, `loading`, `disabled`, условное `error`);
- вызывает `ButtonController.getConfigAction` с корректными параметрами;
- по ответу открывает `BX.SidePanel`/iframe с учётом `url/title/width/context`;
- обрабатывает коды ошибок (`INVALID_SESSION`, `ACCESS_DENIED`, `SETTINGS_NOT_FOUND`, `BUTTON_INACTIVE`, `INTERNAL_ERROR`) через `BX.UI.Notification`.

---

## Контекст

- Бизнес‑и UX‑требования к кнопке описаны в:
  - `requirements.md` (FR‑002, FR‑003, FR‑008, FR‑009, NFR‑002, NFR‑004, NFR‑005);
  - `use_cases.md` (UC‑001–UC‑004).
- UX/UI‑поведение и состояния кнопки:
  - `ui_ux/crm_card_button.md`;
  - `ui_ux/states_and_errors.md`;
  - `ui_ux/style_guide.md`.
- Контракт AJAX‑контроллера и коды ошибок:
  - `api/ajax_controller.md`.
- Общая frontend‑архитектура:
  - `architecture/frontend_ui.md` (учесть, если там заданы доп. требования к JS‑слою).
- Backend‑часть:
  - `TASK-001-user-type` — HTML кнопки и `data-*` атрибуты с контекстом;
  - `TASK-003-ajax-api` — `ButtonController.getConfigAction` и формат JSON‑ответов.

JS‑код пишется в стиле коробочного Bitrix24 (Vanilla JS, `BX`‑ядро, без Vue/React).

---

## Модули и компоненты

Рекомендуемая структура (точные пути сверить с `module_structure/filesystem.md`):

- `local/modules/my.bpbutton/install/js/my.bpbutton/button.js`  
  — основное JS‑расширение для кнопки (инициализация, поиск элементов, обработка кликов, вызовы AJAX, открытие SidePanel, обработка ошибок).

- `local/modules/my.bpbutton/install/js/my.bpbutton/button.bundle.js` (или аналогичный итоговый файл)  
  — собранный бандл, если используется `CJSCore::RegisterExt` + сборка, либо тот же `button.js`.

- Регистрация расширения в PHP:
  - через `\CJSCore::RegisterExt('my_bpbutton.button', [...])` и `\CJSCore::Init(['my_bpbutton.button'])` в `install/index.php` / `lib/EventHandler.php`;
  - или через отдельный вспомогательный класс `Asset`.

---

## Зависимости

**От backend:**

- User type уже отрисовывает кнопку в HTML и проставляет:
  - CSS‑класс (например, `js-bpbutton-field`);
  - `data-entity-id`, `data-element-id`, `data-field-id`, `data-user-id`.
- Реализован контроллер `My\BpButton\Controller\ButtonController` (`TASK-003-ajax-api`) с action:
  - `getConfigAction(entityId, elementId, fieldId): array`.

**От инфраструктуры Bitrix24:**

- Подключено ядро JS (`BX`);
- Доступны библиотеки:
  - `ui.buttons`;
  - `ui.sidepanel`;
  - `ui.notification`.

**От будущих задач (не блокируют MVP):**

- Логирование (`TASK-004-logging`) — JS‑код должен иметь понятную точку расширения для вызова `logClickAction` в будущем, но сейчас этот вызов может отсутствовать.

---

## Модули и компоненты (детализация)

- `my_bpbutton.button` — JS‑расширение модуля:
  - точка входа: `BX.ready` или другой рекомендованный хук CRM‑карточки;
  - экспортирует объект/неймспейс (например, `BX.MyBpButton.Button`), инкапсулирующий логику:
    - инициализации;
    - поиска кнопок;
    - работы с состояниями;
    - вызовов AJAX‑контроллера;
    - открытия SidePanel;
    - обработки ошибок.

---

## Ступенчатые подзадачи

### 1. Регистрация JS‑расширения модуля

1.1. Создать файл `install/js/my.bpbutton/button.js` с каркасом модуля.

1.2. Зарегистрировать расширение:

- в `install/index.php` или отдельном классе:

```php
\CJSCore::RegisterExt('my_bpbutton.button', [
    'js'  => '/local/modules/my.bpbutton/install/js/my.bpbutton/button.js',
    'rel' => ['main.core', 'ui.buttons', 'ui.sidepanel', 'ui.notification'],
]);
```

- инициализировать его на страницах, где отображается `bp_button_field`:
  - либо в user type (метод отображения добавляет `CJSCore::Init(['my_bpbutton.button'])`);
  - либо через общий EventHandler, если есть единая точка подключения для CRM‑карточек.

**Требование:** расширение не должно грузиться на всех страницах админки/портала без нужды — только там, где потенциально есть `bp_button_field`.

---

### 2. Поиск и инициализация кнопок на странице

2.1. Внутри `button.js`:

- дождаться готовности ядра:

```javascript
BX.ready(function () {
    BX.MyBpButton && BX.MyBpButton.Button && BX.MyBpButton.Button.init();
});
```

2.2. В `init()`:

- найти все элементы кнопок по CSS‑классу user type (согласовать с backend, например, `js-bpbutton-field`);
- для каждого элемента:
  - прочитать `data-entity-id`, `data-element-id`, `data-field-id`, `data-user-id`;
  - инициализировать объект состояния (например, сохранить в `WeakMap` или навесить данные через `dataset`);
  - навесить обработчик клика, если кнопка не помечена как `disabled`.

2.3. Учесть перерисовку части интерфейса Bitrix (AJAX подгрузка полей, обновление карточки):

- предусмотреть возможность переинициализации (повторный вызов `init()`):
  - избегать повторного навешивания обработчиков, использовать проверку флага `data-bpbutton-init="Y"` или аналог.

---

### 3. Модель состояний кнопки

3.1. Определить в JS внутренние состояния:

- `idle` — кнопка готова к нажатию;
- `loading` — запрос отправлен, пользователь ждёт;
- `disabled` — действие недоступно;
- `error` — краткое визуальное состояние при ошибке (надстройка над `idle`/`disabled`).

3.2. Реализовать функции:

- `setIdle(buttonEl)`:
  - удалить `ui-btn-wait`;
  - убедиться, что обработчик клика активен (если по бизнес‑логике кнопка не отключена);
  - актуализировать внутренний флаг состояния.

- `setLoading(buttonEl)`:
  - добавить `ui-btn-wait`;
  - заблокировать повторные клики (флаг/отключение listener’а);
  - не изменять текст кнопки (если нет отдельного UX‑требования).

- `setDisabled(buttonEl)`:
  - добавить `ui-btn-disabled`;
  - снять/игнорировать обработчик клика;
  - сохранить состояние `disabled`, чтобы не возвращать в `idle` после следующих ошибок.

- `flashError(buttonEl)` (опционально):
  - краткая подсветка (если UX из `states_and_errors.md` предполагает);
  - не обязательна для первой версии, достаточно корректного уведомления.

3.3. Гарантировать переходы:

- `idle → loading` при первом клике;
- `loading → idle` при успешном ответе;
- `loading → idle/error` при ошибке (никогда не остаётся `loading`);
- `idle → disabled` при `BUTTON_INACTIVE`/бизнес‑ограничениях.

---

### 4. Вызов `ButtonController.getConfigAction`

4.1. В обработчике клика:

- считать из `dataset`:
  - `entityId = data-entity-id`;
  - `elementId = data-element-id`;
  - `fieldId = data-field-id`;
- получить `sessid`:
  - через `BX.bitrix_sessid()` или из скрытого поля `bitrix_sessid`.

4.2. Перевести кнопку в `loading`:

```javascript
BX.MyBpButton.Button.setLoading(buttonEl);
```

4.3. Отправить запрос к контроллеру:

- использовать `BX.ajax.runComponentAction` или `BX.ajax`/`BX.ajax.post` (в зависимости от реализованного endpoint’а, см. `api/ajax_controller.md` и `TASK-003-ajax-api`);
- тело запроса (пример для JSON):

```javascript
BX.ajax({
    url: BX.MyBpButton.Button.getConfigUrl(), // сконфигурированный URL
    method: 'POST',
    dataType: 'json',
    data: {
        entityId: entityId,
        elementId: elementId,
        fieldId: fieldId,
        sessid: BX.bitrix_sessid()
    },
    onsuccess: onSuccess,
    onfailure: onFailure
});
```

4.4. Реализовать `onSuccess`:

- проверить, что ответ имеет вид:

```json
{
  "success": true,
  "data": { },
  "error": null
}
```

или

```json
{
  "success": false,
  "data": null,
  "error": { }
}
```

- при `success: true`:
  - вызвать обработку открытия SidePanel (см. следующий раздел);
- при `success: false`:
  - вызвать обработку ошибки.

4.5. Реализовать `onFailure`/`catch`:

- обработать сетевые/форматные ошибки (нет связи, невалидный JSON):
  - вернуть кнопку в `idle`;
  - показать общее уведомление по типу `INTERNAL_ERROR`.

---

### 5. Открытие `BX.SidePanel`

5.1. При `success: true` и валидном `data`:

- получить:
  - `url` — базовый URL обработчика;
  - `title` — заголовок окна;
  - `width` — строка (`"70%"`) или число (`1200`) — интерпретировать согласно контракту;
  - `context` — объект `entityId`, `elementId`, `fieldId`, `userId`.

5.2. Подготовить итоговый URL:

- если backend уже включает параметры контекста в `url` — просто использовать его;
- если нет — добавить `ENTITY_ID`, `ELEMENT_ID`, `FIELD_ID`, `USER_ID` как query‑параметры.

5.3. Вызвать SidePanel:

```javascript
BX.SidePanel.Instance.open(finalUrl, {
    width: width,          // поддержка px/% по контракту
    cacheable: false,
    allowChangeHistory: false,
    label: { text: title }
});
```

5.4. После успешного вызова:

- перевести кнопку в `idle` (если бизнес‑логика не требует иного).

---

### 6. Обработка ошибок и UX‑реакции

6.1. При `success: false`:

- гарантированно снять состояние `loading`:

```javascript
BX.MyBpButton.Button.setIdle(buttonEl);
```

- прочитать:

```javascript
var code = response.error && response.error.code;
var message = response.error && response.error.message;
```

6.2. Вызвать уведомление (без тех. деталей):

```javascript
BX.UI.Notification.Center.notify({
    content: message || BX.message('MY_BPBTN_ERROR_DEFAULT'),
    autoHideDelay: 5000
});
```

6.3. Поведение по кодам (см. `ui_ux/states_and_errors.md`):

- `INVALID_SESSION`:
  - кнопка остаётся в `idle`;
  - текст: «Сессия истекла. Обновите страницу и повторите попытку.»
- `ACCESS_DENIED`:
  - кнопка остаётся в `idle` (либо по решению может временно блокироваться);
  - текст: «Недостаточно прав для выполнения действия.»
- `SETTINGS_NOT_FOUND`:
  - кнопка остаётся в `idle`;
  - текст: «Кнопка не настроена. Обратитесь к администратору.»
- `BUTTON_INACTIVE`:
  - перевести кнопку в состояние `disabled` (добавить `ui-btn-disabled`, убрать обработчик);
  - текст: «Действие недоступно. Кнопка отключена администратором.»
- `INTERNAL_ERROR`:
  - кнопка возвращается в `idle`;
  - текст: «Произошла ошибка. Попробуйте позже или обратитесь к администратору.»

6.4. Во всех случаях:

- кнопка **не должна** оставаться в `loading`;
- сообщения должны быть локализованы (через `BX.message` и языковые файлы на PHP‑стороне).

---

### 7. Подготовка точки расширения под логирование

7.1. В JS реализовать отдельную функцию‑заготовку (без фактического вызова backend):

```javascript
BX.MyBpButton.Button.logClick = function (buttonEl, context, status, message) {
    // TODO: реализация по TASK-004-logging / logClickAction
};
```

7.2. В местах:

- успешного получения конфигурации;
- (в будущем) успешного открытия SidePanel;
- обработки ошибок

предусмотреть (комментарием или вызовом пустой функции) вызовы `logClick`, чтобы при реализации `TASK-004-logging` не пришлось рефакторить основную логику.

---

## API‑методы

JS‑слой использует уже описанный контроллер (`api/ajax_controller.md`):

- `getConfigAction(entityId, elementId, fieldId)`:
  - вход: `entityId`, `elementId`, `fieldId`, `sessid`;
  - выход (успех):

```json
{
  "success": true,
  "data": {
    "url": "...",
    "title": "...",
    "width": "...",
    "context": {
      "entityId": "...",
      "elementId": 123,
      "fieldId": 456,
      "userId": 789
    }
  }
}
```

  - выход (ошибка):

```json
{
  "success": false,
  "error": {
    "code": "ACCESS_DENIED",
    "message": "Недостаточно прав для доступа к элементу CRM."
  }
}
```

Новых REST‑методов со стороны JS‑слоя не добавляется.

---

## Технические требования

- Только Vanilla JS + ядро Bitrix (`BX`), без фреймворков.
- Код оформлен как переиспользуемое JS‑расширение:
  - поддерживает многократную инициализацию без дублирования обработчиков;
  - не создаёт глобальных переменных кроме одного неймспейса `BX.MyBpButton` (или другого согласованного).
- Используются стандартные UI‑классы:
  - `ui-btn`, `ui-btn-primary` (или альтернативы из user type);
  - `ui-btn-wait`, `ui-btn-disabled`.
- Ошибки не приводят к:
  - «залипанию» кнопки в `loading`;
  - утечке технологических подробностей (stack trace, SQL) в интерфейс пользователя.
- Поведение корректно в светлой и тёмной темах:
  - никаких жёстких кастомных цветов, только стандартные классы и минимальный CSS при необходимости.

---

## Критерии приёмки

- [ ] На карточке CRM для любого поля `bp_button_field` JS‑слой корректно находит кнопки и инициализирует обработчики.
- [ ] При клике по кнопке:
  - [ ] кнопка переходит в состояние `loading` (визуально — `ui-btn-wait`) не позднее 100 мс;
  - [ ] отправляется запрос к `getConfigAction` с корректными `entityId`, `elementId`, `fieldId`, `sessid`.
- [ ] При успешном ответе:
  - [ ] кнопка возвращается в `idle`;
  - [ ] открывается `BX.SidePanel` с URL, заголовком и шириной из ответа;
  - [ ] в обработчик передаётся контекст (`ENTITY_ID`, `ELEMENT_ID`, `FIELD_ID`, `USER_ID`).
- [ ] При ошибках `INVALID_SESSION`, `ACCESS_DENIED`, `SETTINGS_NOT_FOUND`, `BUTTON_INACTIVE`, `INTERNAL_ERROR`:
  - [ ] кнопка не остаётся в `loading`;
  - [ ] пользователь видит локализованное, понятное уведомление;
  - [ ] при `BUTTON_INACTIVE` кнопка переходит в `disabled` и перестаёт отправлять запросы.
- [ ] JS‑код не генерирует ошибок в консоли в штатных сценариях и при типовых ошибках backend’а.
- [ ] Повторное открытие/закрытие карточек и динамическая подгрузка полей не приводят к «задвоению» обработчиков.

---

## Тестирование

**Функциональные проверки:**

- Создать по одному полю `bp_button_field` для:
  - Лида, Сделки, Контакта, Компании, Смарт‑процесса.
- Для каждой сущности:
  - открыть карточку;
  - нажать кнопку с валидными настройками:
    - убедиться, что:
      - кнопка уходит в `loading`;
      - запрашивается `getConfigAction` с корректными параметрами;
      - открывается правильный SidePanel с ожидаемым URL и заголовком.

**Негативные сценарии:**

- Отсутствуют настройки (`SETTINGS_NOT_FOUND`):
  - удалить/исказить запись в `my_bpbutton_settings`;
  - нажать кнопку и проверить:
    - возврат в `idle`;
    - уведомление вида «Кнопка не настроена. Обратитесь к администратору.».
- Кнопка отключена (`BUTTON_INACTIVE`):
  - установить `ACTIVE = 'N'` в настройках;
  - нажать кнопку:
    - убедиться, что она перестаёт быть активной и отображает уведомление.
- Недостаточно прав (`ACCESS_DENIED`):
  - под пользователем без прав на элемент CRM нажать кнопку:
    - увидеть соответствующее уведомление, кнопка возвращается в `idle`.
- Невалидная/отсутствующая сессия (`INVALID_SESSION`):
  - искусственно испортить `sessid` (или воспроизвести истечение сессии);
  - проверить корректное сообщение и отсутствие «залипания».

**UX‑проверки:**

- В светлой и тёмной темах:
  - кнопка выглядит согласованно с Bitrix24 UI;
  - состояния `idle`, `loading`, `disabled` различимы;
  - уведомления читаемы.

**Технические проверки:**

- Открыть консоль браузера:
  - нет неотловленных исключений при кликах, ошибках, многократной инициализации.
- Проверить сценарий:
  - AJAX‑перерисовка карточки (если воспроизводимо в CRM) не приводит к утечкам обработчиков и некорректным состояниям кнопки.

