# Единый формат ответов API модуля «BP Button Field»

**Дата создания:** 2026-03-16  
**Связь:** TASK-REF-004, ajax_controller.md

---

## Обзор

Все точки входа API модуля `my.bpbutton`, возвращающие JSON, используют единый формат ответа. Это обеспечивает предсказуемость для клиентов (JS, интеграции) и упрощает обработку ошибок.

**Точки входа:**
- `bitrix/services/my.bpbutton/button/ajax.php` — через `ButtonController::getConfigAction`
- При необработанном исключении в `ajax.php` — тот же формат через `catch (\Throwable)`

---

## Успешный ответ

```json
{
  "success": true,
  "data": {
    "url": "string",
    "title": "string",
    "width": "string|number",
    "context": {
      "settingsId": 0,
      "entityId": "string",
      "elementId": 0,
      "fieldId": 0,
      "userId": 0
    }
  }
}
```

| Поле | Тип | Описание |
|------|-----|----------|
| `success` | boolean | Всегда `true` при успехе |
| `data.url` | string | URL для iframe/SidePanel |
| `data.title` | string | Заголовок окна |
| `data.width` | string \| number | Ширина окна (например, `"70%"` или `800`) |
| `data.context` | object | Контекст для обработчика |

---

## Ответ с ошибкой

```json
{
  "success": false,
  "error": {
    "code": "string",
    "message": "string"
  }
}
```

| Поле | Тип | Описание |
|------|-----|----------|
| `success` | boolean | Всегда `false` при ошибке |
| `error.code` | string | Канонический код ошибки |
| `error.message` | string | Локализованное сообщение для пользователя |

---

## Канонические коды ошибок

| Код | Описание |
|-----|----------|
| `INVALID_SESSION` | Невалидная сессия (sessid) |
| `ACCESS_DENIED` | Нет прав на CRM-сущность |
| `SETTINGS_NOT_FOUND` | Запись настроек не найдена |
| `BUTTON_INACTIVE` | Кнопка отключена (ACTIVE=N) |
| `INTERNAL_ERROR` | Внутренняя ошибка |

---

## Правила

1. **Безопасность:** В `error.message` не передаются stack trace, SQL, внутренние пути. Детали логируются на сервере.
2. **Кодировка:** JSON выводится с флагом `JSON_UNESCAPED_UNICODE` для корректного отображения кириллицы.
3. **Обратная совместимость:** Формат не меняется для существующих клиентов. Добавление новых полей в `data` допустимо.

---

## Связь с другими документами

- `api/ajax_controller.md` — описание контроллера и методов
- `architecture/backend_d7.md` — архитектура backend-слоя
- `architecture/layers-diagram.md` — схема потоков данных
- `../onboarding-checklist.md` — чек-лист для разработчиков
