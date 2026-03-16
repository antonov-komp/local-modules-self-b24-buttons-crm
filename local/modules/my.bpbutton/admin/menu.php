<?php

declare(strict_types=1);

use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

global $APPLICATION;

if ($APPLICATION->GetGroupRight('my.bpbutton') < 'R') {
    return [];
}

return [
    [
        'parent_menu' => 'global_menu_settings',
        'section'     => 'my_bpbutton',
        'sort'        => 100,
        'text'        => Loc::getMessage('MY_BPBUTTON_MENU_TEXT') ?: 'Кнопки БП',
        'title'       => Loc::getMessage('MY_BPBUTTON_MENU_TITLE') ?: 'Управление пользовательскими кнопками в карточках CRM',
        'url'         => 'my_bpbutton_bpbutton_list.php?lang=' . LANGUAGE_ID,
        'icon'        => 'sys_menu_icon',
        'page_icon'   => 'sys_page_icon',
        'items_id'    => 'menu_my_bpbutton',
        'items'       => [],
    ],
];

