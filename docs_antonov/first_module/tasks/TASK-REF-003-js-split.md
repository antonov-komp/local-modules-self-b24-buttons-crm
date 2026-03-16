# TASK-REF-003: Рефакторинг JS-слоя (frontend)

**Дата создания:** 2026-03-16 (UTC+3, Брест)  
**Статус:** Новая  
**Приоритет:** Высокий  
**Исполнитель:** Bitrix24 Программист (коробка, Vanilla JS)  
**Связь с планом:** REFACTOR-PLAN-001-five-stages.md, Этап 3

---

## Описание

Файл `install/js/my.bpbutton/button.js` (~397 строк) объединяет в одном месте: управление состоянием кнопки (idle, loading, disabled), уведомления, парсинг ширины SidePanel, формирование URL с контекстом, инициализацию и привязку событий, запрос к API, открытие SidePanel, обработку ответов и ошибок.

**Цель задачи:** Разделить ответственности на отдельные модули. Результат — JS-модули с чёткой зоной ответственности, упрощённое тестирование и доработка.

---

## Контекст

### Текущая структура button.js

| Блок | Строки (прибл.) | Ответственность |
|------|-----------------|-----------------|
| IIFE, проверка BX | 1–11 | Обёртка |
| stateMap, getState, setState | 11–57 | Хранение и изменение состояния кнопки |
| notify, message | 58–76 | Уведомления и локализация |
| parseWidth | 82–119 | Парсинг ширины (пиксели/проценты) для SidePanel |
| ensureContextUrl | 121–161 | Добавление query-параметров к URL |
| BX.MyBpButton.Button | 163–388 | Основной объект: init, bind, setIdle, setLoading, setDisabled, flashError, getConfigUrl, onClick, handleResponse, logClick |
| BX.ready | 390–396 | Инициализация при загрузке |

### Точки вызова

- **Extension:** `my_bpbutton.button` — подключается в `BpButtonUserType::getPublicViewHTML` через `Extension::load('my_bpbutton.button')`
- **Зависимости:** `main.core`, `ui.buttons`, `ui.sidepanel`, `ui.notification`
- **API:** `/bitrix/services/my.bpbutton/button/ajax.php` — POST с entityId, elementId, fieldId, sessid

### Контракт data-атрибутов (из TASK-REF-001)

| Атрибут | Описание |
|---------|----------|
| `data-entity-id` | Тип сущности CRM |
| `data-element-id` | ID элемента |
| `data-field-id` | ID пользовательского поля |
| `data-user-id` | ID текущего пользователя |
| `class="js-bpbutton-field"` | Селектор для инициализации |

---

## Модули и компоненты

### Новые файлы

| Путь | Назначение |
|------|------------|
| `local/modules/my.bpbutton/install/js/my.bpbutton/button.state.js` | Управление состоянием: getState, setState, notify, message. Методы UI: setIdle, setLoading, setDisabled, flashError. |
| `local/modules/my.bpbutton/install/js/my.bpbutton/button.utils.js` | Утилиты: parseWidth, ensureContextUrl. |
| `local/modules/my.bpbutton/install/js/my.bpbutton/button.api.js` | Запрос к API: getConfigUrl, fetchConfig (BX.ajax), формат ответа. |
| `local/modules/my.bpbutton/install/js/my.bpbutton/button.sidepanel.js` | Открытие SidePanel: open(url, title, width, context). |

### Изменяемые файлы

| Путь | Изменения |
|------|-----------|
| `local/modules/my.bpbutton/install/js/my.bpbutton/button.js` | Только оркестрация: init, bind, onClick, handleResponse, logClick. Делегирование в модули. ~120–150 строк. |
| `local/modules/my.bpbutton/include.php` | Обновить `'js'` — массив файлов в порядке загрузки. |
| `local/modules/my.bpbutton/install/index.php` | Обновить `InstallJS()` — массив файлов. |

### Порядок загрузки модулей

