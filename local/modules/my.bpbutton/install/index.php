<?php

declare(strict_types=1);

use Bitrix\Main\Localization\Loc;

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

