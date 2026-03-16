<?php

declare(strict_types=1);

namespace My\BpButton\UserField;

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\UI\Extension;
use Bitrix\Main\UserTable;

Loc::loadMessages(__FILE__);

class BpButtonUserType
{
    public const USER_TYPE_ID = 'bp_button_field';

    /**
     * Описание пользовательского типа поля.
     *
     * @return array
     */
    public static function getUserTypeDescription(): array
    {
        return [
            'USER_TYPE_ID'  => self::USER_TYPE_ID,
            'CLASS_NAME'    => static::class,
            'DESCRIPTION'   => Loc::getMessage('BPBUTTON_USER_TYPE_NAME'),
            'BASE_TYPE'     => 'string',
            'GetDBColumnType' => [static::class, 'getDBColumnType'],
            'GetPublicViewHTML' => [static::class, 'getPublicViewHTML'],
            // В CRM-карточке поле часто рендерится в "public edit" режиме, поэтому нужен отдельный callback.
            'GetPublicEditHTML' => [static::class, 'getPublicEditHTML'],
            'GetPublicTextHTML' => [static::class, 'getPublicTextHTML'],
            'GetPublicViewHTMLMulty' => [static::class, 'getPublicViewHTMLMulty'],
            'GetPublicEditHTMLMulty' => [static::class, 'getPublicEditHTMLMulty'],
            'GetAdminListViewHTML' => [static::class, 'getAdminListViewHTML'],
            'GetEditFormHTML' => [static::class, 'getEditFormHTML'],
        ];
    }

    /**
     * Тип колонки в БД для хранения служебного значения.
     *
     * @return string
     */
    public static function getDBColumnType(): string
    {
        // Для хранения служебных данных достаточно строки.
        return 'varchar(255)';
    }

    /**
     * HTML в административном списке (минимальная реализация).
     *
     * @param array       $field
     * @param array|null  $value
     * @param array       $row
     * @param array       $additional
     *
     * @return string
     */
    public static function getAdminListViewHTML(array $field, ?array $value, array $row, array $additional): string
    {
        $display = isset($value['VALUE']) ? (string)$value['VALUE'] : '';

        return htmlspecialcharsbx($display);
    }

    /**
     * HTML элемента при редактировании записи (админка / форма настроек поля).
     *
     * Для первой версии поле может оставаться текстовым.
     *
     * @param array       $field
     * @param array|null  $value
     * @param array       $additional
     *
     * @return string
     */
    public static function getEditFormHTML(array $field, ?array $value, array $additional): string
    {
        $htmlName = htmlspecialcharsbx($additional['NAME'] ?? $field['FIELD_NAME']);
        $displayValue = isset($value['VALUE']) ? (string)$value['VALUE'] : '';

        return sprintf(
            '<input type="text" name="%s" value="%s" size="20" />',
            $htmlName,
            htmlspecialcharsbx($displayValue)
        );
    }

    /**
     * Публичное представление поля в карточке CRM — кнопка Bitrix UI.
     *
     * @param array       $field
     * @param array|null  $value
     * @param array       $additional
     *
     * @return string
     */
    public static function getPublicViewHTML(array $field, ?array $value, array $additional): string
    {
        // Подключаем стандартные UI‑стили и JS-логику кнопки только там, где реально отрисовано поле.
        if (class_exists(Extension::class)) {
            Extension::load('ui.buttons');
            Extension::load('my_bpbutton.button');
        }

        $buttonText = Loc::getMessage('BPBUTTON_USER_TYPE_BUTTON_TEXT')
            ?: Loc::getMessage('BPBUTTON_USER_TYPE_NAME');

        $entityId = (string)($additional['ENTITY_ID'] ?? '');
        $elementId = (string)($additional['ELEMENT_ID'] ?? '');
        $fieldId = (string)($field['ID'] ?? '');
        $userId = '';

        if (is_array($additional['USER']) && isset($additional['USER']['ID'])) {
            $userId = (string)$additional['USER']['ID'];
        } elseif (isset($GLOBALS['USER']) && $GLOBALS['USER'] instanceof \CUser) {
            $userId = (string)$GLOBALS['USER']->GetID();
        } else {
            $user = UserTable::getList([
                'select' => ['ID'],
                'limit' => 1,
            ])->fetch();

            if ($user && isset($user['ID'])) {
                $userId = (string)$user['ID'];
            }
        }

        $attributes = [
            'type="button"',
            'class="ui-btn ui-btn-primary js-bpbutton-field"',
            'data-entity-id="' . htmlspecialcharsbx($entityId) . '"',
            'data-element-id="' . htmlspecialcharsbx($elementId) . '"',
            'data-field-id="' . htmlspecialcharsbx($fieldId) . '"',
            'data-user-id="' . htmlspecialcharsbx($userId) . '"',
        ];

        return sprintf(
            '<button %s>%s</button>',
            implode(' ', $attributes),
            htmlspecialcharsbx($buttonText)
        );
    }

    /**
     * Публичное представление в режиме редактирования (CRM карточка).
     *
     * Для MVP используем тот же UI, что и во "view": кнопка открывает SidePanel.
     */
    public static function getPublicEditHTML(array $field, ?array $value, array $additional): string
    {
        return static::getPublicViewHTML($field, $value, $additional);
    }

    /**
     * Текстовое представление (например, для некоторых списков/экспорта).
     */
    public static function getPublicTextHTML(array $field, ?array $value, array $additional): string
    {
        return htmlspecialcharsbx(Loc::getMessage('BPBUTTON_USER_TYPE_BUTTON_TEXT') ?: (Loc::getMessage('BPBUTTON_USER_TYPE_NAME') ?: ''));
    }

    /**
     * Multy-варианты: у нас поле логически одиночное, поэтому рендерим как одиночное.
     */
    public static function getPublicViewHTMLMulty(array $field, ?array $value, array $additional): string
    {
        return static::getPublicViewHTML($field, $value, $additional);
    }

    public static function getPublicEditHTMLMulty(array $field, ?array $value, array $additional): string
    {
        return static::getPublicEditHTML($field, $value, $additional);
    }
}