```text
1. button.state.js   — состояние, уведомления
2. button.utils.js   — parseWidth, ensureContextUrl
3. button.api.js     — getConfigUrl, fetchConfig
4. button.sidepanel.js — open
5. button.js         — main entry, init, bind, onClick, handleResponse
```

---

## Зависимости

### От каких модулей/задач зависит

- TASK-REF-001 (UserField split) — контракт data-атрибутов не меняется
- API endpoint: `/bitrix/services/my.bpbutton/button/ajax.php`
- BX.* API: BX.ajax, BX.ready, BX.UI.Notification, BX.SidePanel

### Какие задачи зависят от этой

- TASK-REF-005 (Structure & docs) — обновление схемы модуля

---

## Детальная спецификация модулей

### 1. button.state.js

**Глобальный объект:** `BX.MyBpButton.ButtonState`

**Назначение:** Управление состоянием кнопки и уведомлениями.

**Функции (внутренние, через замыкание):**

- `getState(buttonEl)` — возвращает `{ status, disabledByBusiness }`. status: 'idle' | 'loading' | 'disabled'.
- `setState(buttonEl, next)` — обновляет состояние. Поддержка WeakMap или fallback на data-атрибуты.
- `notify(content)` — показ уведомления через BX.UI.Notification.Center.notify.
- `message(key, fallback)` — получение строки через BX.message(key) или fallback.

**Публичный API (BX.MyBpButton.ButtonState):**

```javascript
{
    getState: function(buttonEl) { ... },
    setState: function(buttonEl, next) { ... },
    notify: function(content) { ... },
    message: function(key, fallback) { ... },
    setIdle: function(buttonEl) { ... },      // BX.removeClass ui-btn-wait, ui-btn-disabled
    setLoading: function(buttonEl) { ... },   // BX.addClass ui-btn-wait
    setDisabled: function(buttonEl) { ... }, // BX.addClass ui-btn-disabled, disabledByBusiness
    flashError: function(buttonEl) { ... }   // ui-btn-danger на 900ms
}
```

**Зависимости:** BX, BX.addClass, BX.removeClass, BX.hasClass, BX.mergeEx (для setState), BX.UI.Notification, BX.message.

---

### 2. button.utils.js

**Глобальный объект:** `BX.MyBpButton.ButtonUtils`

**Назначение:** Утилиты для работы с URL и параметрами SidePanel.

**Методы:**

```javascript
{
    /**
     * Преобразует width в число (пиксели) для BX.SidePanel.
     * @param {string|number} width — '800', '80%', 800
     * @returns {number|undefined}
     */
    parseWidth: function(width) { ... },

    /**
     * Добавляет контекст (ENTITY_ID, ELEMENT_ID, FIELD_ID, USER_ID) к URL.
     * @param {string} url
     * @param {object} context — { entityId, elementId, fieldId, userId }
     * @returns {string}
     */
    ensureContextUrl: function(url, context) { ... }
}
```

**Логика parseWidth:** Цифры → parseInt. Проценты (80%) → window.innerWidth * percent / 100. Иначе → undefined.

**Логика ensureContextUrl:** Собирает query из context, добавляет к url через ? или &.

---

### 3. button.api.js

**Глобальный объект:** `BX.MyBpButton.ButtonApi`

**Назначение:** Запрос конфигурации кнопки к backend.

**Методы:**

```javascript
{
    /**
     * URL для запроса конфигурации.
     * @returns {string}
     */
    getConfigUrl: function() {
        return '/bitrix/services/my.bpbutton/button/ajax.php';
    },

    /**
     * Запрос конфигурации кнопки.
     * @param {object} params — { entityId, elementId, fieldId }
     * @param {function} onSuccess — (response) => void
     * @param {function} onFailure — () => void
     */
    fetchConfig: function(params, onSuccess, onFailure) { ... }
}
```

**Формат запроса:** POST, dataType: 'json', data: { entityId, elementId, fieldId, sessid }.

**Формат ответа (успех):** `{ success: true, data: { url, title, width, context } }`.

**Формат ответа (ошибка):** `{ success: false, error: { code, message } }`.

