# TASK-013: Отображение ENTITY_ID и названия смарт-процесса в настройках «Кнопки БП»

**Дата создания:** 2026-03-16 (UTC+3, Брест)  
**Статус:** Новая  
**Приоритет:** Средний  
**Исполнитель:** Bitrix24 Программист (коробка, D7 + Vanilla JS)

## Описание

Пользовательское поле типа `bp_button_field` привязывается к сущности CRM (лиды, сделки, контакты, компании, **смарт-процессы**). В настройках «Кнопки БП» администратор видит только технический идентификатор `ENTITY_ID` (например, `CRM_DYNAMIC_123`), но не видит человекочитаемое название сущности.

**Цель задачи:** вывести в административном интерфейсе «Кнопки БП»:
1. **ENTITY_ID** — идентификатор сущности (entity_id смарт-процесса);
2. **Название сущности** — человекочитаемое имя (для смарт-процессов — название из справочника типов; для стандартных сущностей CRM — локализованное имя).

Это улучшит UX: администратор сможет быстро понять, к какой сущности привязана кнопка, без необходимости сверяться с техническими кодами.

## Контекст

- Поле `ENTITY_ID` уже хранится в `my_bpbutton_settings` и берётся из `b_user_field.ENTITY_ID` при создании поля.
- Для **смарт-процессов** `ENTITY_ID` имеет вид `CRM_DYNAMIC_{typeId}`, где `typeId` — ID типа в таблице `b_crm_dynamic_type`.
- Название смарт-процесса хранится в `Bitrix\Crm\Model\Dynamic\TypeTable` (поле `TITLE`).
- Для стандартных сущностей CRM (`CRM_LEAD`, `CRM_DEAL`, `CRM_CONTACT`, `CRM_COMPANY`) названия берутся из языковых файлов модуля `crm`.
- Документация по админ-интерфейсу: `../module_structure/settings_ui.md`.
- Реестр и форма редактирования: `TASK-002-admin-grid`, `TASK-010-admin-field-binding-fix`.

## Модули и компоненты

- `local/modules/my.bpbutton/admin/bpbutton_list.php`  
  — реестр: добавить колонку/отображение названия сущности рядом с ENTITY_ID.
- `local/modules/my.bpbutton/admin/bpbutton_edit.php`  
  — форма редактирования: вывести ENTITY_ID и название сущности (read-only).
- `local/modules/my.bpbutton/lib/Service/EntityNameResolver.php` (новый)  
  — сервис для получения человекочитаемого названия по ENTITY_ID.
- `local/modules/my.bpbutton/lang/ru/admin/bpbutton_list.php`  
  — языковые строки для новых подписей.
- `local/modules/my.bpbutton/lang/ru/admin/bpbutton_edit.php`  
  — языковые строки (если используются в форме).

## Зависимости

- **От других задач:**
  - `TASK-002-admin-grid` — реестр настроек;
  - `TASK-010-admin-field-binding-fix` — форма редактирования, связь с полями.
- **От ядра Bitrix24:**
  - `Bitrix\Main\UserFieldTable` — ENTITY_ID пользовательского поля;
  - `Bitrix\Crm\Model\Dynamic\TypeTable` — название смарт-процесса по typeId;
  - модуль `crm` — для доступа к TypeTable и локализации стандартных сущностей.

## Ступенчатые подзадачи

### 1. Создать сервис EntityNameResolver

**Файл:** `lib/Service/EntityNameResolver.php`

Сервис с методом `resolve(string $entityId): array`:
- Вход: `ENTITY_ID` (например, `CRM_DYNAMIC_123`, `CRM_LEAD`, `CRM_DEAL`).
- Выход: `['entity_id' => string, 'entity_name' => string]`:
  - `entity_id` — переданный ENTITY_ID;
  - `entity_name` — человекочитаемое название.

