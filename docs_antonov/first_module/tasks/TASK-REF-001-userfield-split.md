# TASK-REF-001: Разделение BpButtonUserType (UserField-слой)

**Дата создания:** 2026-03-16 (UTC+3, Брест)  
**Статус:** Новая  
**Приоритет:** Высокий  
**Исполнитель:** Bitrix24 Программист (коробка, D7)  
**Связь с планом:** REFACTOR-PLAN-001-five-stages.md, Этап 1

---

## Описание

Класс `BpButtonUserType` (~509 строк) выполняет слишком много обязанностей: описание пользовательского типа поля, генерация HTML кнопки, получение настроек из `SettingsTable` с кешированием, нормализация параметров из разных источников (Entity Editor, компонент, админка), формирование `data-*` атрибутов, подключение JS-расширений и inline-скрипта инициализации.

**Цель задачи:** Выделить ответственности в отдельные классы, оставив в `BpButtonUserType` только описание типа и делегирование вызовов. Результат — тонкая обёртка (~150–200 строк), остальная логика — в `ButtonHtmlRenderer` и `SettingsResolver`.

---

## Контекст

### Текущая структура BpButtonUserType

| Блок кода | Строки (прибл.) | Ответственность |
|-----------|-----------------|-----------------|
| getUserTypeDescription, getDBColumnType | 1–66 | Описание типа (остаётся) |
| getAdminListViewHTML, getEditFormHTML, getViewHTML | 69–121 | Делегирование в getPublicViewHTML (остаётся) |
| getButtonTextForField | 131–165 | Получение BUTTON_TEXT из SettingsTable + кеш |
| getButtonSizeForField | 173–198 | Получение BUTTON_SIZE из SettingsTable + кеш |
| getPublicViewHTML | 209–358 | Нормализация, Extensions, контекст, HTML, init script |
| getPublicEditHTML, getPublicTextHTML, Multy | 365–387 | Делегирование (остаётся) |
| getSettingsHTML | 399–423 | Ссылка на настройки (остаётся) |
| prepareSettings | 335–344 | Подготовка настроек (остаётся) |
| renderField, renderView, renderEdit | 355–406 | Рендеринг через компонент (остаётся) |

### Точки вызова getPublicViewHTML

- `BpButtonUserType::getPublicViewHTML` — напрямую из Bitrix (VIEW_CALLBACK, GetPublicViewHTML)
- `bitrix/components/bitrix/system.field.view/templates/bp_button_field/template.php` — компонент ядра
- `local/components/my/main.field.bp_button_field/templates/main.view/.default.php` — кастомный компонент

**Важно:** После рефакторинга публичный контракт `BpButtonUserType::getPublicViewHTML(array $field, ?array $value, array $additional): string` не меняется. Внутри — делегирование в `ButtonHtmlRenderer`.

---

## Модули и компоненты

### Новые файлы

| Путь | Назначение |
|------|------------|
| `local/modules/my.bpbutton/lib/UserField/ButtonHtmlRenderer.php` | Класс генерации HTML кнопки. Методы: `render()`, `normalizeParameters()`, `resolveContext()`, `buildButtonHtml()`, `buildInitScript()`. |
| `local/modules/my.bpbutton/lib/Service/SettingsResolver.php` | Класс получения настроек отображения (BUTTON_TEXT, BUTTON_SIZE) по FIELD_ID с кешированием в рамках запроса. |
| `local/modules/my.bpbutton/lang/ru/lib/UserField/buttonhtmlrenderer.php` | Языковые сообщения для рендерера (если появятся). |
| `local/modules/my.bpbutton/lang/ru/lib/Service/settingsresolver.php` | Языковые сообщения для SettingsResolver (fallback-тексты). |

### Изменяемые файлы

