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
        // Подключаем стандартные UI‑стили, если доступно.
        if (class_exists(Extension::class)) {
            Extension::load('ui.buttons');
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
}

