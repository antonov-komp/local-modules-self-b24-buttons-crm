# TASK-REF-002: Разделение админки (admin-слой)

**Дата создания:** 2026-03-16 (UTC+3, Брест)  
**Статус:** Новая  
**Приоритет:** Высокий  
**Исполнитель:** Bitrix24 Программист (коробка, D7 + Vanilla JS)  
**Связь с планом:** REFACTOR-PLAN-001-five-stages.md, Этап 2

---

## Описание

Файл `admin/bpbutton_list.php` (~733 строки) объединяет в одном месте: роутинг (edit vs list), форму редактирования настроек кнопки, обработку POST (обычный и AJAX), валидацию полей, отображение списка (CAdminList), фильтры, групповые действия, авто-синхронизацию настроек с полями, пагинацию.

**Цель задачи:** Разделить ответственности на отдельные файлы и классы. Результат — модульная админка, каждый файл < 300 строк, логика вынесена в сервисы и контроллер.

---

## Контекст

### Текущая структура bpbutton_list.php

| Блок | Строки (прибл.) | Ответственность |
|------|-----------------|-----------------|
| Prolog, проверка модуля, прав | 1–51 | Инициализация (остаётся в обоих файлах) |
| Роутинг: ID, FIELD_ID, action | 38–51 | Определение режима edit/list |
| Edit: AJAX POST (до HTML) | 56–114 | Сохранение через AJAX, JSON-ответ |
| Edit: prolog_after, проверки | 116–145 | Подключение JS, проверка прав, загрузка записи |
| Edit: обычный POST | 147–231 | Валидация, сохранение, редирект/уведомление |
| Edit: форма HTML | 233–341 | CAdminTabControl, поля формы |
| List: авто-синхронизация | 349–394 | Создание записей SettingsTable для полей без настроек |
| List: CAdminList, фильтр, сортировка | 396–424 | Инициализация грида |
| List: групповые действия | 354–377 | activate, deactivate, delete |
| List: выборка данных | 376–398 | getList с пагинацией |
| List: вывод строк | 430–462 | Отрисовка таблицы, действия |
| List: фильтр, футер, навигация | 464–531 | CAdminFilter, пагинация |

### Текущие точки входа

- `install/admin/my_bpbutton_bpbutton_list.php` — подключает `admin/bpbutton_list.php`
- `admin/bpbutton_list_ajax.php` — отдельный файл для AJAX `toggle_active` (inline-переключатель активности в списке)

### URL-схема

- Список: `my_bpbutton_bpbutton_list.php?lang=ru`
- Редактирование: `my_bpbutton_bpbutton_list.php?lang=ru&action=edit&ID=1` или `&FIELD_ID=123`
- SidePanel открывает форму с `IFRAME=Y&IFRAME_TYPE=SIDE_SLIDER`

---

## Модули и компоненты

### Новые файлы

| Путь | Назначение |
|------|------------|
| `local/modules/my.bpbutton/admin/bpbutton_edit.php` | Страница формы редактирования настроек кнопки. Обработка POST (обычный и AJAX), вывод формы. |
| `local/modules/my.bpbutton/install/admin/my_bpbutton_bpbutton_edit.php` | Точка входа для формы редактирования (аналог my_bpbutton_bpbutton_list.php). |
| `local/modules/my.bpbutton/lib/Service/SettingsFormService.php` | Валидация полей формы, сохранение в SettingsTable, подготовка данных для отображения. |
| — | AdminController не создаётся; AJAX `toggle_active` остаётся в `bpbutton_list_ajax.php`, логика — в `SettingsFormService::toggleActive` |
| `local/modules/my.bpbutton/lang/ru/lib/Service/settingsformservice.php` | Опционально. Можно использовать `Loc::loadMessages` из `admin/bpbutton_list.php` — сообщения валидации уже там. |

### Изменяемые файлы