| Путь | Изменения |
|------|-----------|
| `local/modules/my.bpbutton/lib/UserField/BpButtonUserType.php` | Удалить `getButtonTextForField`, `getButtonSizeForField`, логику из `getPublicViewHTML`. Добавить вызов `ButtonHtmlRenderer::render()`. Сократить до ~150–200 строк. |
| `local/modules/my.bpbutton/include.php` | Без изменений (подключение JS остаётся в модуле). |

### Зависимые компоненты (без изменений)

- `local/components/my/main.field.bp_button_field/` — вызывает `BpButtonUserType::getPublicViewHTML`
- `bitrix/components/bitrix/system.field.view/templates/bp_button_field/` — вызывает `BpButtonUserType::getPublicViewHTML`

---

## Зависимости

### От каких модулей/задач зависит

- Модуль `my.bpbutton` установлен
- `SettingsTable` с полями `BUTTON_TEXT`, `BUTTON_SIZE`
- `EventHandler` — регистрация user type (без изменений)

### Какие задачи зависят от этой

- TASK-REF-004 (Service layer) — `SettingsResolver` может быть объединён с `ButtonService::getDisplaySettings` в будущем
- TASK-REF-005 (Structure & docs) — обновление схемы модуля

---

## Детальная спецификация новых классов

### 1. SettingsResolver

**Namespace:** `My\BpButton\Service`  
**Файл:** `lib/Service/SettingsResolver.php`

**Назначение:** Получение настроек отображения кнопки (BUTTON_TEXT, BUTTON_SIZE) по `FIELD_ID` с кешированием в рамках одного HTTP-запроса.

**Методы:**

```php
/**
 * Получить текст кнопки для поля.
 * @param int $fieldId ID пользовательского поля
 * @return string Текст кнопки (из настроек или fallback)
 */
public function getButtonText(int $fieldId): string

/**
 * Получить размер кнопки для поля.
 * @param int $fieldId ID пользовательского поля
 * @return string 'default' | 'sm' | 'lg'
 */
public function getButtonSize(int $fieldId): string

/**
 * Получить все настройки отображения за один запрос (оптимизация).
 * @param int $fieldId ID пользовательского поля
 * @return array{buttonText: string, buttonSize: string}
 */
public function getDisplaySettings(int $fieldId): array
```

**Кеширование:** Статический массив `$cache` по ключу `$fieldId`. При первом обращении — запрос в `SettingsTable`, при повторном — из кеша.

**Fallback для BUTTON_TEXT:** `Loc::getMessage('BPBUTTON_USER_TYPE_BUTTON_TEXT')` → `Loc::getMessage('BPBUTTON_USER_TYPE_NAME')` → `'Кнопка'`.

**Fallback для BUTTON_SIZE:** `'default'` при отсутствии или невалидном значении. Допустимые: `'sm'`, `'lg'`.

**Обработка ошибок:** При исключении из `SettingsTable::getList` — возврат fallback-значений, без проброса исключения.

---

### 2. ButtonHtmlRenderer

**Namespace:** `My\BpButton\UserField`  
**Файл:** `lib/UserField/ButtonHtmlRenderer.php`

**Назначение:** Генерация HTML кнопки для отображения в карточке CRM, админ-списке, форме редактирования.

**Методы:**

```php
/**
 * Отрисовать кнопку.
 * @param array $field Данные поля (ENTITY_ID, ID, VALUE и т.д.)
 * @param array|null $value Значение поля (может быть null)
 * @param array $additional Доп. параметры (ELEMENT_ID, ENTITY_ID, USER и т.д.)
 * @return string HTML кнопки с обёрткой и init script
 */
public function render(array $field, ?array $value, array $additional = []): string

/**
 * Нормализовать $value и $additional (защита от разных форматов Bitrix).
 * @return array{value: array, additional: array}
 */
protected function normalizeParameters(array $field, $value, array $additional): array

/**
 * Извлечь контекст для data-атрибутов: entityId, elementId, fieldId, userId.
 * @return array{entityId: string, elementId: string, fieldId: string, userId: string}
 */
protected function resolveContext(array $field, array $additional): array

/**
 * Собрать HTML кнопки (button + wrapper).
 */
protected function buildButtonHtml(
    string $buttonId,
    string $buttonText,
    string $sizeStyle,
    array $context
): string

/**
 * Собрать inline script для инициализации BX.MyBpButton.Button.bind().
 */
protected function buildInitScript(string $buttonId): string
```

