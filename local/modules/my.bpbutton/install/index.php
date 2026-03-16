<?php

declare(strict_types=1);

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Application;
use Bitrix\Main\Loader;
use Bitrix\Main\IO\Directory;
use Bitrix\Main\IO\File;

Loc::loadMessages(__FILE__);

class my_bpbutton extends CModule
{
    public $MODULE_ID = 'my.bpbutton';
    public $MODULE_VERSION = '0.0.1';
    public $MODULE_VERSION_DATE = '2026-03-16 00:00:00';
    public $MODULE_NAME;
    public $MODULE_DESCRIPTION;
    public $PARTNER_NAME;
    public $PARTNER_URI;

    public function __construct()
    {
        $arModuleVersion = [];
        include __DIR__ . '/version.php';

        $this->MODULE_VERSION = $arModuleVersion['VERSION'] ?? '0.0.1';
        $this->MODULE_VERSION_DATE = $arModuleVersion['VERSION_DATE'] ?? '2026-03-16 00:00:00';
        $this->MODULE_NAME = Loc::getMessage('MY_BPBUTTON_INSTALL_NAME') ?: 'BP Button Field';
        $this->MODULE_DESCRIPTION = Loc::getMessage('MY_BPBUTTON_INSTALL_DESCRIPTION') ?: 'Пользовательский тип поля bp_button_field для CRM и смарт‑процессов.';
        $this->PARTNER_NAME = Loc::getMessage('MY_BPBUTTON_INSTALL_PARTNER_NAME') ?: '';
        $this->PARTNER_URI = Loc::getMessage('MY_BPBUTTON_INSTALL_PARTNER_URI') ?: '';
    }

    public function DoInstall()
    {
        Loader::includeModule('main');

        // 1. Регистрация модуля
        RegisterModule($this->MODULE_ID);

        // 2. Создание таблиц БД
        $this->InstallDB();

        // 3. Регистрация обработчиков событий
        $this->InstallEvents();

        // 4. Установка admin-прокси файлов
        $this->InstallFiles();

        // 5. Регистрация JS/CSS расширений
        $this->InstallJS();

        // 6. Регистрация admin-меню
        $this->InstallMenu();

        return true;
    }

    public function DoUninstall()
    {
        global $APPLICATION;

        // Проверка прав доступа
        if (!check_bitrix_sessid()) {
            return false;
        }

        // Опционально: мастер удаления с выбором политики по таблицам
        // По умолчанию: удаляем настройки, оставляем логи (исторические данные)
        $deleteSettings = $_REQUEST['delete_settings'] ?? 'Y';
        $deleteLogs = $_REQUEST['delete_logs'] ?? 'N';

        // 1. Снятие обработчиков событий
        $this->UnInstallEvents();

        // 2. Удаление admin-файлов
        $this->UnInstallFiles();

        // 3. Удаление admin-меню
        $this->UnInstallMenu();

        // 4. Обработка таблиц БД (согласно политике)
        $this->UnInstallDB($deleteSettings === 'Y', $deleteLogs === 'Y');

        // 5. Разрегистрация модуля
        UnRegisterModule($this->MODULE_ID);

        return true;
    }