| Путь | Изменения |
|------|-----------|
| `local/modules/my.bpbutton/admin/bpbutton_list.php` | Удалить блок edit (строки 56–341). Оставить только список. При `action=edit` — редирект на `bpbutton_edit.php`. |
| `local/modules/my.bpbutton/admin/menu.php` | Без изменений (меню ведёт на список). |
| `local/modules/my.bpbutton/include.php` | При необходимости — регистрация маршрутов для AdminController (если используется Bitrix routing). |

### Файлы без изменений (кроме рефакторинга)

| Путь | Изменения |
|------|-----------|
| `local/modules/my.bpbutton/admin/bpbutton_list_ajax.php` | Вызов `SettingsFormService::toggleActive()` вместо прямого `SettingsTable::update`. Файл остаётся как endpoint для admin.list.js. |

### Зависимые компоненты (без изменений)

- `install/js/my.bpbutton/admin.list.js` — вызывает `bpbutton_list_ajax.php` для toggle_active. После рефакторинга — вызов AdminController (URL изменится).
- Языковые файлы `lang/ru/admin/bpbutton_list.php` — используются и списком, и формой. Форма может использовать `bpbutton_edit.php` в Loc::loadMessages.

---

## Зависимости

### От каких модулей/задач зависит

- Модуль `my.bpbutton` установлен
- `SettingsTable`, `UserFieldTable`
- `BpButtonUserType::USER_TYPE_ID` для авто-синхронизации

### Какие задачи зависят от этой

- TASK-REF-004 (Service layer) — `SettingsFormService` может быть расширен
- TASK-REF-005 (Structure & docs) — обновление схемы админки

---

## Детальная спецификация новых классов и файлов

### 1. SettingsFormService

**Namespace:** `My\BpButton\Service`  
**Файл:** `lib/Service/SettingsFormService.php`

**Назначение:** Валидация данных формы настроек кнопки, сохранение в `SettingsTable`, подготовка нормализованных данных.

**Методы:**

```php
/**
 * Валидация данных формы.
 * @param array $data Данные из POST: HANDLER_URL, TITLE, WIDTH, BUTTON_TEXT, BUTTON_SIZE, ACTIVE
 * @return array{valid: bool, errors: string[], normalized: array}
 */
public function validate(array $data): array

/**
 * Сохранение настроек в SettingsTable.
 * @param int $id ID записи в my_bpbutton_settings
 * @param array $data Нормализованные данные (результат validate)
 * @return \Bitrix\Main\ORM\Data\UpdateResult
 */
public function save(int $id, array $data): \Bitrix\Main\ORM\Data\UpdateResult

/**
 * Получить запись настроек по ID с проверкой существования.
 * @param int $id
 * @return array|null
 */
public function getById(int $id): ?array

/**
 * Получить ID записи по FIELD_ID (для редиректа с FIELD_ID).
 * @param int $fieldId
 * @return int|null
 */
public function getIdByFieldId(int $fieldId): ?int
```

**Правила валидации (из текущего кода):**

| Поле | Правило | Сообщение об ошибке |
|------|---------|---------------------|
| HANDLER_URL | Если не пусто: `https?://` или начинается с `/` | MY_BPBUTTON_EDIT_ERROR_INVALID_URL |
| WIDTH | Если не пусто: `^\d+$` или `^\d+%$` | MY_BPBUTTON_EDIT_ERROR_INVALID_WIDTH |
| BUTTON_SIZE | Если не пусто: `default`, `sm`, `lg` | При невалидном — нормализовать в `default` |

**Нормализация:** Пустые строки преобразуются в `null` для сохранения в БД. `ACTIVE` — `'Y'` или `'N'`.

---

### 2. bpbutton_edit.php

**Файл:** `admin/bpbutton_edit.php`

**Назначение:** Страница формы редактирования настроек кнопки.

**Логика:**

1. **Prolog:** `prolog_admin_before.php`, проверка модуля, прав (W для редактирования).
2. **Роутинг ID:** Получить `ID` или `FIELD_ID` из GET. Если `FIELD_ID` — найти запись в SettingsTable, взять `ID`.
3. **Обработка POST (до вывода HTML):**
   - Если `isPost && (save || apply)`:
     - Вызвать `SettingsFormService::validate()`.
     - Если valid — `SettingsFormService::save()`.
     - Если AJAX (IFRAME=Y, IFRAME_TYPE=SIDE_SLIDER): вернуть JSON `{status, formParams}` или `{status, message}`.
     - Если обычный POST: редирект или вывод сообщения, затем форма.
