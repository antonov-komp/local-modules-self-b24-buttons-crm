# TASK-003: AJAX-контроллер и API модуля «BP Button Field»

**Дата создания:** 2026-03-16 (UTC+3, Брест)  
**Статус:** Новая  
**Приоритет:** Высокий  
**Исполнитель:** Bitrix24 Программист (коробка, D7 + Vanilla JS)

## Описание

Задача описывает реализацию контроллера на базе `Bitrix\Main\Engine\Controller` для обработки запросов от кнопок:

- Проверка сессии и прав доступа пользователя.
- Получение конфигурации SidePanel по `FIELD_ID` и контексту CRM-элемента.
- Возврат данных в формате JSON для JS-расширения.

Полная спецификация методов описана в `../api/ajax_controller.md`. В рамках данной задачи контроллер должен быть реализован и связан с сервисным слоем и UX‑требованиями.

## Контекст

- Основная цель и фичи модуля описаны в `../local_button.md`.
- Архитектура backend‑слоя (сервисы, ORM, события) — в:
  - `../architecture/backend_d7.md`;
  - `../architecture/data_model.md`;
  - `../architecture/security.md`.
- UX‑ожидания по поведению кнопки и обработке ошибок — в:
  - `../ui_ux/crm_card_button.md`.

## Модули и компоненты

- `local/modules/my.bpbutton/lib/Controller/ButtonController.php`  
  — контроллер на базе `Bitrix\Main\Engine\Controller`, обрабатывающий AJAX‑запросы от кнопок.
- `local/modules/my.bpbutton/lib/Service/ButtonService.php`  
  — сервисный слой, предоставляющий методы для получения конфигурации и (в будущем) логирования.
- `local/modules/my.bpbutton/lib/Internals/SettingsTable.php`  
  — ORM‑сущность для таблицы `my_bpbutton_settings`.
- `local/modules/my.bpbutton/lib/Internals/LogsTable.php` (план)  
  — ORM‑сущность для таблицы `my_bpbutton_logs`, используемая для логирования (в увязке с `TASK-004-logging.md`).
- Языковые файлы для контроллера:
  - `local/modules/my.bpbutton/lang/ru/lib/Controller/buttoncontroller.php`.

## Зависимости

- От `TASK-001-user-type`:
  - кнопка в карточке CRM уже отрисовывается и вызывает JS‑расширение с нужными `data-*` атрибутами.
- От архитектуры:
  - `ButtonService` и `SettingsTable` должны быть доступны и корректно настроены.
- Связь с `TASK-004-logging`:
  - при реализации логирования часть функционала может быть вынесена в отдельный action (`logClickAction`).

## Ступенчатые подзадачи

1. **Создать класс контроллера**
   - Определить `ButtonController` в пространстве имён `My\BpButton\Controller`, унаследовать от `Bitrix\Main\Engine\Controller`.
   - Подключить необходимые пространства имён (`ButtonService`, `SettingsTable`, классы безопасности CRM).

2. **Реализовать `getConfigAction`**
   - Определить метод `public function getConfigAction(string $entityId, int $elementId, int $fieldId): array`.
   - Внутри action:
     1. Проверить сессию (`check_bitrix_sessid()`).
     2. Проверить права на чтение указанной CRM‑сущности (`entityId`, `elementId`).
     3. Через `ButtonService` получить конфигурацию для SidePanel:
        - URL обработчика;
        - заголовок окна;
        - ширину;
        - контекст (entity/element/field/user).
     4. Вернуть данные в виде массива, который Engine сериализует в JSON.

3. **(Опционально) подготовить `logClickAction`**
   - Спроектировать и реализовать метод `logClickAction` в соответствии с `../api/ajax_controller.md` и `TASK-004-logging.md`, но:
     - приоритизировать `getConfigAction` как часть MVP;
     - при необходимости оставить `logClickAction` за флагом FEATURE (может быть реализован в отдельной итерации).

4. **Единый формат ошибок**
   - В контроллере реализовать маппинг ошибок на единый формат:
     - `success` — булево (`true` / `false`);
     - при ошибке — объект:
       - `error.code` — строковый код (`INVALID_SESSION`, `ACCESS_DENIED`, `SETTINGS_NOT_FOUND`, `BUTTON_INACTIVE`, `INTERNAL_ERROR`);
       - `error.message` — локализованное человеко‑читаемое сообщение.
   - Обеспечить, чтобы технические подробности (исключения, SQL, stack trace) не попадали в JSON‑ответы.