    /**
     * Создание таблиц БД
     */
    public function InstallDB(): void
    {
        // Регистрация автозагрузчика классов
        Loader::registerAutoLoadClasses($this->MODULE_ID, [
            'My\\BpButton\\UserField\\BpButtonUserType' => 'lib/UserField/BpButtonUserType.php',
            'My\\BpButton\\EventHandler' => 'lib/EventHandler.php',
            'My\\BpButton\\Internals\\SettingsTable' => 'lib/Internals/SettingsTable.php',
            'My\\BpButton\\Internals\\LogsTable' => 'lib/Internals/LogsTable.php',
            'My\\BpButton\\Service\\ButtonService' => 'lib/Service/ButtonService.php',
            'My\\BpButton\\Controller\\ButtonController' => 'lib/Controller/ButtonController.php',
            'My\\BpButton\\Helper\\SecurityHelper' => 'lib/Helper/SecurityHelper.php',
        ]);

        $connection = Application::getConnection();
        $sqlHelper = $connection->getSqlHelper();

        // Таблица настроек
        $tableName = 'my_bpbutton_settings';
        if (!$connection->isTableExists($tableName)) {
            $connection->queryExecute(
                'CREATE TABLE IF NOT EXISTS `' . $tableName . '` (
                    `ID` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `FIELD_ID` INT UNSIGNED NOT NULL,
                    `ENTITY_ID` VARCHAR(50) NULL,
                    `HANDLER_URL` VARCHAR(500) NULL,
                    `TITLE` VARCHAR(255) NULL,
                    `BUTTON_TEXT` VARCHAR(255) NULL,
                    `WIDTH` VARCHAR(50) NULL,
                    `BUTTON_SIZE` VARCHAR(20) NULL,
                    `ACTIVE` CHAR(1) NOT NULL DEFAULT \'Y\',
                    `CREATED_AT` DATETIME NOT NULL,
                    `UPDATED_AT` DATETIME NOT NULL,
                    PRIMARY KEY (`ID`),
                    UNIQUE KEY `UX_BPBTN_FIELD` (`FIELD_ID`),
                    KEY `IX_BPBTN_ENTITY` (`ENTITY_ID`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8;'
            );
        } else {
            // Миграция: добавление колонки BUTTON_TEXT для существующих установок
            $result = $connection->query("SHOW COLUMNS FROM `{$tableName}` LIKE 'BUTTON_TEXT'");
            if (!$result->fetch()) {
                $connection->queryExecute(
                    'ALTER TABLE `' . $tableName . '` ADD COLUMN `BUTTON_TEXT` VARCHAR(255) NULL AFTER `TITLE`'
                );
            }
            // Миграция: добавление колонки BUTTON_SIZE
            $result = $connection->query("SHOW COLUMNS FROM `{$tableName}` LIKE 'BUTTON_SIZE'");
            if (!$result->fetch()) {
                $connection->queryExecute(
                    'ALTER TABLE `' . $tableName . '` ADD COLUMN `BUTTON_SIZE` VARCHAR(20) NULL AFTER `WIDTH`'
                );
            }
        }

        // Таблица логов
        $logsTableName = 'my_bpbutton_logs';
        if (!$connection->isTableExists($logsTableName)) {
            $connection->queryExecute(
                'CREATE TABLE IF NOT EXISTS `' . $logsTableName . '` (
                    `ID` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `SETTINGS_ID` INT UNSIGNED NOT NULL DEFAULT 0,
                    `FIELD_ID` INT UNSIGNED NOT NULL,
                    `ENTITY_ID` VARCHAR(50) NOT NULL,
                    `ELEMENT_ID` INT UNSIGNED NOT NULL,
                    `USER_ID` INT UNSIGNED NOT NULL,
                    `STATUS` VARCHAR(50) NOT NULL,
                    `MESSAGE` TEXT NULL,
                    `CREATED_AT` DATETIME NOT NULL,
                    PRIMARY KEY (`ID`),
                    KEY `IX_BPBTN_LOG_FIELD` (`FIELD_ID`),
                    KEY `IX_BPBTN_LOG_ENTITY` (`ENTITY_ID`, `ELEMENT_ID`),
                    KEY `IX_BPBTN_LOG_USER` (`USER_ID`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8;'
            );
        }
    }

    /**
     * Удаление таблиц БД (с политикой)
     *
     * Политика по умолчанию:
     * - my_bpbutton_settings — удаляется (настройки не критичны для истории)
     * - my_bpbutton_logs — остаётся (исторические данные могут быть ценны для аудита)
     *
     * @param bool $deleteSettings Удалять ли таблицу настроек
     * @param bool $deleteLogs Удалять ли таблицу логов
     */
    public function UnInstallDB(bool $deleteSettings = true, bool $deleteLogs = false): void
    {
        // Автозагрузчик классов удаляется автоматически при удалении модуля через UnRegisterModule()
        // Дополнительных действий не требуется

        $connection = Application::getConnection();

        // Удаление таблицы настроек (по умолчанию удаляем)
        if ($deleteSettings) {
            $tableName = 'my_bpbutton_settings';
            if ($connection->isTableExists($tableName)) {
                $connection->queryExecute('DROP TABLE IF EXISTS `' . $tableName . '`');
            }
        }

        // Удаление таблицы логов (по умолчанию оставляем)
        if ($deleteLogs) {
            $logsTableName = 'my_bpbutton_logs';
            if ($connection->isTableExists($logsTableName)) {
                $connection->queryExecute('DROP TABLE IF EXISTS `' . $logsTableName . '`');
            }
        }
    }

    /**
     * Регистрация обработчиков событий
     */
    public function InstallEvents(): void
    {
        RegisterModuleDependences(
            'main',
            'OnProlog',
            $this->MODULE_ID,
            \My\BpButton\EventHandler::class,
            'onMainProlog'
        );

        RegisterModuleDependences(
            'main',
            'OnUserTypeBuildList',
            $this->MODULE_ID,
            \My\BpButton\EventHandler::class,
            'onUserTypeBuildList'
        );

        RegisterModuleDependences(
            'main',
            'OnAfterUserFieldAdd',
            $this->MODULE_ID,
            \My\BpButton\EventHandler::class,
            'onAfterUserFieldAdd'
        );

        RegisterModuleDependences(
            'main',
            'OnUserFieldDelete',
            $this->MODULE_ID,
            \My\BpButton\EventHandler::class,
            'onUserFieldDelete'
        );
    }

    /**
     * Снятие обработчиков событий
     */
    public function UnInstallEvents(): void
    {
        UnRegisterModuleDependences(
            'main',
            'OnProlog',
            $this->MODULE_ID,
            \My\BpButton\EventHandler::class,
            'onMainProlog'
        );

        UnRegisterModuleDependences(
            'main',
            'OnUserTypeBuildList',
            $this->MODULE_ID,
            \My\BpButton\EventHandler::class,
            'onUserTypeBuildList'
        );

        UnRegisterModuleDependences(
            'main',
            'OnAfterUserFieldAdd',
            $this->MODULE_ID,
            \My\BpButton\EventHandler::class,
            'onAfterUserFieldAdd'
        );

        UnRegisterModuleDependences(
            'main',
            'OnUserFieldDelete',
            $this->MODULE_ID,
            \My\BpButton\EventHandler::class,
            'onUserFieldDelete'
        );
    }

    /**
     * Установка admin-прокси файлов
     */
    public function InstallFiles(): void
    {
        $adminDir = $_SERVER['DOCUMENT_ROOT'] . '/bitrix/admin';
        $installAdminDir = __DIR__ . '/admin';

        // Создаём директорию для прокси, если её нет
        if (!Directory::isDirectoryExists($installAdminDir)) {
            Directory::createDirectory($installAdminDir);
        }

        // Создаём/обновляем прокси для списка настроек
        $proxyListPath = $installAdminDir . '/my_bpbutton_bpbutton_list.php';
        $proxyListContent = "<?php\nrequire_once(\$_SERVER['DOCUMENT_ROOT'] . '/local/modules/my.bpbutton/admin/bpbutton_list.php');\n";
        $proxyListFile = new File($proxyListPath);
        $proxyListFile->putContents($proxyListContent);

        // Копируем прокси в /bitrix/admin/ (всегда обновляем)
        CopyDirFiles($installAdminDir, $adminDir, true, true);
    }

    /**
     * Удаление admin-прокси файлов
     */
    public function UnInstallFiles(): void
    {
        $adminDir = $_SERVER['DOCUMENT_ROOT'] . '/bitrix/admin';

        // Удаляем прокси файлы
        $proxyFiles = [
            'my_bpbutton_bpbutton_list.php',
        ];

        foreach ($proxyFiles as $file) {
            $filePath = $adminDir . '/' . $file;
            if (File::isFileExists($filePath)) {
                File::deleteFile($filePath);
            }
        }
    }

    /**
     * Регистрация JS/CSS расширений
     */
    public function InstallJS(): void
    {
        // Регистрация JS-расширения для кнопки в CRM
        CJSCore::RegisterExt('my_bpbutton.button', [
            'js' => '/local/modules/my.bpbutton/install/js/my.bpbutton/button.js',
            'rel' => ['main.core', 'ui.buttons', 'ui.sidepanel', 'ui.notification'],
            'lang' => '/local/modules/my.bpbutton/lang/' . LANGUAGE_ID . '/install/js/my.bpbutton/button.php',
        ]);

        // Регистрация JS-расширения для админ-списка
        CJSCore::RegisterExt('my_bpbutton.admin_list', [
            'js' => '/local/modules/my.bpbutton/install/js/my.bpbutton/admin.list.js',
            'rel' => ['main.core', 'ui.notification', 'ui.sidepanel'],
            'lang' => '/local/modules/my.bpbutton/lang/' . LANGUAGE_ID . '/install/js/my.bpbutton/admin.list.php',
        ]);
    }

    /**
     * Регистрация admin-меню
     */
    public function InstallMenu(): void
    {
        // Меню регистрируется автоматически через admin/menu.php
        // Bitrix автоматически подхватывает файлы из admin/menu.php модулей
        // Дополнительных действий не требуется
    }

    /**
     * Удаление admin-меню
     */
    public function UnInstallMenu(): void
    {
        // Меню удаляется автоматически при удалении модуля
        // Дополнительных действий не требуется
    }
}

