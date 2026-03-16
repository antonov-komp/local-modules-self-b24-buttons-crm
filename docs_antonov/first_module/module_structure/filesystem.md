## Файловая структура модуля «BP Button Field» в `/local/modules`

Целевая структура модуля в коробочной версии Битрикс24:

```text
/local/modules/my.bpbutton/
├── install/
│   ├── index.php             # Логика установки/удаления модуля
│   ├── version.php           # Версия модуля
│   └── step.php              # Мастер установки (при необходимости)
├── lib/
│   ├── Internals/
│   │   └── SettingsTable.php # D7 ORM-сущность my_bpbutton_settings
│   ├── Controller/
│   │   └── ButtonController.php  # Bitrix\Main\Engine\Controller для AJAX-запросов
│   ├── Service/
│   │   └── ButtonService.php     # Бизнес-логика работы кнопок
│   └── EventHandler.php          # Регистрация и обработка событий (OnUserTypeBuildList и др.)
├── lang/
│   └── ru/
│       ├── install/index.php
│       ├── lib/…                # Языковые файлы для классов
│       └── options.php
├── options.php                   # Настройки модуля (если требуются глобальные опции)
└── admin/
    ├── bpbutton_list.php         # Административный реестр полей «Кнопки БП»
    └── menu.php                  # Пункт меню «Настройки → Мои модули → Кнопки БП»
```

Дальнейшие секции этого файла будут описывать назначение каждого файла и его связь с требованиями из `local_button.md`.