4. **Загрузка данных:** `SettingsFormService::getById($id)`.
5. **Форма:** CAdminTabControl, поля (FIELD_INFO, ENTITY_ID, HANDLER_URL, TITLE, BUTTON_TEXT, WIDTH, BUTTON_SIZE, ACTIVE).
6. **Epilog:** `epilog_admin.php`.

**Подключение JS:** Extension::load('ui.sidepanel'), Extension::load('ui.notification').

**Ссылка «Назад»:** `my_bpbutton_bpbutton_list.php?lang=...`

---

### 3. bpbutton_list.php (после рефакторинга)

**Логика:**

1. **Prolog:** как сейчас.
2. **Роутинг:** Если `action=edit` и `id>0` (или найден по FIELD_ID):
   - `LocalRedirect('my_bpbutton_bpbutton_edit.php?lang='.LANGUAGE_ID.'&ID='.$id)`.
   - `return`.
3. **Список:** авто-синхронизация, CAdminList, фильтр, групповые действия, вывод строк, пагинация — без изменений.

**Удалить:** Весь блок edit (строки 56–341).

---

### 4. Точка входа для формы

**Файл:** `install/admin/my_bpbutton_bpbutton_edit.php`

```php
<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/local/modules/my.bpbutton/admin/bpbutton_edit.php');
```

**Регистрация в меню:** Не требуется — форма открывается по ссылке из списка (SidePanel или обычная ссылка).

**Обновление ссылок в списке:** В `bpbutton_list.php` заменить URL редактирования с `my_bpbutton_bpbutton_list.php?action=edit&ID=X` на `my_bpbutton_bpbutton_edit.php?ID=X`.

---

## Ступенчатые подзадачи

### Подзадача 1: Создать SettingsFormService

1.1. Создать `lib/Service/SettingsFormService.php`  
1.2. Реализовать `validate(array $data): array` — правила HANDLER_URL, WIDTH, BUTTON_SIZE  
1.3. Реализовать `save(int $id, array $data): UpdateResult`  
1.4. Реализовать `getById(int $id): ?array`  
1.5. Реализовать `getIdByFieldId(int $fieldId): ?int`  
1.6. Добавить `toggleActive(int $id, string $active): UpdateResult` — для использования в bpbutton_list_ajax.php  
1.7. Создать `lang/ru/lib/Service/settingsformservice.php` (или использовать сообщения из bpbutton_list.php)

### Подзадача 2: Создать bpbutton_edit.php

2.1. Создать `admin/bpbutton_edit.php`  
2.2. Перенести prolog, проверку модуля и прав  
2.3. Реализовать роутинг ID/FIELD_ID  
2.4. Реализовать обработку POST (AJAX и обычный) через SettingsFormService  
2.5. Перенести HTML формы (CAdminTabControl, поля) из bpbutton_list.php  
2.6. Подключить Extensions (ui.sidepanel, ui.notification)  
2.7. Обновить action формы: `my_bpbutton_bpbutton_edit.php`  
2.8. Обновить back_url в CAdminTabControl

### Подзадача 3: Создать точку входа и обновить ссылки

3.1. Создать `install/admin/my_bpbutton_bpbutton_edit.php`  
3.2. В `bpbutton_list.php` заменить блок edit на редирект:
   ```php
   if ($action === 'edit' && $id > 0) {
       LocalRedirect('my_bpbutton_bpbutton_edit.php?lang=' . LANGUAGE_ID . '&ID=' . $id);
       return;
   }
   ```
   (URL относительный, в контексте админки Bitrix)