---

### 4. button.sidepanel.js

**Глобальный объект:** `BX.MyBpButton.ButtonSidePanel`

**Назначение:** Открытие SidePanel с параметрами.

**Методы:**

```javascript
{
    /**
     * Открывает SidePanel.
     * @param {string} url
     * @param {string} title
     * @param {string|number} width — '800', '80%', 800
     * @param {object} context — { entityId, elementId, fieldId, userId }
     * @throws {Error} — если BX.SidePanel.Instance недоступен
     */
    open: function(url, title, width, context) { ... }
}
```

**Логика:** Вызывает `parseWidth` и `ensureContextUrl` из ButtonUtils. `BX.SidePanel.Instance.open(url, { width, cacheable: false, allowChangeHistory: false, label: { text: title } })`.

---

### 5. button.js (main, после рефакторинга)

**Глобальный объект:** `BX.MyBpButton.Button`

**Назначение:** Оркестрация: инициализация, привязка клика, обработка ответа.

**Методы:**

```javascript
{
    selectors: { button: '.js-bpbutton-field' },
    init: function() { ... },
    bind: function(buttonEl) { ... },
    onClick: function(event, buttonEl) { ... },
    handleResponse: function(buttonEl, response, fallbackContext) { ... },
    logClick: function(buttonEl, context, status, messageText) { ... }
}
```

**Логика onClick:**

1. event.preventDefault, stopPropagation
2. Проверка состояния (loading, disabled) — return
3. Извлечение entityId, elementId, fieldId, userId из data-атрибутов
4. ButtonState.setLoading(buttonEl)
5. ButtonApi.fetchConfig({ entityId, elementId, fieldId }, onSuccess, onFailure)
6. onSuccess → handleResponse(buttonEl, response, context)
7. onFailure → ButtonState.setIdle, notify, logClick(INTERNAL_ERROR)

**Логика handleResponse:**

1. Невалидный response → setIdle, notify, logClick, return
2. success && data:
   - url = ensureContextUrl(data.url, data.context)
   - title = data.title
   - width = parseWidth(data.width)
   - Если !url → setIdle, notify, logClick, return
   - try: ButtonSidePanel.open(url, title, width, context)
   - catch: notify, logClick
   - finally: setIdle
   - logClick(SUCCESS)
3. Ошибка (success: false):
   - setIdle
   - Если code === 'BUTTON_INACTIVE' → setDisabled(buttonEl)
   - notify(msg)
   - logClick(code, msg)

---

## Ступенчатые подзадачи

### Подзадача 1: Создать button.state.js

1.1. Создать файл `install/js/my.bpbutton/button.state.js`  
1.2. IIFE, проверка BX  
1.3. Перенести stateMap, getState, setState  
1.4. Перенести notify, message  
1.5. Реализовать setIdle, setLoading, setDisabled, flashError (используют getState/setState)  
1.6. Экспортировать в `BX.MyBpButton.ButtonState`

### Подзадача 2: Создать button.utils.js

2.1. Создать файл `install/js/my.bpbutton/button.utils.js`  
2.2. IIFE, проверка BX  
2.3. Перенести parseWidth  
2.4. Перенести ensureContextUrl  
2.5. Экспортировать в `BX.MyBpButton.ButtonUtils`

### Подзадача 3: Создать button.api.js

3.1. Создать файл `install/js/my.bpbutton/button.api.js`  
3.2. IIFE, проверка BX  
3.3. Реализовать getConfigUrl  
3.4. Реализовать fetchConfig (BX.ajax, onsuccess, onfailure)  
3.5. Экспортировать в `BX.MyBpButton.ButtonApi`

### Подзадача 4: Создать button.sidepanel.js

4.1. Создать файл `install/js/my.bpbutton/button.sidepanel.js`  
4.2. IIFE, проверка BX  
4.3. Реализовать open(url, title, width, context) — вызов ButtonUtils.parseWidth, ensureContextUrl, BX.SidePanel.Instance.open  
4.4. Экспортировать в `BX.MyBpButton.ButtonSidePanel`

