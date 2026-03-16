# TASK-REF-004: Усиление сервисного слоя и API

**Дата создания:** 2026-03-16 (UTC+3, Брест)  
**Статус:** В работе  
**Приоритет:** Высокий  
**Исполнитель:** Bitrix24 Программист (коробка, D7)  
**Связь с планом:** REFACTOR-PLAN-001-five-stages.md, Этап 4

---

## Описание

Этап 4 объединяет результаты этапов 1 и 2: унифицирует backend-слой, устраняет дублирование логики, обеспечивает чёткое разделение Controller → Service → ORM. К моменту выполнения этапа 4 уже существуют: `SettingsResolver`, `ButtonHtmlRenderer` (TASK-REF-001), `SettingsFormService`, `bpbutton_edit.php` (TASK-REF-002).

**Цель задачи:** Усилить сервисный слой, упростить `ButtonController`, ввести единый формат ответов API, устранить дублирование доступа к `SettingsTable`.

---

## Контекст

### Текущая архитектура (после этапов 1–2)

| Слой | Компоненты | Ответственность |
|------|------------|-----------------|
| **Controller** | ButtonController | getConfigAction: sessid, CRM-доступ, вызов ButtonService, logClick, возврат JSON |
| **Service** | ButtonService | getSidePanelConfig, logClick |
| **Service** | SettingsResolver | getButtonText, getButtonSize, getDisplaySettings (для UserField) |
| **Service** | SettingsFormService | validate, save, getById, getIdByFieldId, toggleActive (для админки) |
| **ORM** | SettingsTable, LogsTable | Прямой доступ из сервисов |

### Потоки данных

1. **CRM-кнопка (клик):** JS → `/bitrix/services/my.bpbutton/button/ajax.php` → ButtonController::getConfigAction → ButtonService::getSidePanelConfig → SettingsTable
2. **UserField (рендеринг):** BpButtonUserType → ButtonHtmlRenderer → SettingsResolver → SettingsTable
3. **Админка (форма):** bpbutton_edit.php → SettingsFormService::validate, save → SettingsTable
4. **Админка (toggle):** bpbutton_list_ajax.php → SettingsFormService::toggleActive → SettingsTable

### Выявленные проблемы

- **Дублирование доступа к SettingsTable:** ButtonService, SettingsResolver, SettingsFormService, bpbutton_list.php, EventHandler — каждый обращается к таблице по-своему.
- **ButtonController:** Создаёт `new ButtonService()` в нескольких местах, дублирует вызовы logClick.
- **Формат ошибок:** Разные точки входа (ajax.php, Controller) должны возвращать единую структуру.
- **canReadCrmEntity:** Логика проверки прав в контроллере — можно вынести в отдельный класс.

---

## Модули и компоненты

### Новые файлы (опционально)

| Путь | Назначение |
|------|------------|
| `local/modules/my.bpbutton/lib/Repository/SettingsRepository.php` | Централизованный доступ к `SettingsTable` для чтения. Методы: getByFieldId, getById. |
| `local/modules/my.bpbutton/lib/Helper/CrmAccessChecker.php` | Проверка прав на чтение CRM-сущности. Метод: canRead(string $entityId, int $elementId): bool. |

### Изменяемые файлы

| Путь | Изменения |
|------|-----------|
| `local/modules/my.bpbutton/lib/Service/ButtonService.php` | Использовать SettingsRepository (если создан) вместо прямого SettingsTable::getList. |
| `local/modules/my.bpbutton/lib/Service/SettingsResolver.php` | Использовать SettingsRepository (если создан). |
| `local/modules/my.bpbutton/lib/Controller/ButtonController.php` | Внедрить ButtonService и CrmAccessChecker через конструктор. Упростить getConfigAction. |
| `local/modules/my.bpbutton/install/index.php` | Добавить автозагрузку SettingsRepository, CrmAccessChecker (если созданы). |
| `bitrix/services/my.bpbutton/button/ajax.php` | Убедиться в едином формате ответов при исключениях. |

---

## Зависимости

### От каких модулей/задач зависит

- **TASK-REF-001** — SettingsResolver, ButtonHtmlRenderer созданы
- **TASK-REF-002** — SettingsFormService, bpbutton_edit.php созданы
- Модуль `my.bpbutton` установлен

### Какие задачи зависят от этой

