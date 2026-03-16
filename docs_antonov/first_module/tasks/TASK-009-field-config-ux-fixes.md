# TASK-009: Исправление UX при выборе типа поля `bp_button_field` в форме настроек

**Дата создания:** 2026-03-16 (UTC+3, Брест)  
**Статус:** Завершена  
**Приоритет:** Высокий  
**Исполнитель:** Bitrix24 Программист (коробка, D7 + Vanilla JS)

## Описание

Задача описывает исправление ошибок, возникавших при выборе типа пользовательского поля `bp_button_field` в форме создания/редактирования поля (компонент `main.field.config.detail`):

1. **Ошибка «[object Object]»** — при сбое AJAX-запроса `getSettings` компонент выводил объект ошибки как строку.
2. **Ошибка «Network error»** — AJAX-запрос `getSettings` падал, т.к. `renderField` пытался использовать компонент без шаблона для режима `main.admin_settings`.

## Контекст

- Форма настроек поля вызывается при добавлении пользовательского поля в CRM (слайдер или отдельная страница).
- При смене типа поля в выпадающем списке выполняется AJAX `getSettings` для подгрузки HTML настроек типа.
- Компонент `main.field.config.detail` использует `BX.Main.UserField.Config`, метод `showErrors` ожидает массив строк, а Bitrix передаёт массив объектов `{message, code}`.
- `UserFieldManager::renderField` вызывает `BpButtonUserType::renderField` с режимом `main.admin_settings`; компонент `main.field.bp_button_field` не имеет шаблона для этого режима.

## Модули и компоненты

- `local/modules/my.bpbutton/install/js/my.bpbutton/entity-editor.js`  
  — патч `showErrors` для корректного отображения ошибок (объекты → строки), расширение области загрузки скрипта.
- `local/modules/my.bpbutton/lib/EventHandler.php`  
  — расширение условий загрузки `entity-editor` на страницы CRM, админки и настройки полей.
- `local/modules/my.bpbutton/lib/UserField/BpButtonUserType.php`  
  — обработка режима `main.admin_settings` в `renderField`: возврат `getSettingsHTML` напрямую, без компонента.

## Зависимости

- **От других задач:**
  - `TASK-001-user-type` — регистрация типа `bp_button_field`;
  - `TASK-005-js-frontend` — JS-расширение `entity-editor`.
- **От ядра Bitrix24:**
  - компонент `main.field.config.detail`;
  - `UserFieldManager::renderField`, `SettingsArea`, `Renderer`.

## Ступенчатые подзадачи

1. **Исправление отображения ошибок «[object Object]»** ✅  
   - Добавлен патч `patchConfigShowErrors` в `entity-editor.js`:
     - перехват `BX.Main.UserField.Config.prototype.showErrors`;
     - преобразование элементов массива: объекты `{message}` → строки, строки — без изменений;
     - polling (до 30 сек) для случая, когда `Config` подгружается позже (слайдер).
   - Патч применяется до вызова оригинального `showErrors`.

2. **Расширение области загрузки `entity-editor`** ✅  
   - В `EventHandler::onMainProlog` скрипт загружается не только на CRM, но и на:
     - страницах админки (`/bitrix/admin/`);
     - страницах настройки полей (`userfield`, `field.config`, `main.field.config`).
   - Патч `showErrors` активен на всех страницах, где может открываться форма настроек поля.

3. **Исправление «Network error» при выборе типа** ✅  
   - В `BpButtonUserType::renderField` добавлена проверка режима:
     - при `mode === 'main.admin_settings'` вызывается `getSettingsHTML` напрямую;
     - компонент `main.field.bp_button_field` не используется (нет шаблона для этого режима).
   - AJAX `getSettings` возвращает корректный HTML и завершается успешно.

4. **Устойчивость к отсутствию `BX.UI`** ✅  
   - Проверка `BX.UI` вынесена из общей инициализации: блок `EntityUserFieldManager` и патч `EntityEditorUserField` выполняются только при наличии `BX.UI`.
   - Патч `showErrors` работает при наличии только `BX` и `BX.Main.UserField.Config`.

## Технические детали

### Патч showErrors

```javascript
proto.showErrors = function (errors) {
    var list = [];
    if (BX.Type.isArray(errors)) {
        errors.forEach(function (item) {
            if (BX.Type.isString(item)) list.push(item);
            else if (item && typeof item === 'object' && item.message) list.push(String(item.message));
            else if (item != null) list.push(String(item));
        });
    }
    return original.call(this, list);
};
```

### Обработка main.admin_settings в renderField

```php
if ($mode === 'main.admin_settings') {
    $htmlControlName = $additionalParameters['NAME'] ?? 'settings';
    return static::getSettingsHTML($userField, $htmlControlName, $additionalParameters);
}
```

## Критерии приёмки

- [x] При выборе типа `bp_button_field` в форме настроек поля не возникает ошибка «[object Object]».
- [x] При выборе типа `bp_button_field` не возникает ошибка «Network error».
- [x] Отображается блок настроек: «После создания поля вы сможете настроить параметры кнопки в разделе настроек модуля».
- [x] Патч `showErrors` применяется на страницах CRM, админки и настройки полей.
- [x] Скрипт `entity-editor` не падает при отсутствии `BX.UI` (страницы только с формой настроек).

## Тестирование

- **Сценарий 1:** CRM → Настройки → Пользовательские поля → Добавить поле → выбрать тип «Кнопка бизнес‑процесса».
  - Ожидание: блок настроек отображается, ошибок нет.
- **Сценарий 2:** Симуляция сбоя AJAX (например, отключение сети перед выбором типа).
  - Ожидание: вместо «[object Object]» отображается текст ошибки (например, «Network error»).
- **Сценарий 3:** Открытие формы настроек из слайдера на странице CRM.
  - Ожидание: патч применяется, настройки загружаются корректно.

## История правок

- 2026-03-16: Создана задача по итогам исправлений в чате.
- 2026-03-16: Реализованы все подзадачи, критерии приёмки выполнены.
