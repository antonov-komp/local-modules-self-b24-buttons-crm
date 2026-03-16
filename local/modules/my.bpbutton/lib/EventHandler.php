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
    /** Отладка HIDE_BP_TAB: true = логи в консоль, false = выкл */
    private const DEBUG_BP_TAB = false;

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
     * Миграция TASK-014: добавление колонок ACTION_TYPE и BP_TEMPLATE_ID.
     */
    private static function ensureBpLaunchColumnsExist(): void
    {
        try {
            $connection = \Bitrix\Main\Application::getConnection();
            $tableName = 'my_bpbutton_settings';
            if (!$connection->isTableExists($tableName)) {
                return;
            }
            $result = $connection->query("SHOW COLUMNS FROM `{$tableName}` LIKE 'ACTION_TYPE'");
            if (!$result->fetch()) {
                $connection->queryExecute(
                    'ALTER TABLE `' . $tableName . '` ADD COLUMN `ACTION_TYPE` VARCHAR(20) NULL DEFAULT \'url\' AFTER `BUTTON_SIZE`'
                );
            }
            $result = $connection->query("SHOW COLUMNS FROM `{$tableName}` LIKE 'BP_TEMPLATE_ID'");
            if (!$result->fetch()) {
                $connection->queryExecute(
                    'ALTER TABLE `' . $tableName . '` ADD COLUMN `BP_TEMPLATE_ID` INT UNSIGNED NULL AFTER `ACTION_TYPE`'
                );
            }
        } catch (\Throwable $e) {
            // Игнорируем ошибки миграции
        }
    }

    /**
     * Миграция TASK-014-A: добавление колонки HIDE_BP_TAB.
     */
    private static function ensureHideBpTabColumnExists(): void
    {
        try {
            $connection = \Bitrix\Main\Application::getConnection();
            $tableName = 'my_bpbutton_settings';
            if (!$connection->isTableExists($tableName)) {
                return;
            }
            $result = $connection->query("SHOW COLUMNS FROM `{$tableName}` LIKE 'HIDE_BP_TAB'");
            if (!$result->fetch()) {
                $connection->queryExecute(
                    'ALTER TABLE `' . $tableName . '` ADD COLUMN `HIDE_BP_TAB` CHAR(1) NOT NULL DEFAULT \'N\' AFTER `BP_TEMPLATE_ID`'
                );
            }
        } catch (\Throwable $e) {
            // Игнорируем ошибки миграции
        }
    }

    /**
     * Определить ENTITY_ID (пространство полей) по URL страницы CRM.
     * Для смарт-процессов использует CCrmOwnerType::ResolveUserFieldEntityID — возвращает
     * реальный ENTITY_ID полей (например CRM_4), а не CRM_DYNAMIC_1038.
     *
     * @param string $requestUri REQUEST_URI
     * @return string|null CRM_LEAD, CRM_4 и т.д. или null
     */
    private static function resolveEntityIdFromCrmUrl(string $requestUri): ?string
    {
        // Учитываем /site_vl/, отсутствие trailing slash, category в пути
        if (preg_match('#/crm/lead/(details|list)(/|$|\?|#)#i', $requestUri)) {
            return 'CRM_LEAD';
        }
        if (preg_match('#/crm/deal/(details|list)(/|$|\?|#)#i', $requestUri)) {
            return 'CRM_DEAL';
        }
        if (preg_match('#/crm/contact/(details|list)(/|$|\?|#)#i', $requestUri)) {
            return 'CRM_CONTACT';
        }
        if (preg_match('#/crm/company/(details|list)(/|$|\?|#)#i', $requestUri)) {
            return 'CRM_COMPANY';
        }
        // Смарт-процессы: /crm/type/1038/, /crm/type/1038/0/details/1/, /crm/type/1038/list/
        if (preg_match('#/crm/type/(\d+)(/[^/]+)?(/(details|list))?(/|$|\?|#)#i', $requestUri, $m)) {
            $entityTypeId = (int)$m[1];
            if ($entityTypeId > 0 && Loader::includeModule('crm') && class_exists('\CCrmOwnerType')) {
                $ufEntityId = \CCrmOwnerType::ResolveUserFieldEntityID($entityTypeId);
                return $ufEntityId !== '' ? $ufEntityId : null;
            }
            return 'CRM_DYNAMIC_' . $entityTypeId;
        }
        return null;
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
        self::ensureBpLaunchColumnsExist();
        self::ensureHideBpTabColumnExists();
        // Подключаем на страницах карточек CRM (details), админки и настройки полей
        // НЕ на списках — иначе ui.entity-editor грузится глобально и может ломать CRM
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        $hasCrm = stripos($requestUri, '/crm/') !== false || stripos($requestUri, 'crm.') !== false;
        $isCrmDetails = $hasCrm && (
            stripos($requestUri, '/details') !== false
            || preg_match('#/crm/(?:lead|deal|contact|company|type/\d+)/\d+#i', $requestUri)
        );
        $isAdmin = stripos($requestUri, '/bitrix/admin/') !== false;
        $isFieldConfig = stripos($requestUri, 'userfield') !== false
            || stripos($requestUri, 'field.config') !== false
            || stripos($requestUri, 'main.field.config') !== false;
        if (!$isCrmDetails && !$isAdmin && !$isFieldConfig) {
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

            $settings = $field['SETTINGS'] ?? [];
            $actionType = trim((string)($settings['ACTION_TYPE'] ?? ''));
            if ($actionType !== 'url' && $actionType !== 'bp_launch') {
                $actionType = 'url';
            }
            $bpTemplateId = isset($settings['BP_TEMPLATE_ID']) ? (int)$settings['BP_TEMPLATE_ID'] : null;
            if ($bpTemplateId <= 0) {
                $bpTemplateId = null;
            }

            // Создаём дефолтную запись настроек для поля.
            $addResult = SettingsTable::add([
                'FIELD_ID'      => $fieldId,
                'ENTITY_ID'     => (string)($field['ENTITY_ID'] ?? null),
                'HANDLER_URL'   => null,
                'TITLE'         => null,
                'BUTTON_TEXT'   => null,
                'WIDTH'         => null,
                'BUTTON_SIZE'   => null,
                'ACTION_TYPE'   => $actionType,
                'BP_TEMPLATE_ID'=> $bpTemplateId,
                'ACTIVE'        => 'Y',
                'CREATED_AT'    => new \Bitrix\Main\Type\DateTime(),
                'UPDATED_AT'    => new \Bitrix\Main\Type\DateTime(),
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
     * Синхронизация настроек ACTION_TYPE и BP_TEMPLATE_ID при обновлении поля.
     *
     * При сохранении поля через main.field.config.detail данные из SETTINGS
     * синхронизируются в my_bpbutton_settings.
     *
     * @param array $field Обновлённые данные поля (после сохранения)
     */
    public static function onAfterUserFieldUpdate(array $field): void
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
            $settings = $field['SETTINGS'] ?? [];

            $actionType = trim((string)($settings['ACTION_TYPE'] ?? ''));
            if ($actionType !== 'url' && $actionType !== 'bp_launch') {
                $actionType = 'url';
            }

            $bpTemplateId = isset($settings['BP_TEMPLATE_ID']) ? (int)$settings['BP_TEMPLATE_ID'] : null;
            if ($bpTemplateId <= 0) {
                $bpTemplateId = null;
            }

            $settingsRow = SettingsTable::getList([
                'select' => ['ID'],
                'filter' => ['=FIELD_ID' => $fieldId],
                'limit' => 1,
            ])->fetch();

            if ($settingsRow && isset($settingsRow['ID'])) {
                SettingsTable::update((int)$settingsRow['ID'], [
                    'ACTION_TYPE' => $actionType,
                    'BP_TEMPLATE_ID' => $bpTemplateId,
                    'UPDATED_AT' => new \Bitrix\Main\Type\DateTime(),
                ]);
            }
        } catch (\Throwable $e) {
            SecurityHelper::safeLog($e, 'my.bpbutton', 'EventHandler::onAfterUserFieldUpdate');
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