3.3. Обновить все ссылки на редактирование в списке: `my_bpbutton_bpbutton_edit.php?ID=...`  
3.4. Обновить ссылку в `BpButtonUserType::getSettingsHTML`: заменить `my_bpbutton_bpbutton_list.php?FIELD_ID=X&action=edit` на `my_bpbutton_bpbutton_edit.php?FIELD_ID=X` — форма принимает FIELD_ID напрямую

### Подзадача 4: Рефакторинг bpbutton_list.php

4.1. Удалить блок edit (строки 56–341)  
4.2. Оставить только логику списка  
4.3. Добавить редирект при action=edit (см. 3.2)  
4.4. Проверить, что авто-синхронизация, фильтры, групповые действия работают

### Подзадача 5: Рефакторинг bpbutton_list_ajax.php

5.1. Заменить прямые вызовы SettingsTable::update на SettingsFormService::toggleActive  
5.2. Оставить структуру ответа JSON без изменений (для совместимости с admin.list.js)  
5.3. Проверить, что inline-переключатель активности работает

### Подзадача 6: Тестирование

6.1. Список: открыть, фильтр, сортировка, пагинация  
6.2. Переход на редактирование: по ссылке из строки, по action=edit&ID=  
6.3. Форма: отображение полей, сохранение (обычный POST), редирект  
6.4. Форма в SidePanel: открытие, сохранение через AJAX, закрытие, обновление списка  
6.5. Inline toggle_active в списке  
6.6. Групповые действия: активация, деактивация, удаление  
6.7. Переход с FIELD_ID: `my_bpbutton_bpbutton_edit.php?FIELD_ID=123` — редирект на запись с этим FIELD_ID

---

## Технические требования

- PHP 8.x, strict_types  
- PSR-12, D7-подход  
- Обратная совместимость: URL списка не меняется; URL редактирования — новый, но старые ссылки (action=edit) редиректят  
- Все тексты — через Loc::getMessage  
- Проверка sessid для POST  
- Проверка прав: GetGroupRight('my.bpbutton') >= 'W' для редактирования

---

## Схема URL после рефакторинга

| Действие | URL |
|----------|-----|
| Список | `my_bpbutton_bpbutton_list.php?lang=ru` |
| Редактирование | `my_bpbutton_bpbutton_edit.php?lang=ru&ID=1` |
| Редактирование по FIELD_ID | `my_bpbutton_bpbutton_edit.php?lang=ru&FIELD_ID=123` |
| Редирект (старая ссылка) | `my_bpbutton_bpbutton_list.php?action=edit&ID=1` → редирект на edit |
| AJAX toggle_active | `bpbutton_list_ajax.php` (без изменений URL) |

---

## Критерии приёмки

- [ ] Создан класс `SettingsFormService` с методами validate, save, getById, getIdByFieldId, toggleActive
- [ ] Создан файл `admin/bpbutton_edit.php` с формой редактирования
- [ ] Создана точка входа `install/admin/my_bpbutton_bpbutton_edit.php`
- [ ] `bpbutton_list.php` содержит только логику списка, < 400 строк
- [ ] `bpbutton_edit.php` < 250 строк
- [ ] Список открывается, фильтры и сортировка работают
- [ ] Редактирование открывается по ссылке и через SidePanel
- [ ] Сохранение формы (обычный POST и AJAX) работает
- [ ] Inline-переключатель активности работает (bpbutton_list_ajax.php)
- [ ] Групповые действия (активация, деактивация, удаление) работают
- [ ] Редирект с action=edit&ID= на новую страницу редактирования
- [ ] Код соответствует PSR-12

---

## Примеры кода

### SettingsFormService::validate (сокращённо)