**Зависимости:** `SettingsResolver` (через конструктор или статический вызов), `Extension`, `Loc`, `UserTable` (для userId fallback).

**Логика resolveContext (elementId):** Приоритет источников:
1. `$additional['ELEMENT_ID']` (в `getAdminListViewHTML` подставляется из `$row['ID']` до вызова)
2. `$additional['VALUE']`
3. `$_REQUEST['ID']`
4. Извлечение из URL через `$GLOBALS['APPLICATION']->GetCurPageParam()` и `preg_match('/[?&]ID=(\d+)/')`

*Примечание:* В текущем коде была ссылка на `$row['ID']`, но `$row` не передаётся в `getPublicViewHTML`. В `getAdminListViewHTML` значение `$row['ID']` заранее подставляется в `$additional['ELEMENT_ID']`, поэтому отдельная обработка `$row` не требуется.

**Логика resolveContext (userId):** Приоритет:
1. `$additional['USER']['ID']`
2. `$GLOBALS['USER']->GetID()`
3. Запрос в `UserTable::getList` (limit 1) — fallback

**Подключение расширений:** В `render()` перед генерацией HTML:
- `Extension::load('ui.buttons')`
- `Extension::load('my_bpbutton.button')`

**Формат HTML:** Без изменений относительно текущего:
- Обёртка: `<div class="bpbutton-field-wrapper" data-field-type="bp_button_field">`
- Кнопка: `type="button"`, `class="ui-btn ui-btn-primary js-bpbutton-field"`, `data-editor-control-type="button"`, `data-entity-id`, `data-element-id`, `data-field-id`, `data-user-id`
- Inline-стили для размера: `sm` → `padding: 4px 12px; font-size: 12px;`, `lg` → `padding: 12px 28px; font-size: 16px;`

---

### 3. BpButtonUserType (после рефакторинга)

**Остаётся без изменений:**
- `getUserTypeDescription()`
- `getDBColumnType()`
- `getAdminListViewHTML()` — делегирует в `getPublicViewHTML`
- `getEditFormHTML()`, `getViewHTML()` — делегируют в `getPublicViewHTML`
- `getPublicEditHTML()`, `getPublicTextHTML()`, `getPublicViewHTMLMulty()`, `getPublicEditHTMLMulty()`
- `getSettingsHTML()`, `prepareSettings()`
- `renderField()`, `renderView()`, `renderEdit()`

**Изменяется:**
- `getPublicViewHTML()` — вызывает `ButtonHtmlRenderer::render()`:

```php
public static function getPublicViewHTML(array $field, ?array $value = null, array $additional = []): string
{
    $renderer = new ButtonHtmlRenderer(new SettingsResolver());
    return $renderer->render($field, $value, $additional ?? []);
}
```

Либо использовать статический фасад/DI через сервис-локатор, если в проекте принят такой подход. Для простоты — создание экземпляра напрямую.

**Удаляется:**
- `getButtonTextForField()`
- `getButtonSizeForField()`
- Вся логика внутри `getPublicViewHTML` (нормализация, контекст, HTML, init script)

---

## Ступенчатые подзадачи

### Подзадача 1: Создать SettingsResolver

1.1. Создать файл `lib/Service/SettingsResolver.php`  
1.2. Реализовать `getButtonText(int $fieldId): string` с кешированием  
1.3. Реализовать `getButtonSize(int $fieldId): string` с кешированием  
1.4. Реализовать `getDisplaySettings(int $fieldId): array` — один запрос в SettingsTable для обоих полей  
1.5. Добавить fallback-логику и обработку исключений  
1.6. Создать `lang/ru/lib/Service/settingsresolver.php` (если нужны свои сообщения; можно использовать `bpbuttonusertype.php`)  
1.7. Написать юнит-тест (опционально, если в проекте есть тесты)