### Подзадача 5: Рефакторинг button.js

5.1. Удалить перенесённый код (stateMap, getState, setState, notify, message, parseWidth, ensureContextUrl)  
5.2. Заменить вызовы на BX.MyBpButton.ButtonState, ButtonUtils, ButtonApi, ButtonSidePanel  
5.3. Оставить только: init, bind, onClick, handleResponse, logClick, BX.ready  
5.4. Проверить, что все зависимости доступны (модули загружаются раньше)

### Подзадача 6: Обновить регистрацию расширения

6.1. В `include.php` заменить `'js' => '...button.js'` на массив:
   ```php
   'js' => [
       '/local/modules/my.bpbutton/install/js/my.bpbutton/button.state.js',
       '/local/modules/my.bpbutton/install/js/my.bpbutton/button.utils.js',
       '/local/modules/my.bpbutton/install/js/my.bpbutton/button.api.js',
       '/local/modules/my.bpbutton/install/js/my.bpbutton/button.sidepanel.js',
       '/local/modules/my.bpbutton/install/js/my.bpbutton/button.js',
   ],
   ```
6.2. В `install/index.php` метод `InstallJS()` — аналогично обновить массив js  
6.3. Проверить, что Bitrix CJSCore поддерживает массив js (документация Bitrix: да, поддерживается)

### Подзадача 7: Тестирование

7.1. Открыть карточку CRM с полем bp_button_field — кнопка отображается  
7.2. Клик по кнопке — состояние loading, затем SidePanel открывается  
7.3. Ошибка API (например, неверный fieldId) — уведомление, кнопка возвращается в idle  
7.4. BUTTON_INACTIVE — кнопка переходит в disabled, уведомление  
7.5. Проверить parseWidth: 800 → 800, 80% → пиксели  
7.6. Проверить ensureContextUrl: параметры добавляются к URL  
7.7. Динамически добавленная кнопка (Entity Editor) — BX.MyBpButton.Button.bind вызывается из init script в BpButtonUserType

---

## Технические требования

- Vanilla JS, без фреймворков  
- Совместимость с BX.* API (Bitrix24)  
- Обратная совместимость: публичный API `BX.MyBpButton.Button` не меняется (init, bind вызываются как прежде)  
- Порядок загрузки модулей критичен: state → utils → api → sidepanel → main  
- Каждый модуль — IIFE с проверкой `typeof BX !== 'undefined'`

---

## Формат регистрации в Bitrix

CJSCore::RegisterExt поддерживает массив файлов в `js`:

```php
\CJSCore::RegisterExt('my_bpbutton.button', [
    'js' => [
        '/local/modules/my.bpbutton/install/js/my.bpbutton/button.state.js',
        '/local/modules/my.bpbutton/install/js/my.bpbutton/button.utils.js',
        '/local/modules/my.bpbutton/install/js/my.bpbutton/button.api.js',
        '/local/modules/my.bpbutton/install/js/my.bpbutton/button.sidepanel.js',
        '/local/modules/my.bpbutton/install/js/my.bpbutton/button.js',
    ],
    'rel' => ['main.core', 'ui.buttons', 'ui.sidepanel', 'ui.notification'],
    'lang' => '/local/modules/my.bpbutton/lang/' . LANGUAGE_ID . '/install/js/my.bpbutton/button.php',
]);
```

Файлы загружаются последовательно в указанном порядке.

---

## Критерии приёмки

- [ ] Созданы модули: button.state.js, button.utils.js, button.api.js, button.sidepanel.js
- [ ] button.js сокращён до ~120–150 строк
- [ ] Расширение зарегистрировано с массивом js-файлов
- [ ] Кнопка в карточке CRM работает: клик → loading → SidePanel
- [ ] Обработка ошибок: уведомление, возврат в idle
- [ ] BUTTON_INACTIVE: кнопка переходит в disabled
- [ ] Публичный API BX.MyBpButton.Button.init, .bind — без изменений
- [ ] Динамически добавленные кнопки инициализируются (init script в BpButtonUserType)
- [ ] Код соответствует стилю проекта (IIFE, 'use strict')