```php
public function validate(array $data): array
{
    $errors = [];
    $handlerUrl = trim((string)($data['HANDLER_URL'] ?? ''));
    if ($handlerUrl !== '' && !preg_match('~^https?://~i', $handlerUrl) && $handlerUrl[0] !== '/') {
        $errors[] = Loc::getMessage('MY_BPBUTTON_EDIT_ERROR_INVALID_URL');
    }
    $width = trim((string)($data['WIDTH'] ?? ''));
    if ($width !== '' && !preg_match('~^\d+$~', $width) && !preg_match('~^\d+%$~', $width)) {
        $errors[] = Loc::getMessage('MY_BPBUTTON_EDIT_ERROR_INVALID_WIDTH');
    }
    $buttonSize = trim((string)($data['BUTTON_SIZE'] ?? ''));
    $allowedSizes = ['default', 'sm', 'lg'];
    if ($buttonSize !== '' && !in_array($buttonSize, $allowedSizes, true)) {
        $buttonSize = 'default';
    }
    // ...
    return [
        'valid' => empty($errors),
        'errors' => $errors,
        'normalized' => [
            'HANDLER_URL' => $handlerUrl !== '' ? $handlerUrl : null,
            'TITLE' => trim((string)($data['TITLE'] ?? '')) ?: null,
            'WIDTH' => $width ?: null,
            'BUTTON_TEXT' => trim((string)($data['BUTTON_TEXT'] ?? '')) ?: null,
            'BUTTON_SIZE' => $buttonSize ?: null,
            'ACTIVE' => ($data['ACTIVE'] ?? '') === 'Y' ? 'Y' : 'N',
        ],
    ];
}
```

### Редирект в bpbutton_list.php

```php
if ($action === 'edit' && $id > 0) {
    $editUrl = '/bitrix/admin/my_bpbutton_bpbutton_edit.php?lang=' . LANGUAGE_ID . '&ID=' . $id;
    LocalRedirect($editUrl);
    return;
}
```

### Вызов SettingsFormService в bpbutton_edit.php (POST)

```php
if ($isPost && (isset($_POST['save']) || isset($_POST['apply']))) {
    $formService = new SettingsFormService();
    $postData = [
        'HANDLER_URL' => $request->getPost('HANDLER_URL'),
        'TITLE' => $request->getPost('TITLE'),
        'WIDTH' => $request->getPost('WIDTH'),
        'BUTTON_TEXT' => $request->getPost('BUTTON_TEXT'),
        'BUTTON_SIZE' => $request->getPost('BUTTON_SIZE'),
        'ACTIVE' => $request->getPost('ACTIVE'),
    ];
    $validation = $formService->validate($postData);
    if ($validation['valid']) {
        $result = $formService->save($id, $validation['normalized']);
        if ($result->isSuccess()) {
            // AJAX: JSON; обычный: редирект/сообщение
        }
    }
}
```

---

## Тестирование

### Ручное тестирование

1. **Список:** Открыть «Кнопки БП» — таблица отображается, данные загружаются.  
2. **Фильтр:** Применить фильтр по сущности, активности — результат корректен.  
3. **Редактирование:** Клик «Редактировать» в строке — открывается форма (SidePanel или новая вкладка).  
4. **Форма:** Заполнить поля, «Сохранить» — редирект на список, данные сохранены.  
5. **Форма в SidePanel:** Открыть в SidePanel, сохранить — панель закрывается, список обновляется.  
6. **Валидация:** Ввести некорректный URL — ошибка, сохранение не выполняется.  
7. **Toggle active:** Клик по inline-переключателю в списке — статус меняется без перезагрузки.  
8. **Групповые действия:** Выбрать строки, «Активировать»/«Деактивировать»/«Удалить» — действия выполняются.  
9. **FIELD_ID:** Открыть `my_bpbutton_bpbutton_edit.php?FIELD_ID=123` — отображается форма для поля 123.  
10. **Редирект:** Открыть `my_bpbutton_bpbutton_list.php?action=edit&ID=1` — редирект на `my_bpbutton_bpbutton_edit.php?ID=1`.

### Проверка регрессий

- Ссылка «Настроить кнопку» в форме настроек поля (BpButtonUserType::getSettingsHTML) — ведёт на edit с FIELD_ID.  
- Меню «Кнопки БП» — открывает список.  
- Авто-синхронизация — при наличии полей bp_button_field без настроек создаются записи.

---

## История правок

- 2026-03-16: Создан документ задачи TASK-REF-002 на основе REFACTOR-PLAN-001.
