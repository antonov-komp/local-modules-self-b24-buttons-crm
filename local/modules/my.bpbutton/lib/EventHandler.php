<?php

declare(strict_types=1);

namespace My\BpButton;

use Bitrix\Main\Loader;
use Bitrix\Main\UI\Extension;
use My\BpButton\Helper\SecurityHelper;
use My\BpButton\Internals\SettingsTable;
use My\BpButton\UserField\BpButtonUserType;

class EventHandler
{
    /**
     * Миграция: добавление колонки BUTTON_TEXT в my_bpbutton_settings для существующих установок.
     */
    private static function ensureButtonTextColumnExists(): void
    {
        try {
            $connection = \Bitrix\Main\Application::getConnection();
            $tableName = 'my_bpbutton_settings';
            if (!$connection->isTableExists($tableName)) {
                return;
            }
            $result = $connection->query("SHOW COLUMNS FROM `{$tableName}` LIKE 'BUTTON_TEXT'");
            if (!$result->fetch()) {
                $connection->queryExecute(
                    'ALTER TABLE `' . $tableName . '` ADD COLUMN `BUTTON_TEXT` VARCHAR(255) NULL AFTER `TITLE`'
                );
            }
        } catch (\Throwable $e) {
            // Игнорируем ошибки миграции
        }
    }

    /**
     * Миграция: добавление колонки BUTTON_SIZE в my_bpbutton_settings.
     */
    private static function ensureButtonSizeColumnExists(): void
    {
        try {
            $connection = \Bitrix\Main\Application::getConnection();
            $tableName = 'my_bpbutton_settings';
            if (!$connection->isTableExists($tableName)) {
                return;
            }
            $result = $connection->query("SHOW COLUMNS FROM `{$tableName}` LIKE 'BUTTON_SIZE'");
            if (!$result->fetch()) {
                $connection->queryExecute(
                    'ALTER TABLE `' . $tableName . '` ADD COLUMN `BUTTON_SIZE` VARCHAR(20) NULL AFTER `WIDTH`'
                );
            }
        } catch (\Throwable $e) {
            // Игнорируем ошибки миграции
        }
    }

    /**
     * Подключение JS для Entity Editor на страницах CRM.
     * Обеспечивает отображение кнопки bp_button_field сразу в режиме просмотра.
     */
    public static function onMainProlog(): void
    {
        if (!Loader::includeModule('my.bpbutton')) {
            return;
        }

        // Миграция: добавление колонки BUTTON_TEXT (один раз при первом обращении)
        self::ensureButtonTextColumnExists();
        self::ensureButtonSizeColumnExists();
        // Подключаем на страницах CRM, админки и настройки полей (config, userfield)
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        $isCrm = stripos($requestUri, '/crm/') !== false || stripos($requestUri, 'crm.') !== false;
        $isAdmin = stripos($requestUri, '/bitrix/admin/') !== false;
        $isFieldConfig = stripos($requestUri, 'userfield') !== false
            || stripos($requestUri, 'field.config') !== false
            || stripos($requestUri, 'main.field.config') !== false;
        if (!$isCrm && !$isAdmin && !$isFieldConfig) {
            return;
        }

        try {
            Extension::load('my_bpbutton.entity_editor');
        } catch (\Throwable $e) {
            SecurityHelper::safeLog($e, 'my.bpbutton', 'EventHandler::onMainProlog');
        }
    }

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
        if (!Loader::includeModule('my.bpbutton')) {
            return [];
        }

        try {
            return BpButtonUserType::getUserTypeDescription();
        } catch (\Throwable $e) {
            SecurityHelper::safeLog($e, 'my.bpbutton', 'EventHandler::onUserTypeBuildList');
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
                'FIELD_ID'    => $fieldId,
                'ENTITY_ID'   => (string)($field['ENTITY_ID'] ?? null),
                'HANDLER_URL' => null,
                'TITLE'       => null,
                'BUTTON_TEXT' => null,
                'WIDTH'       => null,
                'BUTTON_SIZE' => null,
                'ACTIVE'      => 'Y',
                'CREATED_AT'  => new \Bitrix\Main\Type\DateTime(),
                'UPDATED_AT'  => new \Bitrix\Main\Type\DateTime(),
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