### Подзадача 2: Создать ButtonHtmlRenderer

2.1. Создать файл `lib/UserField/ButtonHtmlRenderer.php`  
2.2. Добавить зависимость от `SettingsResolver` (через конструктор)  
2.3. Реализовать `normalizeParameters()` — перенести логику из текущего `getPublicViewHTML` (строки 211–226)  
2.4. Реализовать `resolveContext()` — извлечение entityId, elementId, fieldId, userId (строки 254–296)  
2.5. Реализовать `buildButtonHtml()` — формирование атрибутов, HTML (строки 299–356)  
2.6. Реализовать `buildInitScript()` — inline script для BX.MyBpButton.Button.bind (строки 319–345)  
2.7. Реализовать `render()` — оркестрация: normalizeParameters → Extension::load → SettingsResolver → buildButtonHtml → buildInitScript  
2.8. Убедиться, что `$row` в `resolveContext` обрабатывается (в `getAdminListViewHTML` передаётся `$row` в `$additional` — проверить, как Bitrix передаёт данные)

### Подзадача 3: Рефакторинг BpButtonUserType

3.1. Удалить методы `getButtonTextForField` и `getButtonSizeForField`  
3.2. Заменить тело `getPublicViewHTML` на вызов `ButtonHtmlRenderer::render()`  
3.3. Добавить `use My\BpButton\Service\SettingsResolver;` и `use My\BpButton\UserField\ButtonHtmlRenderer;`  
3.4. Проверить, что `getAdminListViewHTML` передаёт `ELEMENT_ID` в `$additional` — при вызове `getPublicViewHTML($field, $value, $additional)` в `$additional` уже есть `ELEMENT_ID` из `$row['ID']` (строка 84)  
3.5. Убедиться, что `$row` не передаётся в `$additional` в текущей реализации — в `getAdminListViewHTML` только `ELEMENT_ID`. В `resolveContext` в старом коде была ссылка на `$row['ID']`, но `$row` не входит в `$additional`. Нужно проверить: в `getPublicViewHTML` параметр `$additional` не содержит `row`. В `resolveContext` следует использовать только `$additional`. Текущий код имел баг: `$row` не определён в scope `getPublicViewHTML`. Исправить в `resolveContext`: убрать `$row`, оставить только `$additional` и `$_REQUEST`.

### Подзадача 4: Проверка точек вызова

4.1. Компонент `main.field.bp_button_field` — вызывает `BpButtonUserType::getPublicViewHTML` — без изменений  
4.2. Шаблон `system.field.view` (bp_button_field) — вызывает `BpButtonUserType::getPublicViewHTML` — без изменений  
4.3. EventHandler, include.php — без изменений

### Подзадача 5: Тестирование

5.1. Открыть карточку CRM (лид/сделка) с полем `bp_button_field` — кнопка отображается  
5.2. Проверить текст кнопки (из настроек и fallback)  
5.3. Проверить размер кнопки (sm, lg, default)  
5.4. Клик по кнопке — открывается SidePanel  
5.5. Админ-список сущности с полем — кнопка отображается, ELEMENT_ID корректный  
5.6. Форма настроек поля (GetSettingsHTML) — ссылка «Настроить кнопку» работает  
5.7. Проверить, что `data-editor-control-type="button"` присутствует (TASK-012)

---

## Технические требования

- PHP 8.x, strict_types  
- PSR-12, D7-подход  
- Без изменения публичного API `BpButtonUserType` — обратная совместимость  
- Все тексты — через `Loc::getMessage`  
- `SettingsResolver` — stateless, кеш только в рамках запроса (static)  
- `ButtonHtmlRenderer` — можно инстанцировать без глобального состояния; при необходимости передавать `SettingsResolver` через конструктор