- TASK-REF-005 (Structure & docs) — фиксация итоговой архитектуры

---

## Детальная спецификация

### 1. Единый формат ответов API

**Успех:**

```json
{
  "success": true,
  "data": {
    "url": "string",
    "title": "string",
    "width": "string|number",
    "context": { "settingsId", "entityId", "elementId", "fieldId", "userId" }
  }
}
```

**Ошибка:**

```json
{
  "success": false,
  "error": {
    "code": "string",
    "message": "string"
  }
}
```

**Коды ошибок (канонический список):**

| Код | Описание |
|-----|----------|
| INVALID_SESSION | Невалидная сессия (sessid) |
| ACCESS_DENIED | Нет прав на CRM-сущность |
| SETTINGS_NOT_FOUND | Запись настроек не найдена |
| BUTTON_INACTIVE | Кнопка отключена (ACTIVE=N) |
| INTERNAL_ERROR | Внутренняя ошибка |

**Точки входа, возвращающие JSON:**

- `bitrix/services/my.bpbutton/button/ajax.php` — через ButtonController
- При исключении в ajax.php — тот же формат `{ success: false, error: { code, message } }`

---

### 2. SettingsRepository (опционально)

**Namespace:** `My\BpButton\Repository`  
**Файл:** `lib/Repository/SettingsRepository.php`

**Назначение:** Централизованный доступ к `SettingsTable` для операций чтения. Устраняет дублирование getList/getByPrimary в ButtonService, SettingsResolver.

**Методы:**

```php
/**
 * Получить запись настроек по FIELD_ID.
 * @param int $fieldId
 * @return array|null
 */
public function getByFieldId(int $fieldId): ?array

/**
 * Получить запись настроек по ID.
 * @param int $id
 * @return array|null
 */
public function getById(int $id): ?array
```

**Кеширование:** Опционально — статический кеш в рамках запроса по ключу $fieldId/$id. При первом обращении — запрос в SettingsTable, при повторном — из кеша.

**Использование:**
- ButtonService::getSidePanelConfig — вместо SettingsTable::getList использовать $this->repository->getByFieldId($fieldId)
- SettingsResolver::getDisplaySettings — использовать repository->getByFieldId
- SettingsFormService::getById — использовать repository->getById (или оставить getByPrimary для записи с блокировкой)

**Примечание:** SettingsFormService выполняет save/update — эти операции остаются в сервисе. Repository только для чтения.

---

### 3. CrmAccessChecker (опционально)

**Namespace:** `My\BpButton\Helper`  
**Файл:** `lib/Helper/CrmAccessChecker.php`

**Назначение:** Вынести логику проверки прав на CRM-сущность из ButtonController.

**Методы:**

```php
/**
 * Проверить право на чтение CRM-сущности.
 * @param string $entityId — LEAD, DEAL, CONTACT, DYNAMIC_123 и т.д.
 * @param int $elementId
 * @return bool
 */
public function canRead(string $entityId, int $elementId): bool
```

**Логика:** Перенести из ButtonController::canReadCrmEntity — нормализация entityId, CCrmOwnerType::ResolveID, EntityAuthorization::checkReadPermission.

**Использование в ButtonController:**

```php
$checker = new CrmAccessChecker();
if (!$checker->canRead($entityId, $elementId)) {
    $this->service->logClick(...);
    return $this->error('ACCESS_DENIED', ...);
}
```

---

### 4. Рефакторинг ButtonController

**Цель:** Контроллер только маршрутизует запрос и вызывает сервисы. Вся бизнес-логика — в сервисах.

**Текущие обязанности (что остаётся):**
- Проверка sessid
- Загрузка модулей (crm, my.bpbutton)
- Получение userId из $GLOBALS['USER']
- Проверка прав на CRM (через CrmAccessChecker или inline)
- Вызов ButtonService::getSidePanelConfig
- Вызов ButtonService::logClick для аудита (success/error)
- Возврат результата

**Изменения:**
- ButtonService: Bitrix создаёт контроллер через `new ButtonController()` в ajax.php — DI-контейнера нет. Решение: приватный метод `getService(): ButtonService` возвращает кешированный экземпляр (свойство `$this->service`) или `new ButtonService()` при первом вызове.
- Внедрить CrmAccessChecker (если создан) — аналогично.
- Упростить структуру: один вызов $service->getSidePanelConfig, затем по результату — logClick и return.

