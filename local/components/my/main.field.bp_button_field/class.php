<?php

if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) {
    die();
}

use Bitrix\Main\Component\BaseUfComponent;
use Bitrix\Main\Loader;

/**
 * Компонент для рендеринга пользовательского поля типа bp_button_field
 */
class MainFieldBpButtonFieldComponent extends BaseUfComponent
{
    protected static function getUserTypeId(): string
    {
        return 'bp_button_field';
    }
}
