<?php

declare(strict_types=1);

namespace My\BpButton;

use Bitrix\Main\Loader;
use My\BpButton\Internals\SettingsTable;
use My\BpButton\UserField\BpButtonUserType;

class EventHandler
{
    /**
     * Регистрация пользовательского типа через OnUserTypeBuildList.
     *
     * @return array
     */
    public static function onUserTypeBuildList(): array
    {
        Loader::includeModule('my.bpbutton');

        return BpButtonUserType::getUserTypeDescription();
    }

    /**
     * Обработка создания пользовательского поля.
     *
     * @param array $field
     *
     * @return void
     */
    public static function onAfterUserFieldAdd(array $field): void
    {
        if (
            empty($field['ID'])
            || ($field['USER_TYPE_ID'] ?? null) !== BpButtonUserType::USER_TYPE_ID
        ) {
            return;
        }

        if (!Loader::includeModule('my.bpbutton')) {
            return;
        }

        $fieldId = (int)$field['ID'];

        // Создаём дефолтную запись настроек для поля.
        SettingsTable::add([
            'FIELD_ID'   => $fieldId,
            'ENTITY_ID'  => (string)($field['ENTITY_ID'] ?? null),
            'HANDLER_URL'=> null,
            'TITLE'      => null,
            'WIDTH'      => null,
            'ACTIVE'     => 'Y',
            'CREATED_AT' => new \Bitrix\Main\Type\DateTime(),
            'UPDATED_AT' => new \Bitrix\Main\Type\DateTime(),
        ]);
    }

    /**
     * Очистка настроек при удалении пользовательского поля.
     *
     * @param array $field
     *
     * @return void
     */
    public static function onUserFieldDelete(array $field): void
    {
        if (
            empty($field['ID'])
            || ($field['USER_TYPE_ID'] ?? null) !== BpButtonUserType::USER_TYPE_ID
        ) {
            return;
        }

        if (!Loader::includeModule('my.bpbutton')) {
            return;
        }

        $fieldId = (int)$field['ID'];

        $settingsRow = SettingsTable::getList([
            'select' => ['ID'],
            'filter' => ['=FIELD_ID' => $fieldId],
            'limit'  => 1,
        ])->fetch();

        if ($settingsRow && isset($settingsRow['ID'])) {
            SettingsTable::delete((int)$settingsRow['ID']);
        }
    }
}

