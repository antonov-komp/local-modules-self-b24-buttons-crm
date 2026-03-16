<?php

declare(strict_types=1);

namespace My\BpButton\Internals;

use Bitrix\Main\Entity;

class SettingsTable extends Entity\DataManager
{
    public static function getTableName()
    {
        return 'my_bpbutton_settings';
    }

    public static function getMap()
    {
        return [
            new Entity\IntegerField('ID', [
                'primary'      => true,
                'autocomplete' => true,
            ]),

            new Entity\IntegerField('FIELD_ID', [
                'required' => true,
            ]),

            new Entity\StringField('ENTITY_ID', [
                'validation' => static function () {
                    return [
                        new Entity\Validator\Length(null, 50),
                    ];
                },
            ]),

            new Entity\StringField('HANDLER_URL', [
                'validation' => static function () {
                    return [
                        new Entity\Validator\Length(null, 500),
                    ];
                },
            ]),

            new Entity\StringField('TITLE', [
                'validation' => static function () {
                    return [
                        new Entity\Validator\Length(null, 255),
                    ];
                },
            ]),

            new Entity\StringField('BUTTON_TEXT', [
                'validation' => static function () {
                    return [
                        new Entity\Validator\Length(null, 255),
                    ];
                },
            ]),

            new Entity\StringField('WIDTH', [
                'validation' => static function () {
                    return [
                        new Entity\Validator\Length(null, 50),
                    ];
                },
            ]),

            new Entity\BooleanField('ACTIVE', [
                'values'  => ['N', 'Y'],
                'default' => 'Y',
            ]),

            new Entity\DatetimeField('CREATED_AT', [
                'default_value' => new \Bitrix\Main\Type\DateTime(),
            ]),

            new Entity\DatetimeField('UPDATED_AT', [
                'default_value' => new \Bitrix\Main\Type\DateTime(),
            ]),
        ];
    }
}

