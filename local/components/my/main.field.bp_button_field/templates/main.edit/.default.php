<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

use Bitrix\Main\Loader;
use My\BpButton\UserField\BpButtonUserType;

/**
 * @var MainFieldBpButtonFieldComponent $component
 * @var array $arResult
 */

$component = $this->getComponent();

// Загружаем модуль
if (!Loader::includeModule('my.bpbutton')) {
    echo '<!-- Module my.bpbutton not loaded -->';
    return;
}

// Получаем данные поля из $arResult
$field = $arResult['userField'] ?? [];
$value = null;

// Обрабатываем значение поля
if (isset($arResult['fieldValues']) && is_array($arResult['fieldValues']) && !empty($arResult['fieldValues'])) {
    $firstValue = reset($arResult['fieldValues']);
    $value = ['VALUE' => $firstValue['attrList']['value'] ?? ''];
} elseif (isset($arResult['value'])) {
    $value = is_array($arResult['value']) ? $arResult['value'] : ['VALUE' => $arResult['value']];
}

$additional = $arResult['additionalParameters'] ?? [];

// Добавляем информацию о пользователе, если доступна
if (isset($GLOBALS['USER']) && $GLOBALS['USER'] instanceof \CUser) {
    $additional['USER'] = ['ID' => $GLOBALS['USER']->GetID()];
}

// Рендерим кнопку через метод класса BpButtonUserType
echo BpButtonUserType::getPublicEditHTML($field, $value, $additional);
