<?php

declare(strict_types=1);

namespace My\BpButton\Internals;

use Bitrix\Main\Entity;
use Bitrix\Main\Type\DateTime;

final class LogsTable extends Entity\DataManager
{
    public static function getTableName()
    {
        return 'my_bpbutton_logs';
    }

    public static function getMap()
    {
        return [
            new Entity\IntegerField('ID', [
                'primary' => true,
                'autocomplete' => true,
            ]),

            new Entity\IntegerField('SETTINGS_ID', [
                'required' => true,
            ]),

            new Entity\IntegerField('FIELD_ID', [
                'required' => true,
            ]),

            new Entity\StringField('ENTITY_ID', [
                'required' => true,
                'validation' => static function () {
                    return [
                        new Entity\Validator\Length(null, 50),
                    ];
                },
            ]),

            new Entity\IntegerField('ELEMENT_ID', [
                'required' => true,
            ]),

            new Entity\IntegerField('USER_ID', [
                'required' => true,
            ]),

            new Entity\StringField('STATUS', [
                'required' => true,
                'validation' => static function () {
                    return [
                        new Entity\Validator\Length(null, 50),
                    ];
                },
            ]),

            new Entity\TextField('MESSAGE'),

            new Entity\DatetimeField('CREATED_AT', [
                'default_value' => static fn () => new DateTime(),
            ]),
        ];
    }
}