**Логика:**
- Если `ENTITY_ID` начинается с `CRM_DYNAMIC_`:
  - извлечь `typeId` (число после префикса);
  - загрузить запись из `TypeTable::getById($typeId)`;
  - если найдена — `entity_name = $row['TITLE']`;
  - если не найдена — `entity_name = ENTITY_ID` (fallback).
- Для стандартных сущностей (`CRM_LEAD`, `CRM_DEAL`, `CRM_CONTACT`, `CRM_COMPANY`):
  - использовать `Loc::getMessage('CRM_LEAD', ...)` или аналоги из модуля `crm`;
  - или маппинг: `CRM_LEAD` → «Лиды», `CRM_DEAL` → «Сделки», `CRM_CONTACT` → «Контакты», `CRM_COMPANY` → «Компании».
- Для неизвестных сущностей — `entity_name = ENTITY_ID`.

**Пример кода (псевдокод):**
```php
namespace My\BpButton\Service;

use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Bitrix\Crm\Model\Dynamic\TypeTable;

class EntityNameResolver
{
    /** Маппинг ENTITY_ID → ключ Loc::getMessage в модуле crm */
    private static array $entityLocKeys = [
        'CRM_LEAD'    => 'CRM_COMMON_LEADS',    // «Лиды»
        'CRM_DEAL'    => 'CRM_COMMON_DEALS',    // «Сделки»
        'CRM_CONTACT' => 'CRM_COMMON_CONTACTS', // «Контакты»
        'CRM_COMPANY' => 'CRM_COMMON_COMPANIES', // «Компании»
    ];

    public function resolve(string $entityId): array
    {
        $entityId = trim($entityId);
        if ($entityId === '') {
            return ['entity_id' => '', 'entity_name' => ''];
        }

        // Смарт-процесс: CRM_DYNAMIC_123
        if (preg_match('/^CRM_DYNAMIC_(\d+)$/', $entityId, $m) && Loader::includeModule('crm')) {
            $typeId = (int)$m[1];
            $row = TypeTable::getById($typeId)->fetch();
            $entityName = $row ? (string)($row['TITLE'] ?? $entityId) : $entityId;
            return ['entity_id' => $entityId, 'entity_name' => $entityName];
        }

        // Стандартные сущности CRM
        if (isset(self::$entityLocKeys[$entityId]) && Loader::includeModule('crm')) {
            Loc::loadMessages($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/crm/lib/Service/Localization.php');
            $entityName = Loc::getMessage(self::$entityLocKeys[$entityId]) ?: $entityId;
            return ['entity_id' => $entityId, 'entity_name' => $entityName];
        }

        return ['entity_id' => $entityId, 'entity_name' => $entityId];
    }
}
```

### 2. Интегрировать EntityNameResolver в реестр (bpbutton_list.php)

- При формировании списка для каждой строки вызывать `EntityNameResolver::resolve($row['ENTITY_ID'])`.
- В колонке «Сущность» (ENTITY_ID) выводить:
  - либо: `ENTITY_ID` + в скобках `entity_name` (например: `CRM_DYNAMIC_123 (Заявки на ремонт)`);
  - либо: две строки — `ENTITY_ID` и `entity_name` (по согласованию с UX).
- **Оптимизация:** для большого списка — кешировать результаты по ENTITY_ID в рамках одного запроса (массив `$entityNamesCache`), чтобы не вызывать TypeTable для каждой строки с одинаковым ENTITY_ID.

### 3. Интегрировать EntityNameResolver в форму редактирования (bpbutton_edit.php)

- В блоке «Сущность» (ENTITY_ID) вывести:
  - Строка 1: `ENTITY_ID` (например, `CRM_DYNAMIC_123`, `CRM_LEAD`).
  - Строка 2: `entity_name` (например, «Заявки на ремонт», «Лиды») — с подписью «Название сущности» или «Тип» (по локализации).
- Оба поля — только для чтения (read-only).

### 4. Локализация