---

## Примеры кода

### button.state.js (сокращённо)

```javascript
;(function () {
    'use strict';
    if (typeof BX === 'undefined') return;

    BX.namespace('MyBpButton');
    var stateMap = (typeof WeakMap !== 'undefined') ? new WeakMap() : null;

    function getState(buttonEl) { /* ... */ }
    function setState(buttonEl, next) { /* ... */ }
    function notify(content) { /* ... */ }
    function message(key, fallback) { /* ... */ }

    BX.MyBpButton.ButtonState = {
        getState: getState,
        setState: setState,
        notify: notify,
        message: message,
        setIdle: function(buttonEl) { /* ... */ },
        setLoading: function(buttonEl) { /* ... */ },
        setDisabled: function(buttonEl) { /* ... */ },
        flashError: function(buttonEl) { /* ... */ }
    };
})();
```

### button.js — onClick (после рефакторинга)

```javascript
onClick: function(event, buttonEl) {
    if (event) { event.preventDefault(); event.stopPropagation(); }
    var State = BX.MyBpButton.ButtonState;
    var st = State.getState(buttonEl);
    if (st && (st.status === 'loading' || st.disabledByBusiness)) return;
    if (BX.hasClass(buttonEl, 'ui-btn-disabled')) return;

    var entityId = buttonEl.dataset ? (buttonEl.dataset.entityId || '') : '';
    var elementId = buttonEl.dataset ? parseInt(buttonEl.dataset.elementId || '0', 10) : 0;
    var fieldId = buttonEl.dataset ? parseInt(buttonEl.dataset.fieldId || '0', 10) : 0;
    var userId = buttonEl.dataset ? parseInt(buttonEl.dataset.userId || '0', 10) : 0;

    State.setLoading(buttonEl);
    var self = this;
    BX.MyBpButton.ButtonApi.fetchConfig(
        { entityId: entityId, elementId: elementId, fieldId: fieldId },
        function(response) { self.handleResponse(buttonEl, response, { entityId, elementId, fieldId, userId }); },
        function() {
            State.setIdle(buttonEl);
            State.notify(State.message('MY_BPBTN_ERROR_INTERNAL_ERROR', 'Произошла ошибка...'));
            self.logClick(buttonEl, { entityId, elementId, fieldId, userId }, 'INTERNAL_ERROR', 'AJAX failure');
        }
    );
}
```

---

## Тестирование

### Ручное тестирование

1. **Успешный сценарий:** Карточка лида/сделки → клик по кнопке → loading → SidePanel открывается с корректным URL и заголовком.  
2. **Ошибка API:** Неверный fieldId или сеть отключена → уведомление, кнопка в idle.  
3. **BUTTON_INACTIVE:** Кнопка с ACTIVE=N → уведомление, кнопка переходит в disabled.  
4. **Ширина:** Настройка 80% → SidePanel открывается с шириной в пикселях.  
5. **Контекст в URL:** В открытой странице SidePanel присутствуют ENTITY_ID, ELEMENT_ID, FIELD_ID, USER_ID.  
6. **Динамическая кнопка:** Создание поля в Entity Editor, появление кнопки — клик работает.

### Проверка регрессий

- entity-editor.js — патчи для bp_button_field работают  
- BpButtonUserType — init script вызывает BX.MyBpButton.Button.bind  
- Совместимость с разными браузерами (WeakMap fallback)

---

## Альтернатива: единый bundle

Если разделение на 5 файлов создаёт избыточное количество HTTP-запросов, можно использовать сборщик (webpack, rollup) для объединения в один `button.bundle.js`. В рамках данной задачи — только разделение на модули и регистрация через массив `js`. Бандлинг — отдельная задача (опционально).

---

## История правок

- 2026-03-16: Создан документ задачи TASK-REF-003 на основе REFACTOR-PLAN-001.