---

## Контракт data-атрибутов (для JS)

После рефакторинга контракт не меняется:

| Атрибут | Описание |
|---------|----------|
| `data-entity-id` | Тип сущности CRM (LEAD, DEAL, CONTACT и т.д.) |
| `data-element-id` | ID элемента (лида, сделки и т.д.) |
| `data-field-id` | ID пользовательского поля |
| `data-user-id` | ID текущего пользователя |
| `data-editor-control-type="button"` | Маркер для Entity Editor (не сохранять карточку при клике) |
| `class="js-bpbutton-field"` | Селектор для инициализации BX.MyBpButton.Button.bind() |

---

## Критерии приёмки

- [ ] Создан класс `SettingsResolver` с методами `getButtonText`, `getButtonSize`, `getDisplaySettings`
- [ ] Создан класс `ButtonHtmlRenderer` с методом `render` и вспомогательными методами
- [ ] `BpButtonUserType` сокращён до ~150–200 строк
- [ ] Кнопка в карточке CRM отображается корректно (текст, размер, клик → SidePanel)
- [ ] Кнопка в админ-списке отображается корректно
- [ ] Атрибут `data-editor-control-type="button"` присутствует
- [ ] Нет регрессий: форма настроек поля, компоненты, Entity Editor работают как прежде
- [ ] Код соответствует PSR-12, без дублирования логики

---

## Примеры кода

### SettingsResolver (сокращённо)

```php
<?php

declare(strict_types=1);

namespace My\BpButton\Service;

use Bitrix\Main\Localization\Loc;
use My\BpButton\Internals\SettingsTable;

final class SettingsResolver
{
    private static array $cache = [];

    public function getButtonText(int $fieldId): string
    {
        $settings = $this->getDisplaySettings($fieldId);
        return $settings['buttonText'] ?: (Loc::getMessage('BPBUTTON_USER_TYPE_BUTTON_TEXT') ?: 'Кнопка');
    }

    public function getButtonSize(int $fieldId): string
    {
        $settings = $this->getDisplaySettings($fieldId);
        return $settings['buttonSize'];
    }

    public function getDisplaySettings(int $fieldId): array
    {
        if ($fieldId <= 0) {
            return ['buttonText' => '', 'buttonSize' => 'default'];
        }
        if (!isset(self::$cache[$fieldId])) {
            // ... getList, fill cache
        }
        return self::$cache[$fieldId];
    }
}
```

### BpButtonUserType::getPublicViewHTML (после)

```php
public static function getPublicViewHTML(array $field, ?array $value = null, array $additional = []): string
{
    $renderer = new ButtonHtmlRenderer(new SettingsResolver());
    return $renderer->render($field, $value ?? [], $additional ?? []);
}
```

---

## Тестирование

### Ручное тестирование

1. **Карточка CRM (view):** Лид/сделка с полем bp_button_field → кнопка видна, текст из настроек, размер применяется, клик → SidePanel.  
2. **Карточка CRM (edit):** Режим редактирования → кнопка видна, клик не сохраняет карточку (data-editor-control-type).  
3. **Админ-список:** Список лидов/сделок с полем → кнопка в колонке, ELEMENT_ID корректный.  
4. **Fallback:** Поле без записи в SettingsTable → текст «Кнопка» (или из lang), размер default.  
5. **Компонент:** Страница с `main.field.bp_button_field` — рендеринг через компонент работает.

### Проверка регрессий

- Установка/удаление модуля — без ошибок  
- Создание/удаление поля bp_button_field — настройки создаются/удаляются  
- TASK-011 (размер кнопки), TASK-012 (клик без сохранения) — поведение сохранено

---

## История правок

- 2026-03-16: Создан документ задачи TASK-REF-001 на основе REFACTOR-PLAN-001.
