<?php

declare(strict_types=1);

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Application;
use Bitrix\Main\Loader;

Loc::loadMessages(__FILE__);

class my_bpbutton extends CModule
{
    public $MODULE_ID = 'my.bpbutton';
    public $MODULE_VERSION = '0.0.1';
    public $MODULE_VERSION_DATE = '2026-03-16 00:00:00';
    public $MODULE_NAME;
    public $MODULE_DESCRIPTION;

    public function __construct()
    {
        $this->MODULE_NAME = 'BP Button Field';
        $this->MODULE_DESCRIPTION = 'Пользовательский тип поля bp_button_field для CRM и смарт‑процессов.';
    }

    public function DoInstall()
    {
        Loader::includeModule('main');

        $connection = Application::getConnection();
        $tableName = 'my_bpbutton_settings';

        if (!$connection->isTableExists($tableName)) {
            $connection->queryExecute(
                'CREATE TABLE IF NOT EXISTS `' . $tableName . '` (
                    `ID` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `FIELD_ID` INT UNSIGNED NOT NULL,
                    `ENTITY_ID` VARCHAR(50) NULL,
                    `HANDLER_URL` VARCHAR(500) NULL,
                    `TITLE` VARCHAR(255) NULL,
                    `WIDTH` VARCHAR(50) NULL,
                    `ACTIVE` CHAR(1) NOT NULL DEFAULT \'Y\',
                    `CREATED_AT` DATETIME NOT NULL,
                    `UPDATED_AT` DATETIME NOT NULL,
                    PRIMARY KEY (`ID`),
                    UNIQUE KEY `UX_BPBTN_FIELD` (`FIELD_ID`),
                    KEY `IX_BPBTN_ENTITY` (`ENTITY_ID`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8;'
            );
        }

        RegisterModule($this->MODULE_ID);
        $this->InstallEvents();
    }

    public function DoUninstall()
    {
        $this->UnInstallEvents();
        UnRegisterModule($this->MODULE_ID);
    }

    public function InstallEvents()
    {
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

    public function UnInstallEvents()
    {
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
}

