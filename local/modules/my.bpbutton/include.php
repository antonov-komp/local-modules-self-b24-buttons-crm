<?php

declare(strict_types=1);

use Bitrix\Main\Loader;

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    // На случай прямого вызова — модульный include.php должен подключаться через Loader::includeModule().
}

// Регистрация JS-расширения модуля.
// Файл подключается только на страницах, где user type явно вызывает Extension::load('my_bpbutton.button').
\CJSCore::RegisterExt('my_bpbutton.button', [
    'js' => '/local/modules/my.bpbutton/install/js/my.bpbutton/button.js',
    'rel' => ['main.core', 'ui.buttons', 'ui.sidepanel', 'ui.notification'],
    'lang' => '/local/modules/my.bpbutton/lang/' . LANGUAGE_ID . '/install/js/my.bpbutton/button.php',
]);

// Регистрация JS-расширения для админ-списка.
\CJSCore::RegisterExt('my_bpbutton.admin_list', [
    'js' => '/local/modules/my.bpbutton/install/js/my.bpbutton/admin.list.js',
    'rel' => ['main.core', 'ui.notification', 'ui.sidepanel'],
    'lang' => '/local/modules/my.bpbutton/lang/' . LANGUAGE_ID . '/install/js/my.bpbutton/admin.list.php',
]);

// Сервисный include для совместимости: в ряде окружений Loader::includeModule() может ожидать true.
return Loader::includeModule('main');