**Пример упрощённого getConfigAction:**

```php
public function getConfigAction(string $entityId, int $elementId, int $fieldId): array
{
    $userId = $this->getUserId();
    if (!$this->checkSession()) {
        return $this->errorResponse('INVALID_SESSION', ...);
    }
    if (!$this->loadModules()) {
        return $this->errorResponse('INTERNAL_ERROR', ...);
    }
    if (!$this->canReadCrmEntity($entityId, $elementId)) {
        $this->service->logClick([...], 'ACCESS_DENIED', ...);
        return $this->errorResponse('ACCESS_DENIED', ...);
    }
    $result = $this->service->getSidePanelConfig($entityId, $elementId, $fieldId, $userId);
    $this->auditLog($result, $entityId, $elementId, $fieldId, $userId);
    return $result;
}
```

Вынести checkSession, loadModules, getUserId, canReadCrmEntity, auditLog в приватные методы — контроллер станет читаемее.

---

### 5. Интеграция SettingsResolver и ButtonService

**Вариант A (рекомендуемый):** Оставить оба класса. SettingsResolver — для UserField (рендеринг), ButtonService — для CRM (SidePanel). Каждый имеет свою зону ответственности.

**Вариант B:** Объединить: добавить в ButtonService метод `getDisplaySettings(int $fieldId): array` с кешированием. SettingsResolver делегирует в ButtonService. Удалить SettingsResolver. ButtonHtmlRenderer использует ButtonService. Риск: ButtonService становится перегруженным. Рекомендация: Вариант A.

---

### 6. Интеграция SettingsFormService

После TASK-REF-002 SettingsFormService уже используется в bpbutton_edit.php и bpbutton_list_ajax.php. На этапе 4:

- Убедиться, что SettingsFormService не дублирует валидацию из других мест.
- Если создан SettingsRepository — SettingsFormService::getById может использовать repository->getById. Методы save, toggleActive — остаются в SettingsFormService (запись в БД).

---

## Ступенчатые подзадачи

### Подзадача 1: Документировать единый формат API

1.1. Создать или обновить документ `docs_antonov/first_module/api/response_format.md` с описанием формата успеха и ошибки  
1.2. Проверить, что ajax.php при исключении возвращает тот же формат  
1.3. Проверить, что ButtonController возвращает только этот формат

### Подзадача 2: Создать SettingsRepository (опционально)

2.1. Создать `lib/Repository/SettingsRepository.php`  
2.2. Реализовать getByFieldId, getById  
2.3. Добавить кеширование в рамках запроса (опционально)  
2.4. Обновить ButtonService — использовать repository  
2.5. Обновить SettingsResolver — использовать repository  
2.6. Зарегистрировать в autoload (install/index.php)

### Подзадача 3: Создать CrmAccessChecker (опционально)

3.1. Создать `lib/Helper/CrmAccessChecker.php`  
3.2. Перенести логику canReadCrmEntity из ButtonController  
3.3. Зарегистрировать в autoload  
3.4. Обновить ButtonController — использовать CrmAccessChecker

### Подзадача 4: Рефакторинг ButtonController

4.1. Вынести checkSession, loadModules, getUserId в приватные методы  
4.2. Вынести auditLog (вызов logClick по результату) в приватный метод  
4.3. Упростить getConfigAction — последовательность проверок и вызовов  
4.4. Убедиться, что все return используют единый формат errorResponse

### Подзадача 5: Проверка ajax.php

5.1. Убедиться, что при исключении ajax.php возвращает `{ success: false, error: { code: 'INTERNAL_ERROR', message: '...' } }`  
5.2. Проверить кодировку JSON_UNESCAPED_UNICODE

### Подзадача 6: Тестирование

6.1. Успешный сценарий: клик по кнопке → SidePanel  
6.2. INVALID_SESSION: неверный sessid → ошибка  
6.3. ACCESS_DENIED: нет прав на сущность → ошибка  
6.4. SETTINGS_NOT_FOUND: несуществующий fieldId → ошибка  
6.5. BUTTON_INACTIVE: ACTIVE=N → ошибка  
6.6. Админка: форма, toggle_active — работают  
6.7. UserField: кнопка рендерится (SettingsResolver/ButtonHtmlRenderer)

---

## Технические требования

