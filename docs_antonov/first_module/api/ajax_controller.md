## AJAX-контроллер модуля «BP Button Field»

Документ описывает API контроллера на базе `Bitrix\Main\Engine\Controller`, который:

- Принимает запросы от кнопки в карточке CRM.
- Проверяет сессию (`check_bitrix_sessid()`) и права пользователя.
- Возвращает конфигурацию для открытия `BX.SidePanel` / iframe.

Ниже описаны:

- методы контроллера;
- форматы запросов и ответов (JSON);
- единый формат ошибок и общие правила обработки исключений.

---

### 1. Общие сведения о контроллере

- Пространство имён: `My\BpButton\Controller`.
- Класс: `ButtonController` (рабочее название).
- Базовый класс: `Bitrix\Main\Engine\Controller`.
- Типичный endpoint: `/bitrix/services/my.bpbutton/button/ajax.php` или роут, зарегистрированный в настройках модуля.

Контроллер вызывается только с фронтенда (JS‑расширения), которое инициализируется при клике по кнопке `bp_button_field`.

---

### 2. Action‑методы

#### 2.1. `getConfigAction`

**Назначение:** вернуть конфигурацию для открытия `BX.SidePanel`/iframe по заданному полю и контексту CRM.

**Сигнатура (понятие):**

```php
public function getConfigAction(string $entityId, int $elementId, int $fieldId): array
```

**Входные параметры:**

- `entityId` — тип сущности CRM (например, `DEAL`, `LEAD`, `CONTACT`, `COMPANY`, ID смарт‑процесса);
- `elementId` — ID элемента CRM;
- `fieldId` — ID пользовательского поля `bp_button_field`.

Параметры передаются в теле запроса (POST JSON) или как query‑параметры — конкретный способ будет выбран на этапе реализации JS.

**Алгоритм:**

1. Проверить сессию (`check_bitrix_sessid()`).
2. Проверить права пользователя на чтение указанной сущности (`entityId`/`elementId`).
3. Через сервисный слой (`ButtonService`) получить настройки из `SettingsTable`:
   - найти запись по `fieldId`;
   - убедиться, что кнопка активна.
4. Сформировать ответ с конфигурацией:
   - URL обработчика (iframe);
   - заголовок окна;
   - ширина SidePanel;
   - контекст (entity, element, user).
5. Вернуть успешный JSON‑ответ.

#### 2.2. `logClickAction` (расширение для логирования)

**Назначение:** зафиксировать факт нажатия кнопки и (опционально) результат выполнения логики.  
Используется в расширенных версиях, когда реализован `my_bpbutton_logs`.

**Сигнатура (понятие):**

```php
public function logClickAction(int $fieldId, string $entityId, int $elementId, string $status, string $message = null): array
```

**Входные параметры:**

- `fieldId` — ID пользовательского поля;
- `entityId` — тип сущности CRM;
- `elementId` — ID элемента CRM;
- `status` — результат (`SUCCESS`, `ERROR`, `TIMEOUT`, и т.п.);
- `message` — диагностическое сообщение (опционально).

**Алгоритм (общий):**

1. Проверить сессию и права на сущность.
2. Проверить наличие соответствующей записи в `SettingsTable`.
3. Сохранить лог через `LogsTable` (через `ButtonService`).
4. Вернуть успешный ответ или ошибку.

#### 2.3. `getExtendedConfigAction` (зарезервировано)

**Статус:** зарезервированный метод для будущих версий (расширенная конфигурация).  
В первой реализации может отсутствовать или проксировать базовую логику `getConfigAction`.

**Возможные задачи:**

- возврат дополнительных параметров для фронтенда:
  - условия отображения/доступности кнопки (например, в зависимости от стадии сделки);
  - подсказки/хинты для пользователя;
  - параметры интеграции с бизнес‑процессами/роботами.
- поддержка различных «режимов» работы кнопки (например, предпросмотр, тестовый режим).

**Предложенная сигнатура (понятие):**

```php
public function getExtendedConfigAction(string $entityId, int $elementId, int $fieldId, array $options = []): array
```

**Общие принципы:**

- наследует все требования по безопасности и проверкам от `getConfigAction`;
- не нарушает контракт базового `getConfigAction` — старый фронтенд продолжает работать как раньше;
- может использовать тот же `ButtonService`, расширяя модель данных при необходимости.

---

### 3. Форматы запросов и ответов (JSON)

#### 3.1. Запрос к `getConfigAction`

**Пример POST‑запроса (JSON‑тело):**

```json
{
  "entityId": "DEAL",
  "elementId": 123,
  "fieldId": 456,
  "sessid": "XXX"
}
```

**Успешный ответ:**

```json
{
  "success": true,
  "data": {
    "url": "/local/bpbutton/handlers/deal_form.php?DEAL_ID=123&FIELD_ID=456",
    "title": "Форма обработки сделки",
    "width": "70%",
    "context": {
      "entityId": "DEAL",
      "elementId": 123,
      "fieldId": 456,
      "userId": 789
    }
  }
}
```

**Ошибка (общий формат, см. ниже):**

```json
{
  "success": false,
  "error": {
    "code": "ACCESS_DENIED",
    "message": "Недостаточно прав для доступа к элементу CRM."
  }
}
```

#### 3.2. Запрос к `logClickAction` (расширение)

**Пример тела запроса:**

```json
{
  "fieldId": 456,
  "entityId": "DEAL",
  "elementId": 123,
  "status": "SUCCESS",
  "message": "Окно успешно открыто",
  "sessid": "XXX"
}
```

**Успешный ответ:**

```json
{
  "success": true
}
```

---

### 4. Единый формат ошибок

Все ошибки контроллера должны возвращаться в структурированном виде:

```json
{
  "success": false,
  "error": {
    "code": "<КОД_ОШИБКИ>",
    "message": "<Читабельное сообщение для пользователя>"
  }
}
```

Примеры кодов ошибок:

- `INVALID_SESSION` — не пройдена проверка `check_bitrix_sessid()`;
- `ACCESS_DENIED` — недостаточно прав для работы с указанной сущностью;
- `SETTINGS_NOT_FOUND` — отсутствует запись в `my_bpbutton_settings` для данного `fieldId`;
- `BUTTON_INACTIVE` — кнопка помечена как неактивная (`ACTIVE = 'N'`);
- `INTERNAL_ERROR` — внутренняя ошибка (подробности только в логах).

**Принципы:**

- пользователю показывается только `message`, безопасное и локализованное;
- технические детали (stack trace, SQL, внутренние исключения) логируются на сервере, но не уходят в JSON.

---

### 5. Обработка исключений

- Контроллер может использовать:
  - собственные исключения модуля (например, `ButtonConfigException`);
  - стандартные исключения Bitrix (`\Bitrix\Main\SystemException`, `\Bitrix\Main\ArgumentException`).
- В `ButtonController` рекомендуется:
  - перехватывать исключения в одном месте (общий `try/catch` или переопределённый `run`/`processBeforeAction`);
  - маппить типы исключений на коды ошибок (`INVALID_SESSION`, `ACCESS_DENIED`, `SETTINGS_NOT_FOUND`, `INTERNAL_ERROR`);
  - логировать детали исключения отдельно.

---

### 6. Связь с другими документами

- Архитектура контроллера и сервисного слоя — см. `architecture/backend_d7.md`.
- Модель данных (`SettingsTable`, `LogsTable`) — см. `architecture/data_model.md`.
- Сценарии UX и ожидания фронтенда — см. `ui_ux/crm_card_button.md`.