- Добавить в `lang/ru/admin/bpbutton_list.php`:
  - `MY_BPBUTTON_LIST_ENTITY_WITH_NAME` — шаблон «%ENTITY_ID% (%ENTITY_NAME%)» или аналогичный.
- Добавить в `lang/ru/admin/bpbutton_edit.php` (если отдельный файл):
  - `MY_BPBUTTON_EDIT_ENTITY_NAME` — «Название сущности» или «Тип смарт-процесса».

### 5. Обработка отсутствия модуля CRM

- Если модуль `crm` не подключён — `EntityNameResolver` должен возвращать `entity_name = ENTITY_ID` (без ошибок).
- Проверка `Loader::includeModule('crm')` перед обращением к `TypeTable`.

## API-методы

- **Bitrix24 D7:**  
  - `Bitrix\Crm\Model\Dynamic\TypeTable::getById($id)` — получение типа смарт-процесса.  
  - Документация: https://dev.1c-bitrix.ru/api_d7/bitrix/crm/model/dynamic/typetable/
- **Внешние REST:** не используются.

## Технические требования

- Коробочная версия Bitrix24, PHP 8.x, D7 ORM.
- Модуль `crm` может быть не установлен — код должен обрабатывать это gracefully.
- Кеширование: в рамках одного запроса списка не делать повторные запросы к TypeTable для одного и того же ENTITY_ID.
- Производительность: при 100+ записях в реестре — batch-запрос или кеш по уникальным ENTITY_ID.

## Критерии приёмки

- [ ] В реестре «Кнопки БП» колонка «Сущность» отображает ENTITY_ID и (в скобках или отдельно) человекочитаемое название сущности.
- [ ] Для смарт-процессов (`CRM_DYNAMIC_XXX`) отображается название из `TypeTable.TITLE`.
- [ ] Для стандартных сущностей CRM (лиды, сделки, контакты, компании) отображается локализованное название.
- [ ] В форме редактирования настроек кнопки выводится ENTITY_ID и название сущности (оба read-only).
- [ ] При отсутствии модуля `crm` или неизвестном ENTITY_ID — отображается только ENTITY_ID, без ошибок.
- [ ] Все новые тексты интерфейса локализованы.
- [ ] Нет регрессии: остальной функционал реестра и формы работает как прежде.

## Тестирование

- **Сценарий 1:** Создать поле `bp_button_field` для смарт-процесса (например, «Заявки на ремонт»). Открыть реестр «Кнопки БП». Ожидание: в колонке «Сущность» отображается `CRM_DYNAMIC_XXX` и название «Заявки на ремонт».
- **Сценарий 2:** Открыть форму редактирования настроек кнопки для смарт-процесса. Ожидание: в блоке «Сущность» выведены ENTITY_ID и название сущности.
- **Сценарий 3:** Проверить отображение для стандартных сущностей (лиды, сделки, контакты, компании).
- **Сценарий 4:** Удалить/отключить модуль `crm` (если возможно в тестовой среде) — реестр и форма не должны выдавать фатальные ошибки.

## Примеры кода (опционально)

### Пример вызова EntityNameResolver в реестре

```php
$entityResolver = new \My\BpButton\Service\EntityNameResolver();
$entityNamesCache = [];

foreach ($rows as $row) {
    $entityId = (string)($row['ENTITY_ID'] ?? '');
    if (!isset($entityNamesCache[$entityId])) {
        $entityNamesCache[$entityId] = $entityResolver->resolve($entityId);
    }
    $resolved = $entityNamesCache[$entityId];
    $displayValue = $resolved['entity_name'] !== $resolved['entity_id']
        ? $resolved['entity_id'] . ' (' . $resolved['entity_name'] . ')'
        : $resolved['entity_id'];
    $listRow->AddViewField('ENTITY_ID', htmlspecialcharsbx($displayValue));
}
```

## История правок

- 2026-03-16: Создана задача. Требование: вывести ENTITY_ID и название сущности (смарт-процесса) в настройках «Кнопки БП».
