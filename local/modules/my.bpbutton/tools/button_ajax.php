<?php

declare(strict_types=1);

define('NO_KEEP_STATISTIC', true);
define('NO_AGENT_CHECK', true);
define('DisableEventsCheck', true);

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

use Bitrix\Main\Loader;
use My\BpButton\Controller\ButtonController;

header('Content-Type: application/json; charset=' . SITE_CHARSET);

if (!Loader::includeModule('my.bpbutton')) {
    echo json_encode([
        'success' => false,
        'error' => [
            'code' => 'INTERNAL_ERROR',
            'message' => 'Модуль my.bpbutton не установлен.',
        ],
    ]);
    return;
}

$request = \Bitrix\Main\Application::getInstance()->getContext()->getRequest();

$entityId = (string)$request->getPost('entityId');
$elementId = (int)$request->getPost('elementId');
$fieldId = (int)$request->getPost('fieldId');
$action = (string)$request->getPost('action');
$value = (string)$request->getPost('value');

try {
    $controller = new ButtonController();
    if ($action === 'startBpWithParams') {
        $result = $controller->startBpWithParamsAction($entityId, $elementId, $fieldId, $value);
    } else {
        $result = $controller->getConfigAction($entityId, $elementId, $fieldId);
    }
} catch (\Throwable $e) {
    $result = [
        'success' => false,
        'error' => [
            'code' => 'INTERNAL_ERROR',
            'message' => 'Произошла ошибка. Попробуйте позже или обратитесь к администратору.',
        ],
    ];
}

echo json_encode($result);

