# TASK-012: Исключение сохранения карточки при клике по кнопке bp_button_field

**Дата создания:** 2026-03-16 (UTC+3, Брест)  
**Статус:** Завершена  
**Приоритет:** Высокий  
**Исполнитель:** Bitrix24 Программист (коробка, Vanilla JS)

## Описание

При клике по кнопке `bp_button_field` в карточке CRM Entity Editor пытается сохранить карточку или переключить поле в режим редактирования. Ожидаемое поведение: клик по кнопке только отрабатывает логику кнопки (открытие SidePanel), без сохранения карточки и без переключения режимов Entity Editor.

## Контекст

Entity Editor (Bitrix24) обрабатывает клики по полям для переключения между режимами просмотра и редактирования. Кнопки, которые должны выполнять собственное действие (а не переключать режим), должны быть помечены атрибутом `data-editor-control-type="button"`. Кнопка `bp_button_field` не имела этого атрибута.

Подробный анализ: `ANALYSIS-011-button-click-save-issue.md`

## Модули и компоненты

- `local/modules/my.bpbutton/lib/UserField/BpButtonUserType.php` — метод `getPublicViewHTML`, формирование атрибутов кнопки

## Зависимости

- Анализ: ANALYSIS-011-button-click-save-issue.md
- Entity Editor: `bitrix/modules/ui/install/js/ui/entity-editor/js/editor-controller.js` (EditorFieldViewController.isHandleableEvent)

## Ступенчатые подзадачи

1. Открыть `BpButtonUserType.php`, метод `getPublicViewHTML`
2. В массив `$attributes` кнопки добавить атрибут `data-editor-control-type="button"`
3. Убедиться, что атрибут попадает в итоговый HTML кнопки
4. Протестировать: клик по кнопке открывает SidePanel, карточка не сохраняется и не переключается в режим редактирования

## Технические требования

- Атрибут должен быть добавлен в том же формате, что и остальные data-атрибуты
- Изменение только в `getPublicViewHTML` (и `getPublicEditHTML` использует тот же метод)

## Реализация

В `BpButtonUserType::getPublicViewHTML` массив `$attributes` (примерно строка 305) дополнить:

```php
$attributes = [
    'type="button"',
    'id="' . htmlspecialcharsbx($buttonId) . '"',
    'class="ui-btn ui-btn-primary js-bpbutton-field"',
    'data-editor-control-type="button"',  // <-- добавить
    'data-entity-id="' . htmlspecialcharsbx($entityId) . '"',
    'data-element-id="' . htmlspecialcharsbx($elementId) . '"',
    'data-field-id="' . htmlspecialcharsbx($fieldId) . '"',
    'data-user-id="' . htmlspecialcharsbx($userId) . '"',
];
```

## Критерии приёмки

- [x] При клике по кнопке `bp_button_field` в карточке CRM открывается SidePanel (если настроено)
- [x] При клике карточка не переключается в режим редактирования
- [x] При клике не инициируется сохранение карточки
- [x] Остальной функционал кнопки (состояния, уведомления) работает как прежде

## Тестирование

1. Открыть карточку CRM (лид, сделка и т.п.) с полем `bp_button_field`
2. Убедиться, что кнопка отображается
3. Кликнуть по кнопке
4. **Ожидание:** открывается SidePanel (или выполняется настроенное действие), карточка остаётся в режиме просмотра, нет запроса на сохранение
5. Проверить в режиме редактирования карточки (если применимо): клик по кнопке не должен переключать поле в single-edit режим

## История правок

- 2026-03-16: Создана задача на основе ANALYSIS-011.
- 2026-03-16: Реализовано — добавлен атрибут `data-editor-control-type="button"` в `BpButtonUserType::getPublicViewHTML`.
