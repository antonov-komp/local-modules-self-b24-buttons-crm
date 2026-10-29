# Последняя задача модуля

**Определение последней задачи:** по максимальному номеру файла формата `TASK-###`.

## Актуальная последняя задача

- **Файл:** `TASK-017-stabilization-baseline.md`
- **Полный путь:** `docs_antonov/first_module/tasks/TASK-017-stabilization-baseline.md`
- **Дата создания:** `2026-10-29`
- **Статус:** `Новая`

## Кратко о задаче

TASK-017 фиксирует этап восстановления и стабилизации модуля после baseline `28f73db`.

На этом этапе новые action type не добавляются. Сначала выполняется regression/smoke четырёх существующих режимов (`url`, `bp_launch`, `bp_launch_with_params`, `bp_launch_with_button_params`), устраняются очевидные дефекты, проверяются логирование и cache semantics, а также фиксируется стратегия переноса миграций БД из runtime.

## Связанные документы

- `docs_antonov/first_module/restart-2026-10-29.md` — контрольная точка проекта и roadmap.
- `TASK-016-bp-launch-with-buttons-param-choice.md` — предыдущая функциональная задача; реализация уже находится в `main`, требуется regression/acceptance в рамках TASK-017.
