<?php

declare(strict_types=1);

use Bitrix\Main\Application;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use My\BpButton\Helper\SecurityHelper;
use My\BpButton\Service\SettingsFormService;

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php';

Loc::loadMessages(__FILE__);

header('Content-Type: application/json; charset=utf-8');

if (!Loader::includeModule('my.bpbutton')) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => [
            'message' => 'Module not installed',
        ],
    ]);
    exit;
}

global $APPLICATION;

$moduleRight = $APPLICATION->GetGroupRight('my.bpbutton');
if ($moduleRight < 'W') {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'error' => [
            'message' => Loc::getMessage('MY_BPBUTTON_AJAX_ERROR_ACCESS_DENIED') ?: 'Недостаточно прав для изменения статуса.',
        ],
    ]);
    exit;
}

$request = Application::getInstance()->getContext()->getRequest();

if (!$request->isPost() || !check_bitrix_sessid()) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => [
            'message' => Loc::getMessage('MY_BPBUTTON_AJAX_ERROR_INVALID_REQUEST') ?: 'Некорректный запрос.',
        ],
    ]);
    exit;
}

$action = (string)$request->getPost('action');
$id = (int)$request->getPost('ID');
$active = (string)$request->getPost('ACTIVE');

if ($action !== 'toggle_active' || $id <= 0) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => [
            'message' => Loc::getMessage('MY_BPBUTTON_AJAX_ERROR_INVALID_PARAMS') ?: 'Некорректные параметры запроса.',
        ],
    ]);
    exit;
}

if ($active !== 'Y' && $active !== 'N') {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => [
            'message' => Loc::getMessage('MY_BPBUTTON_AJAX_ERROR_INVALID_ACTIVE') ?: 'Некорректное значение активности.',
        ],
    ]);
    exit;
}

try {
    $formService = new SettingsFormService();
    $settingsRow = $formService->getById($id);
    if (!$settingsRow) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => [
                'message' => Loc::getMessage('MY_BPBUTTON_AJAX_ERROR_NOT_FOUND') ?: 'Запись не найдена.',
            ],
        ]);
        exit;
    }

    $updateResult = $formService->toggleActive($id, $active);

    if (!$updateResult->isSuccess()) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => [
                'message' => Loc::getMessage('MY_BPBUTTON_AJAX_ERROR_UPDATE_FAILED') ?: 'Не удалось сохранить изменения.',
            ],
        ]);
        exit;
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'id' => $id,
            'active' => $active,
        ],
    ]);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => [
            'message' => Loc::getMessage('MY_BPBUTTON_AJAX_ERROR_INTERNAL') ?: 'Внутренняя ошибка сервера.',
        ],
    ]);

    // Безопасное логирование исключения (без чувствительных данных)
    SecurityHelper::safeLog($e, 'my.bpbutton', 'bpbutton_list_ajax::toggle_active');
}

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';