- PHP 8.x, strict_types  
- PSR-12, D7-подход  
- Обратная совместимость: формат ответа API не меняется для клиентов  
- Все сообщения об ошибках — через Loc::getMessage  
- Логирование исключений — через SecurityHelper::safeLog

---

## Схема слоёв после этапа 4

```
┌─────────────────────────────────────────────────────────────────┐
│  Точки входа                                                     │
│  ajax.php, bpbutton_edit.php, bpbutton_list_ajax.php            │
└────────────────────────────┬────────────────────────────────────┘
                             │
┌────────────────────────────▼────────────────────────────────────┐
│  Controller (ButtonController)                                    │
│  — проверка sessid, модулей, прав                                │
│  — вызов сервисов                                                │
│  — возврат JSON                                                  │
└────────────────────────────┬────────────────────────────────────┘
                             │
┌────────────────────────────▼────────────────────────────────────┐
│  Service                                                         │
│  ButtonService      — getSidePanelConfig, logClick               │
│  SettingsResolver  — getDisplaySettings (UserField)            │
│  SettingsFormService — validate, save, toggleActive (Admin)      │
└────────────────────────────┬────────────────────────────────────┘
                             │
┌────────────────────────────▼────────────────────────────────────┐
│  Repository (опционально)                                        │
│  SettingsRepository — getByFieldId, getById                      │
└────────────────────────────┬────────────────────────────────────┘
                             │
┌────────────────────────────▼────────────────────────────────────┐
│  ORM                                                             │
│  SettingsTable, LogsTable                                       │
└─────────────────────────────────────────────────────────────────┘
```

---

## Критерии приёмки

- [ ] Единый формат ответов API задокументирован и соблюдается во всех точках входа
- [ ] ButtonController упрощён: проверки и вызовы вынесены в методы
- [ ] (Опционально) SettingsRepository создан и используется ButtonService, SettingsResolver
- [ ] (Опционально) CrmAccessChecker создан и используется ButtonController
- [ ] Нет дублирования: каждый запрос к SettingsTable — через один путь (сервис или repository)
- [ ] Все сценарии (успех, ошибки) работают корректно
- [ ] Код соответствует PSR-12

---

## Примеры кода

### ButtonController::getConfigAction (упрощённая структура)

```php
public function getConfigAction(string $entityId, int $elementId, int $fieldId): array
{
    $userId = $this->getCurrentUserId();
    if (!$this->validateSession()) {
        return $this->errorResponse('INVALID_SESSION', Loc::getMessage('...'));
    }
    if (!$this->loadRequiredModules()) {
        return $this->errorResponse('INTERNAL_ERROR', Loc::getMessage('...'));
    }
    if (!$this->checkCrmAccess($entityId, $elementId)) {
        $this->getService()->logClick([...], 'ACCESS_DENIED', '...');
        return $this->errorResponse('ACCESS_DENIED', Loc::getMessage('...'));
    }
    $result = $this->getService()->getSidePanelConfig($entityId, $elementId, $fieldId, $userId);
    $this->auditLogResult($result, $entityId, $elementId, $fieldId, $userId);
    return $result;
}
```

### SettingsRepository::getByFieldId

```php
public function getByFieldId(int $fieldId): ?array
{
    if ($fieldId <= 0) {
        return null;
    }
    $row = SettingsTable::getList([
        'filter' => ['=FIELD_ID' => $fieldId],
        'limit' => 1,
    ])->fetch();
    return $row ?: null;
}
```

---

## Тестирование

### Ручное тестирование

1. **Успех:** Карточка CRM → клик → SidePanel с корректным URL.  
2. **Ошибки:** Проверить каждый код (INVALID_SESSION, ACCESS_DENIED, SETTINGS_NOT_FOUND, BUTTON_INACTIVE) — корректный JSON и сообщение.  
3. **Админка:** Форма сохранения, toggle_active — без регрессий.  
4. **UserField:** Кнопка отображается с правильным текстом и размером.

### Проверка формата ответа

- Успех: `success: true`, `data` присутствует.  
- Ошибка: `success: false`, `error.code`, `error.message` присутствуют.  
- Нет утечки stack trace, SQL, внутренних путей.

---

## История правок

- 2026-03-16: Создан документ задачи TASK-REF-004 на основе REFACTOR-PLAN-001.
