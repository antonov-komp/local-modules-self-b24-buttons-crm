# ANALYSIS-011: Анализ проблемы «Сохранить» при клике по кнопке bp_button_field

**Дата создания:** 2026-03-16 (UTC+3, Брест)  
**Роль:** Технический писатель  
**Статус:** Завершён

## Описание проблемы

Пользователь сообщает:
- Кнопка создаётся и модерируется корректно
- Кнопка выводится в интерфейсе карточки CRM
- **При клике по кнопке карточка пытается что-то сохранить**
- Ожидание: при клике должна только отрабатываться логика кнопки (открытие SidePanel), без сохранения карточки

## Контекст

Поле `bp_button_field` отображается внутри Entity Editor Bitrix24 (карточка CRM, смарт-процесс). Entity Editor имеет собственные механизмы обработки кликов по полям для переключения режимов просмотра/редактирования.

## Анализ кода

### 1. Текущая реализация кнопки

**Файл:** `local/modules/my.bpbutton/lib/UserField/BpButtonUserType.php`

Кнопка рендерится с атрибутами:
```php
$attributes = [
    'type="button"',
    'id="' . htmlspecialcharsbx($buttonId) . '"',
    'class="ui-btn ui-btn-primary js-bpbutton-field"',
    'data-entity-id="..."',
    'data-element-id="..."',
    'data-field-id="..."',
    'data-user-id="..."',
];
```

**Файл:** `local/modules/my.bpbutton/install/js/my.bpbutton/button.js`

Обработчик клика:
```javascript
onClick: function (event, buttonEl) {
    if (event) {
        event.preventDefault();
        event.stopPropagation();
    }
    // ... логика открытия SidePanel
}
```

Кнопка имеет `type="button"` (не submit) и в обработчике вызываются `preventDefault()` и `stopPropagation()`.

### 2. Механизм Entity Editor

**Файл:** `bitrix/modules/ui/install/js/ui/entity-editor/js/editor-controller.js`

#### EditorFieldViewController (режим просмотра)

При клике по полю в режиме просмотра срабатывает цепочка:
1. `onMouseDown` / `onMouseUp` на обёртке поля (`_wrapper`)
2. `isHandleableEvent(e)` — проверка, нужно ли обрабатывать клик
3. Если `true` → `switchTo()` → `switchToSingleEditMode()` → переключение поля в режим редактирования

**Ключевой метод `isHandleableEvent`:**
```javascript
isHandleableEvent: function(e) {
    const node = BX.getEventTarget(e);
    if (node.tagName === 'A') return false;

    const isButton = (control) => {
        return typeof control?.getAttribute === 'function'
            && control.getAttribute('data-editor-control-type') === 'button';
    };

    if (isButton(node) || BX.findParent(node, isButton, this._wrapper))
        return false;

    return !BX.findParent(node, { tagName: 'a' }, this._wrapper);
}
```

**Вывод:** Entity Editor **игнорирует** клики только для:
- элементов `<a>`
- элементов с атрибутом `data-editor-control-type="button"`

Наша кнопка **не имеет** `data-editor-control-type="button"`, поэтому клик по ней считается «обрабатываемым», и Entity Editor вызывает `switchToSingleEditMode()`.

#### EditorFieldSingleEditController (режим редактирования)

При клике вне поля вызывается `onDocumentClick` → `saveControl()` → `switchControlMode(..., view)` — переключение обратно в режим просмотра с сохранением.

### 3. Цепочка событий при клике по кнопке

1. Пользователь кликает по кнопке `bp_button_field`
2. Событие `mouseup` всплывает до `_wrapper` (обёртка поля Entity Editor)
3. `EditorFieldViewController.onMouseUp` срабатывает
4. `isHandleableEvent(e)` возвращает `true` (кнопка не в списке исключений)
5. Вызывается `switchTo()` → `switchToSingleEditMode()`
6. Поле переключается в режим редактирования
7. Entity Editor может инициировать сохранение/валидацию карточки

При этом наш `button.js` с `stopPropagation()` срабатывает на событии `click`, но `EditorFieldViewController` слушает `mousedown` и `mouseup` — порядок и фаза событий могут приводить к тому, что обработчик Entity Editor срабатывает до нашего или независимо.

## Корневая причина

Кнопка `bp_button_field` не помечена атрибутом `data-editor-control-type="button"`, который Entity Editor использует для исключения элементов из обработки кликов (переключение режима просмотра/редактирования).

## Варианты решения

### Вариант A: Добавить `data-editor-control-type="button"` (рекомендуется)

Добавить в HTML кнопки атрибут, который Bitrix24 уже использует для распознавания «действующих» кнопок:

```php
'data-editor-control-type="button"',
```

**Плюсы:**
- Минимальное изменение
- Соответствует стандарту Bitrix24
- Не требует патчинга ядра

**Минусы:** Нет

### Вариант B: Патч EditorFieldViewController в entity-editor.js

Расширить `isHandleableEvent` для игнорирования элементов с классом `js-bpbutton-field`:

```javascript
if (node.classList && node.classList.contains('js-bpbutton-field'))
    return false;
if (BX.findParent(node, '.js-bpbutton-field', this._wrapper))
    return false;
```

**Плюсы:** Работает без изменения PHP  
**Минусы:** Патч ядра Bitrix, может сломаться при обновлении

### Вариант C: Использовать `pointer-events` и обёртку

Обернуть кнопку в контейнер с `pointer-events: none` на обёртке и `pointer-events: auto` на кнопке — сложно и может сломать UX.

## Рекомендация

**Вариант A** — добавить `data-editor-control-type="button"` в `BpButtonUserType::getPublicViewHTML`.

## Зависимости

- `BpButtonUserType.php` — метод `getPublicViewHTML`
- Entity Editor (`ui.entity-editor`) — `EditorFieldViewController`

## Связанные задачи

- TASK-005-js-frontend.md — JS-слой кнопки
- TASK-011-button-size-and-width-clarification.md — размер кнопки

## История правок

- 2026-03-16: Создан анализ, выявлена причина, рекомендован вариант A.