5. **Проверки безопасности**
   - Подтвердить, что:
     - для каждого action выполняется `check_bitrix_sessid()`;
     - проверка прав внедрена через CRM‑классы (`CCrmPerms`/`CCrmAuthorizationHelper` или современные аналоги);
     - при отсутствии прав возвращается ошибка `ACCESS_DENIED`.

6. **Интеграция с фронтендом**
   - Согласовать формат запросов/ответов с JS‑слоем (см. `../ui_ux/crm_card_button.md`):
     - убедиться, что frontend отправляет `entityId`, `elementId`, `fieldId`, `sessid`;
     - убедиться, что frontend ожидает `success`/`error` и данные конфигурации в `data`.

7. **Локализация сообщений**
   - Вынести все сообщения контроллера в языковые файлы:
     - тексты ошибок (`INVALID_SESSION`, `ACCESS_DENIED` и др.);
     - общие сообщения (если используются).

## API-методы

В соответствии с `../api/ajax_controller.md`:

- `getConfigAction(entityId, elementId, fieldId)`:
  - назначение — вернуть конфигурацию SidePanel;
  - входные параметры — тип сущности, ID элемента, ID поля;
  - результат — JSON с полями `success`, `data` или `error`.
- `logClickAction(fieldId, entityId, elementId, status, message = null)` (расширение):
  - назначение — зафиксировать факт нажатия и результат;
  - используется совместно с таблицей `my_bpbutton_logs`.

## Технические требования

- Контроллер реализован на базе `Bitrix\Main\Engine\Controller`.
- Все action‑методы:
  - проверяют сессию (`check_bitrix_sessid()`);
  - проверяют права пользователя на работу с указанной сущностью;
  - используют `ButtonService` для бизнес‑логики (никаких прямых SQL в контроллере).
- Ответы:
  - в едином формате JSON;
  - без утечки технических подробностей.
- Код соответствует стандартам PSR‑12 и принятым практикам Bitrix D7.

## Критерии приёмки

- [ ] Реализован класс `ButtonController` с action‑методом `getConfigAction`.
- [ ] При корректном запросе с валидной сессией и правами:
  - контроллер возвращает `success: true` и объект `data` с полями `url`, `title`, `width`, `context`.
- [ ] При отсутствии настроек (`my_bpbutton_settings`) контроллер возвращает:
  - `success: false` и `error.code = 'SETTINGS_NOT_FOUND'`.
- [ ] При неактивной кнопке (`ACTIVE = 'N'`) возвращается ошибка `BUTTON_INACTIVE`.
- [ ] При отсутствии прав на элемент CRM возвращается ошибка `ACCESS_DENIED`.
- [ ] При битой сессии (`check_bitrix_sessid` возвращает `false`) — ошибка `INVALID_SESSION`.
- [ ] Все сообщения об ошибках локализованы, тексты не содержат технических подробностей.
- [ ] Форматы запросов/ответов соответствуют спецификации в `../api/ajax_controller.md` и ожидаемому поведению фронтенда.

## Тестирование

- **Позитивные сценарии:**
  - нажать кнопку `bp_button_field` в карточке с корректными настройками:
    - проверить, что запрашивается `getConfigAction` с нужными параметрами;
    - убедиться, что SidePanel открывается с правильным URL/заголовком/шириной.
- **Негативные сценарии:**
  - запрос без валидной сессии (отсутствует/битый `sessid`) → `INVALID_SESSION`;
  - пользователь без прав на сущность CRM → `ACCESS_DENIED`;
  - `fieldId`, для которого нет записи в `my_bpbutton_settings` → `SETTINGS_NOT_FOUND`;
  - кнопка деактивирована (`ACTIVE = 'N'`) → `BUTTON_INACTIVE`.
- **Технические проверки:**
  - убедиться, что при ошибках не утечёт SQL/stack trace в ответ;
  - проверить логирование ошибок на стороне сервера (по договорённости с `TASK-004-logging`).

## История правок

- 2026-03-16: Черновое описание задачи.
- 2026-03-16: Задача детализирована и синхронизирована с `api/ajax_controller.md`.

