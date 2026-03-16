<?php

declare(strict_types=1);

namespace My\BpButton;

use Bitrix\Main\Loader;
use My\BpButton\Helper\SecurityHelper;
use My\BpButton\Internals\SettingsTable;
use My\BpButton\UserField\BpButtonUserType;

class EventHandler
{
    /**
     * Регистрация пользовательского типа через OnUserTypeBuildList.
     *
     * Bitrix24 ожидает массив с описанием типа поля напрямую (не массив массивов).
     * Метод ExecuteModuleEventEx вызывает обработчик и ожидает массив с ключом USER_TYPE_ID.
     *
     * @return array
     */
    public static function onUserTypeBuildList(): array
    {
        // Логируем вызов обработчика
        if (function_exists('AddMessage2Log')) {
            AddMessage2Log('EventHandler::onUserTypeBuildList called', 'my.bpbutton');
        }

        if (!Loader::includeModule('my.bpbutton')) {
            if (function_exists('AddMessage2Log')) {
                AddMessage2Log('Module my.bpbutton not loaded in onUserTypeBuildList', 'my.bpbutton');
            }
            return [];
        }

        try {
            // Возвращаем описание типа поля напрямую
            $description = BpButtonUserType::getUserTypeDescription();
            
            if (function_exists('AddMessage2Log')) {
                AddMessage2Log('Returning user type description: ' . json_encode([
                    'USER_TYPE_ID' => $description['USER_TYPE_ID'] ?? 'N/A',
                    'CLASS_NAME' => $description['CLASS_NAME'] ?? 'N/A',
                    'RENDER_COMPONENT' => $description['RENDER_COMPONENT'] ?? 'N/A'
                ]), 'my.bpbutton');
            }
            
            return $description;
        } catch (\Throwable $e) {
            SecurityHelper::safeLog($e, 'my.bpbutton', 'EventHandler::onUserTypeBuildList');
            if (function_exists('AddMessage2Log')) {
                AddMessage2Log('Error in onUserTypeBuildList: ' . $e->getMessage(), 'my.bpbutton');
            }
            return [];
        }
    }

    /**
     * Обработка создания пользовательского поля.
     *
     * Обработчик события ядра - должен быть минимальным и не ломать работу ядра при ошибках.
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

        try {
            $fieldId = (int)$field['ID'];

            // Создаём дефолтную запись настроек для поля.
            $addResult = SettingsTable::add([
                'FIELD_ID'   => $fieldId,
                'ENTITY_ID'  => (string)($field['ENTITY_ID'] ?? null),
                'HANDLER_URL'=> null,
                'TITLE'      => null,
                'WIDTH'      => null,
                'ACTIVE'     => 'Y',
                'CREATED_AT' => new \Bitrix\Main\Type\DateTime(),
                'UPDATED_AT' => new \Bitrix\Main\Type\DateTime(),
            ]);

            // Если не удалось создать запись - логируем, но не прерываем работу ядра
            if (!$addResult->isSuccess()) {
                SecurityHelper::safeLog(
                    'Failed to create settings for field ID: ' . $fieldId . '. Errors: ' . implode(', ', $addResult->getErrorMessages()),
                    'my.bpbutton',
                    'EventHandler::onAfterUserFieldAdd'
                );
            }
        } catch (\Throwable $e) {
            // Ошибки в обработчике событий не должны ломать работу ядра
            SecurityHelper::safeLog($e, 'my.bpbutton', 'EventHandler::onAfterUserFieldAdd');
        }
    }

    /**
     * Очистка настроек при удалении пользовательского поля.
     *
     * Обработчик события ядра - должен быть минимальным и не ломать работу ядра при ошибках.
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

        try {
            $fieldId = (int)$field['ID'];

            $settingsRow = SettingsTable::getList([
                'select' => ['ID'],
                'filter' => ['=FIELD_ID' => $fieldId],
                'limit'  => 1,
            ])->fetch();

            if ($settingsRow && isset($settingsRow['ID'])) {
                $deleteResult = SettingsTable::delete((int)$settingsRow['ID']);
                
                // Если не удалось удалить запись - логируем, но не прерываем работу ядра
                if (!$deleteResult->isSuccess()) {
                    SecurityHelper::safeLog(
                        'Failed to delete settings for field ID: ' . $fieldId . '. Errors: ' . implode(', ', $deleteResult->getErrorMessages()),
                        'my.bpbutton',
                        'EventHandler::onUserFieldDelete'
                    );
                }
            }
        } catch (\Throwable $e) {
            // Ошибки в обработчике событий не должны ломать работу ядра
            SecurityHelper::safeLog($e, 'my.bpbutton', 'EventHandler::onUserFieldDelete');
        }
    }
}

