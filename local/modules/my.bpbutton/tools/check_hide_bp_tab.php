<?php
/**
 * Диагностика: проверка настроек HIDE_BP_TAB
 * Запуск из CLI: php -d short_open_tag=1 check_hide_bp_tab.php
 * Или через браузер (авторизованным): /local/modules/my.bpbutton/tools/check_hide_bp_tab.php
 */
$docRoot = dirname(dirname(dirname(dirname(__DIR__))));
$_SERVER['DOCUMENT_ROOT'] = $docRoot;
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost';
define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);
require $docRoot . '/bitrix/modules/main/include/prolog_before.php';

use Bitrix\Main\Loader;
use My\BpButton\Internals\SettingsTable;
use My\BpButton\Repository\SettingsRepository;

header('Content-Type: text/plain; charset=utf-8');

if (!Loader::includeModule('my.bpbutton')) {
    echo "Модуль my.bpbutton не установлен.\n";
    exit(1);
}

echo "=== Диагностика HIDE_BP_TAB ===\n\n";

$rows = SettingsTable::getList([
    'filter' => ['=ACTION_TYPE' => 'bp_launch'],
    'select' => ['ID', 'ENTITY_ID', 'FIELD_ID', 'HIDE_BP_TAB', 'ACTIVE', 'ACTION_TYPE'],
])->fetchAll();

echo "Записи с ACTION_TYPE=bp_launch:\n";
if (empty($rows)) {
    echo "  (нет записей)\n";
    echo "\nУбедитесь, что кнопка настроена с типом действия «Запуск БП».\n";
} else {
    foreach ($rows as $r) {
        $hide = ($r['HIDE_BP_TAB'] ?? 'N') === 'Y' ? 'Y' : 'N';
        $active = ($r['ACTIVE'] ?? 'N') === 'Y' ? 'Y' : 'N';
        $willHide = ($hide === 'Y' && $active === 'Y') ? ' -> СКРЫТЬ' : '';
        echo sprintf("  ID=%s ENTITY_ID=%s HIDE_BP_TAB=%s ACTIVE=%s%s\n",
            $r['ID'], $r['ENTITY_ID'], $hide, $active, $willHide);
    }
}

echo "\nПроверка shouldHideBpTabForEntity:\n";
$repo = new SettingsRepository();
foreach (['CRM_LEAD', 'CRM_DEAL', 'CRM_CONTACT', 'CRM_COMPANY'] as $entityId) {
    $hide = $repo->shouldHideBpTabForEntity($entityId);
    echo sprintf("  %s: %s\n", $entityId, $hide ? 'СКРЫВАТЬ' : 'не скрывать');
}

echo "\nДля отладки откройте карточку CRM с ?debug_bpbutton=1 в URL\n";
echo "и проверьте консоль браузера (F12).\n";
