# Чек-лист онбординга: модуль my.bpbutton

**Дата создания:** 2026-03-16  
**Связь:** TASK-REF-005

---

## 1. Окружение

- [ ] Установлена коробочная версия Bitrix24
- [ ] Модуль my.bpbutton установлен в `/local/modules/`
- [ ] Доступ к админке (права на модуль)

---

## 2. Понимание структуры

- [ ] Прочитан README модуля (`local/modules/my.bpbutton/README.md`)
- [ ] Изучена документация `docs_antonov/first_module/`
- [ ] Просмотрена архитектурная схема (`architecture/layers-diagram.md`)

---

## 3. Ключевые файлы

- [ ] **BpButtonUserType** — описание типа поля, делегирует в ButtonHtmlRenderer
- [ ] **ButtonHtmlRenderer** — рендеринг HTML кнопки
- [ ] **ButtonService** — конфигурация SidePanel, логирование
- [ ] **ButtonController** — AJAX API (getConfigAction)
- [ ] **SettingsFormService** — форма админки, toggleActive
- [ ] **SettingsResolver** — настройки отображения (BUTTON_TEXT, BUTTON_SIZE)

---

## 4. Первый запуск

- [ ] Создан тестовый лид (или сделка) с полем типа «Кнопка управления логикой»
- [ ] Настроена кнопка в админке («Настройки → Мои модули → Кнопки БП»): URL, заголовок, текст кнопки
- [ ] Клик по кнопке открывает SidePanel

---

## 5. Разработка

- [ ] Проверены namespaces (`My\BpButton\*`)
- [ ] Проверены пути к файлам (`install/...`, `lib/...`)
- [ ] При необходимости — создан TASK-файл по шаблону из `docs_antonov/`

---

## 6. Ссылки

| Документ | Путь |
|----------|------|
| README модуля | `local/modules/my.bpbutton/README.md` |
| Обзор | `docs_antonov/first_module/overview.md` |
| Архитектура backend | `docs_antonov/first_module/architecture/backend_d7.md` |
| Схема слоёв | `docs_antonov/first_module/architecture/layers-diagram.md` |
| Формат API | `docs_antonov/first_module/api/response_format.md` |
| Файловая структура | `docs_antonov/first_module/module_structure/filesystem.md` |
