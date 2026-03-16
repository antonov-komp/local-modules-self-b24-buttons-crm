<?php

declare(strict_types=1);

use Bitrix\Main\Application;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\UserFieldTable;
use Bitrix\Main\Entity;
use Bitrix\Main\UI\Extension;
use My\BpButton\Internals\SettingsTable;
use My\BpButton\UserField\BpButtonUserType;

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php';

Loc::loadMessages(__FILE__);

global $APPLICATION;

if (!Loader::includeModule('my.bpbutton')) {
    require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php';
    ShowError('Module my.bpbutton not installed');
    require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';
    return;
}

$moduleRight = $APPLICATION->GetGroupRight('my.bpbutton');
if ($moduleRight < 'R') {
    $APPLICATION->AuthForm(Loc::getMessage('ACCESS_DENIED'));
}

/** @var CMain $APPLICATION */
$APPLICATION->SetTitle(Loc::getMessage('MY_BPBUTTON_LIST_PAGE_TITLE'));

$request = Application::getInstance()->getContext()->getRequest();
$isPost = $request->isPost() && check_bitrix_sessid();

// Simple routing: edit form vs list
$id = (int)$request->get('ID');
$fieldIdParam = (int)$request->get('FIELD_ID');
$action = (string)$request->get('action');

// Если передан FIELD_ID — ищем запись SettingsTable по FIELD_ID и подставляем ID
if ($fieldIdParam > 0) {
    $settingsRow = SettingsTable::getList([
        'filter' => ['=FIELD_ID' => $fieldIdParam],
        'limit'  => 1,
    ])->fetch();
    if ($settingsRow && !empty($settingsRow['ID'])) {
        $id = (int)$settingsRow['ID'];
    }
}

// ---------------------------------------------------------------------
// Edit form
// ---------------------------------------------------------------------
if ($action === 'edit' && $id > 0) {
    $isAjax = $request->get('IFRAME') === 'Y' && $request->get('IFRAME_TYPE') === 'SIDE_SLIDER';

    // Обработка POST для AJAX — ДО вывода HTML, иначе JSON будет испорчен
    if ($isPost && $isAjax && (isset($_POST['save']) || isset($_POST['apply'])) && $moduleRight >= 'W') {
        $postId = (int)($request->getPost('ID') ?: $id);
        $settingsRow = $postId > 0 ? SettingsTable::getByPrimary($postId)->fetch() : null;
        if ($settingsRow) {
            $handlerUrl = trim((string)$request->getPost('HANDLER_URL'));
            $title = trim((string)$request->getPost('TITLE'));
            $width = trim((string)$request->getPost('WIDTH'));
            $buttonText = trim((string)$request->getPost('BUTTON_TEXT'));
            $buttonSize = trim((string)$request->getPost('BUTTON_SIZE'));
            $active = $request->getPost('ACTIVE') === 'Y' ? 'Y' : 'N';

            $errors = [];
            if ($handlerUrl !== '' && !preg_match('~^https?://~i', $handlerUrl) && $handlerUrl[0] !== '/') {
                $errors[] = Loc::getMessage('MY_BPBUTTON_EDIT_ERROR_INVALID_URL');
            }
            if ($width !== '' && !preg_match('~^\d+$~', $width) && !preg_match('~^\d+%$~', $width)) {
                $errors[] = Loc::getMessage('MY_BPBUTTON_EDIT_ERROR_INVALID_WIDTH');
            }
            $allowedSizes = ['default', 'sm', 'lg'];
            if ($buttonSize !== '' && !in_array($buttonSize, $allowedSizes, true)) {
                $buttonSize = 'default';
            }

            if (empty($errors)) {
                $updateResult = SettingsTable::update($postId, [
                    'HANDLER_URL' => $handlerUrl !== '' ? $handlerUrl : null,
                    'TITLE'       => $title !== '' ? $title : null,
                    'WIDTH'       => $width !== '' ? $width : null,
                    'BUTTON_TEXT' => $buttonText !== '' ? $buttonText : null,
                    'BUTTON_SIZE' => $buttonSize !== '' ? $buttonSize : null,
                    'ACTIVE'      => $active,
                    'UPDATED_AT'  => new \Bitrix\Main\Type\DateTime(),
                ]);

                if ($updateResult->isSuccess()) {
                    header('Content-Type: application/json; charset=' . SITE_CHARSET);
                    echo json_encode([
                        'status'     => 'success',
                        'formParams' => ['ID' => $postId, 'action' => 'edit'],
                    ]);
                    return;
                }
                $errors[] = implode("\n", $updateResult->getErrorMessages());
            }

            if (!empty($errors)) {
                header('Content-Type: application/json; charset=' . SITE_CHARSET);
                echo json_encode([
                    'status'  => 'error',
                    'message' => implode("\n", $errors),
                ]);
                return;
            }
        }
    }

    // Подключение JS для SidePanel
    $isSidePanel = $request->get('IFRAME') === 'Y' || $request->get('IFRAME_TYPE') === 'SIDE_SLIDER';
    if ($isSidePanel && class_exists(Extension::class)) {
        Extension::load('ui.sidepanel');
        Extension::load('ui.notification');
    }

    require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php';

    if ($moduleRight < 'W') {
        ShowError(Loc::getMessage('ACCESS_DENIED'));
        require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';
        return;
    }

    $settingsRow = SettingsTable::getByPrimary($id)->fetch();
    if (!$settingsRow) {
        ShowError('Record not found');
        require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';
        return;
    }

    $fieldInfo = null;
    if (Loader::includeModule('main')) {
        $fieldInfo = UserFieldTable::getList([
            'select' => ['ID', 'FIELD_NAME', 'ENTITY_ID'],
            'filter' => ['=ID' => (int)$settingsRow['FIELD_ID']],
        ])->fetch();
    }

    $errors = [];

    if ($isPost && (isset($_POST['save']) || isset($_POST['apply']))) {
        $handlerUrl = trim((string)$request->getPost('HANDLER_URL'));
        $title = trim((string)$request->getPost('TITLE'));
        $width = trim((string)$request->getPost('WIDTH'));
        $buttonText = trim((string)$request->getPost('BUTTON_TEXT'));
        $buttonSize = trim((string)$request->getPost('BUTTON_SIZE'));
        $active = $request->getPost('ACTIVE') === 'Y' ? 'Y' : 'N';

        // Minimal URL validation: allow relative or absolute URLs
        if ($handlerUrl !== '' && !preg_match('~^https?://~i', $handlerUrl) && $handlerUrl[0] !== '/') {
            $errors[] = Loc::getMessage('MY_BPBUTTON_EDIT_ERROR_INVALID_URL');
        }
        $allowedSizes = ['default', 'sm', 'lg'];
        if ($buttonSize !== '' && !in_array($buttonSize, $allowedSizes, true)) {
            $buttonSize = 'default';
        }

        // Width validation: integer or number%
        if ($width !== '' && !preg_match('~^\d+$~', $width) && !preg_match('~^\d+%$~', $width)) {
            $errors[] = Loc::getMessage('MY_BPBUTTON_EDIT_ERROR_INVALID_WIDTH');
        }

        if (empty($errors)) {
            $updateResult = SettingsTable::update($id, [
                'HANDLER_URL' => $handlerUrl !== '' ? $handlerUrl : null,
                'TITLE'       => $title !== '' ? $title : null,
                'WIDTH'       => $width !== '' ? $width : null,
                'BUTTON_TEXT' => $buttonText !== '' ? $buttonText : null,
                'BUTTON_SIZE' => $buttonSize !== '' ? $buttonSize : null,
                'ACTIVE'      => $active,
                'UPDATED_AT'  => new \Bitrix\Main\Type\DateTime(),
            ]);

            if ($updateResult->isSuccess()) {
                $isSidePanel = $request->get('IFRAME') === 'Y' || $request->get('IFRAME_TYPE') === 'SIDE_SLIDER';
                if ($isSidePanel) {
                    ?>
                    <script>
                        if (typeof BX !== 'undefined' && BX.UI && BX.UI.Notification) {
                            BX.UI.Notification.Center.notify({
                                content: <?= CUtil::PhpToJSObject(Loc::getMessage('MY_BPBUTTON_EDIT_SAVE_SUCCESS')) ?>,
                                autoHideDelay: 3000,
                            });
                        }
                        if (typeof BX !== 'undefined' && BX.SidePanel && BX.SidePanel.Instance) {
                            setTimeout(function() {
                                BX.SidePanel.Instance.close();
                                if (window.top && window.top.location) {
                                    window.top.location.reload();
                                }
                            }, 500);
                        }
                    </script>
                    <?php
                    CAdminMessage::ShowMessage([
                        'MESSAGE' => Loc::getMessage('MY_BPBUTTON_EDIT_SAVE_SUCCESS'),
                        'TYPE'    => 'OK',
                    ]);
                } else {
                    CAdminMessage::ShowMessage([
                        'MESSAGE' => Loc::getMessage('MY_BPBUTTON_EDIT_SAVE_SUCCESS'),
                        'TYPE'    => 'OK',
                    ]);

                    if (isset($_POST['save'])) {
                        LocalRedirect('my_bpbutton_bpbutton_list.php?lang=' . LANGUAGE_ID);
                    } else {
                        LocalRedirect('my_bpbutton_bpbutton_list.php?lang=' . LANGUAGE_ID . '&action=edit&ID=' . $id);
                    }
                }
            } else {
                $errors[] = implode("\n", $updateResult->getErrorMessages());
            }
        }

        if (!empty($errors)) {
            CAdminMessage::ShowMessage([
                'MESSAGE' => Loc::getMessage('MY_BPBUTTON_EDIT_SAVE_ERROR', ['#ERROR#' => implode("\n", $errors)]),
                'TYPE'    => 'ERROR',
            ]);
        }
    }

    $tabControl = new CAdminTabControl('tabControl', [
        [
            'DIV'   => 'edit_main',
            'TAB'   => Loc::getMessage('MY_BPBUTTON_EDIT_TAB_MAIN'),
            'TITLE' => Loc::getMessage('MY_BPBUTTON_EDIT_TAB_MAIN_TITLE'),
        ],
    ]);

    ?>
    <form method="post" action="<?= htmlspecialcharsbx($APPLICATION->GetCurPageParam('', ['mode'])); ?>">
        <?= bitrix_sessid_post(); ?>
        <input type="hidden" name="lang" value="<?= LANGUAGE_ID ?>">
        <input type="hidden" name="ID" value="<?= (int)$id ?>">
        <input type="hidden" name="action" value="edit">
        <?php
        $tabControl->Begin();
        $tabControl->BeginNextTab();

        $fieldTitleParts = [];
        if ($fieldInfo) {
            $fieldTitleParts[] = '[' . $fieldInfo['FIELD_NAME'] . ']';
        } else {
            $fieldTitleParts[] = 'ID=' . (int)$settingsRow['FIELD_ID'];
        }
        $fieldTitle = implode(' ', $fieldTitleParts);

        ?>
        <tr>
            <td width="40%"><?= Loc::getMessage('MY_BPBUTTON_EDIT_FIELD_FIELD_INFO'); ?>:</td>
            <td width="60%"><?= htmlspecialcharsbx($fieldTitle); ?></td>
        </tr>
        <tr>
            <td><?= Loc::getMessage('MY_BPBUTTON_EDIT_FIELD_ENTITY_ID'); ?>:</td>
            <td><?= htmlspecialcharsbx((string)$settingsRow['ENTITY_ID']); ?></td>
        </tr>
        <tr>
            <td><?= Loc::getMessage('MY_BPBUTTON_EDIT_FIELD_HANDLER_URL'); ?>:</td>
            <td>
                <input type="text" name="HANDLER_URL" size="60"
                       value="<?= htmlspecialcharsbx((string)$settingsRow['HANDLER_URL']); ?>"
                       title="<?= htmlspecialcharsbx(Loc::getMessage('MY_BPBUTTON_EDIT_FIELD_HANDLER_URL_HINT') ?: ''); ?>">
                <div style="margin-top: 4px; color: #666; font-size: 11px;"><?= htmlspecialcharsbx(Loc::getMessage('MY_BPBUTTON_EDIT_FIELD_HANDLER_URL_HINT') ?: ''); ?></div>
            </td>
        </tr>
        <tr>
            <td><?= Loc::getMessage('MY_BPBUTTON_EDIT_FIELD_TITLE'); ?>:</td>
            <td>
                <input type="text" name="TITLE" size="40"
                       value="<?= htmlspecialcharsbx((string)$settingsRow['TITLE']); ?>"
                       title="<?= htmlspecialcharsbx(Loc::getMessage('MY_BPBUTTON_EDIT_FIELD_TITLE_HINT') ?: ''); ?>">
                <div style="margin-top: 4px; color: #666; font-size: 11px;"><?= htmlspecialcharsbx(Loc::getMessage('MY_BPBUTTON_EDIT_FIELD_TITLE_HINT') ?: ''); ?></div>
            </td>
        </tr>
        <tr>
            <td><?= Loc::getMessage('MY_BPBUTTON_EDIT_FIELD_BUTTON_TEXT'); ?>:</td>
            <td>
                <input type="text" name="BUTTON_TEXT" size="40"
                       value="<?= htmlspecialcharsbx((string)($settingsRow['BUTTON_TEXT'] ?? '')); ?>"
                       title="<?= htmlspecialcharsbx(Loc::getMessage('MY_BPBUTTON_EDIT_FIELD_BUTTON_TEXT_HINT') ?: ''); ?>">
                <div style="margin-top: 4px; color: #666; font-size: 11px;">
                    <?= htmlspecialcharsbx(Loc::getMessage('MY_BPBUTTON_EDIT_FIELD_BUTTON_TEXT_HINT') ?: ''); ?>
                </div>
            </td>
        </tr>
        <tr>
            <td><?= Loc::getMessage('MY_BPBUTTON_EDIT_FIELD_WIDTH'); ?>:</td>
            <td>
                <input type="text" name="WIDTH" size="10"
                       value="<?= htmlspecialcharsbx((string)$settingsRow['WIDTH']); ?>"
                       title="<?= htmlspecialcharsbx(Loc::getMessage('MY_BPBUTTON_EDIT_FIELD_WIDTH_HINT') ?: ''); ?>">
                <div style="margin-top: 4px; color: #666; font-size: 11px;"><?= htmlspecialcharsbx(Loc::getMessage('MY_BPBUTTON_EDIT_FIELD_WIDTH_HINT') ?: ''); ?></div>
            </td>
        </tr>
        <tr>
            <td><?= Loc::getMessage('MY_BPBUTTON_EDIT_FIELD_BUTTON_SIZE'); ?>:</td>
            <td>
                <?php
                $currentSize = (string)($settingsRow['BUTTON_SIZE'] ?? '');
                if (!in_array($currentSize, ['sm', 'lg'], true)) {
                    $currentSize = 'default';
                }
                ?>
                <select name="BUTTON_SIZE" title="<?= htmlspecialcharsbx(Loc::getMessage('MY_BPBUTTON_EDIT_FIELD_BUTTON_SIZE_HINT') ?: ''); ?>">
                    <option value="default"<?= $currentSize === 'default' ? ' selected' : ''; ?>><?= htmlspecialcharsbx(Loc::getMessage('MY_BPBUTTON_EDIT_FIELD_BUTTON_SIZE_DEFAULT') ?: 'Стандартная'); ?></option>
                    <option value="sm"<?= $currentSize === 'sm' ? ' selected' : ''; ?>><?= htmlspecialcharsbx(Loc::getMessage('MY_BPBUTTON_EDIT_FIELD_BUTTON_SIZE_SM') ?: 'Маленькая'); ?></option>
                    <option value="lg"<?= $currentSize === 'lg' ? ' selected' : ''; ?>><?= htmlspecialcharsbx(Loc::getMessage('MY_BPBUTTON_EDIT_FIELD_BUTTON_SIZE_LG') ?: 'Большая'); ?></option>
                </select>
                <div style="margin-top: 4px; color: #666; font-size: 11px;"><?= htmlspecialcharsbx(Loc::getMessage('MY_BPBUTTON_EDIT_FIELD_BUTTON_SIZE_HINT') ?: ''); ?></div>
            </td>
        </tr>
        <tr>
            <td><?= Loc::getMessage('MY_BPBUTTON_EDIT_FIELD_ACTIVE'); ?>:</td>
            <td>
                <input type="checkbox" name="ACTIVE" value="Y"<?= $settingsRow['ACTIVE'] === 'Y' ? ' checked' : ''; ?>
                       title="<?= htmlspecialcharsbx(Loc::getMessage('MY_BPBUTTON_EDIT_FIELD_ACTIVE_HINT') ?: ''); ?>">
                <span style="margin-left: 6px; color: #666; font-size: 11px;"><?= htmlspecialcharsbx(Loc::getMessage('MY_BPBUTTON_EDIT_FIELD_ACTIVE_HINT') ?: ''); ?></span>
            </td>
        </tr>
        <?php
        $tabControl->Buttons([
            'back_url' => 'my_bpbutton_bpbutton_list.php?lang=' . LANGUAGE_ID,
        ]);
        $tabControl->End();
        ?>
    </form>
    <?php

    require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';
    return;
}

// ---------------------------------------------------------------------
// List view (classic CAdminList/CAdminFilter)
// ---------------------------------------------------------------------

$connection = Application::getConnection();

// Авто-синхронизация настроек с существующими полями bp_button_field
try {
    $existingSettings = [];
    $settingsCursor = SettingsTable::getList([
        'select' => ['FIELD_ID'],
    ]);
    while ($settingsRow = $settingsCursor->fetch()) {
        if (!empty($settingsRow['FIELD_ID'])) {
            $existingSettings[(int)$settingsRow['FIELD_ID']] = true;
        }
    }

    if (Loader::includeModule('main')) {
        $userFieldsCursor = UserFieldTable::getList([
            'select' => ['ID', 'ENTITY_ID'],
            'filter' => [
                '=USER_TYPE_ID' => BpButtonUserType::USER_TYPE_ID,
            ],
        ]);

        while ($uf = $userFieldsCursor->fetch()) {
            $fieldId = (int)$uf['ID'];
            if ($fieldId > 0 && !isset($existingSettings[$fieldId])) {
                SettingsTable::add([
                    'FIELD_ID'    => $fieldId,
                    'ENTITY_ID'   => (string)($uf['ENTITY_ID'] ?? null),
                    'HANDLER_URL' => null,
                    'TITLE'       => null,
                    'BUTTON_TEXT' => null,
                    'WIDTH'       => null,
                    'BUTTON_SIZE' => null,
                    'ACTIVE'      => 'Y',
                    'CREATED_AT'  => new \Bitrix\Main\Type\DateTime(),
                    'UPDATED_AT'  => new \Bitrix\Main\Type\DateTime(),
                ]);
            }
        }
    }
} catch (\Throwable $e) {
    // Ошибки синхронизации не должны ломать реестр
}

$sTableID = 'my_bpbutton_bpbutton_list';
$oSort = new CAdminSorting($sTableID, 'ID', 'desc');
$lAdmin = new CAdminList($sTableID, $oSort);

$allowedSortFields = [
    'ID'         => 'ID',
    'ENTITY_ID'  => 'ENTITY_ID',
    'ACTIVE'     => 'ACTIVE',
    'UPDATED_AT' => 'UPDATED_AT',
];

$by = strtoupper((string)($GLOBALS['by'] ?? 'ID'));
$order = strtoupper((string)($GLOBALS['order'] ?? 'DESC'));

$orderDir = $order === 'ASC' ? 'ASC' : 'DESC';
$orderField = $allowedSortFields[$by] ?? 'ID';

$filterFields = [
    'find_entity_id',
    'find_active',
    'find_field_query',
    'find_handler_url_query',
];

$lAdmin->InitFilter($filterFields);

$findEntityId = (string)($GLOBALS['find_entity_id'] ?? '');
$findActive = (string)($GLOBALS['find_active'] ?? '');
$findFieldQuery = (string)($GLOBALS['find_field_query'] ?? '');
$findHandlerUrlQuery = (string)($GLOBALS['find_handler_url_query'] ?? '');

$filter = [];
$runtime = [];

// Join с UserFieldTable — всегда, для отображения названия поля в колонке «Поле»
$runtime['UF'] = new Entity\ReferenceField(
    'UF',
    UserFieldTable::class,
    ['=this.FIELD_ID' => 'ref.ID'],
    ['join_type' => 'left']
);

if ($findEntityId !== '') {
    $filter['=ENTITY_ID'] = $findEntityId;
}

if ($findActive === 'Y' || $findActive === 'N') {
    $filter['=ACTIVE'] = $findActive;
}

if ($findFieldQuery !== '') {
    $filter[] = [
        'LOGIC'          => 'OR',
        '%UF.FIELD_NAME' => $findFieldQuery,
    ];
}

if ($findHandlerUrlQuery !== '') {
    $filter['%HANDLER_URL'] = $findHandlerUrlQuery;
}

// Group actions
if ($isPost && $moduleRight >= 'W' && $lAdmin->GroupAction()) {
    $ids = (array)$lAdmin->GroupAction();
}

if ($isPost && $moduleRight >= 'W' && isset($_POST['action'])) {
    $actionId = (string)$_POST['action'];
    $ids = $_POST['ID'] ?? [];
    if (!is_array($ids)) {
        $ids = [$ids];
    }

    foreach ($ids as $primaryId) {
        $primaryId = (int)$primaryId;
        if ($primaryId <= 0) {
            continue;
        }

        if ($actionId === 'activate' || $actionId === 'deactivate') {
            $active = $actionId === 'activate' ? 'Y' : 'N';
            SettingsTable::update($primaryId, [
                'ACTIVE'     => $active,
                'UPDATED_AT' => new \Bitrix\Main\Type\DateTime(),
            ]);
        } elseif ($actionId === 'delete') {
            SettingsTable::delete($primaryId);
        }
    }
}

$select = ['ID', 'FIELD_ID', 'ENTITY_ID', 'HANDLER_URL', 'TITLE', 'WIDTH', 'ACTIVE', 'UPDATED_AT', 'UF.FIELD_NAME'];

$pageSize = (int)$request->get('pageSize');
if (!in_array($pageSize, [10, 20, 50, 100], true)) {
    $pageSize = 20;
}
$page = (int)$request->get('page');
if ($page < 1) {
    $page = 1;
}
$offset = ($page - 1) * $pageSize;

$result = SettingsTable::getList([
    'select'  => $select,
    'filter'  => $filter,
    'runtime' => $runtime,
    'order'   => [$orderField => $orderDir],
    'count_total' => true,
    'limit' => $pageSize,
    'offset' => $offset,
]);

/** @var int $totalRows */
$totalRows = (int)$result->getCount();

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php';

$lAdmin->AddHeaders([
    ['id' => 'ID', 'content' => Loc::getMessage('MY_BPBUTTON_LIST_COLUMN_ID'), 'default' => true, 'sort' => 'ID'],
    ['id' => 'FIELD', 'content' => Loc::getMessage('MY_BPBUTTON_LIST_COLUMN_FIELD'), 'default' => true],
    ['id' => 'ENTITY_ID', 'content' => Loc::getMessage('MY_BPBUTTON_LIST_COLUMN_ENTITY_ID'), 'default' => true, 'sort' => 'ENTITY_ID'],
    ['id' => 'HANDLER_URL', 'content' => Loc::getMessage('MY_BPBUTTON_LIST_COLUMN_HANDLER_URL'), 'default' => true],
    ['id' => 'TITLE', 'content' => Loc::getMessage('MY_BPBUTTON_LIST_COLUMN_TITLE'), 'default' => true],
    ['id' => 'WIDTH', 'content' => Loc::getMessage('MY_BPBUTTON_LIST_COLUMN_WIDTH'), 'default' => true],
    ['id' => 'ACTIVE', 'content' => Loc::getMessage('MY_BPBUTTON_LIST_COLUMN_ACTIVE'), 'default' => true, 'sort' => 'ACTIVE'],
    ['id' => 'UPDATED_AT', 'content' => Loc::getMessage('MY_BPBUTTON_LIST_COLUMN_UPDATED_AT'), 'default' => true, 'sort' => 'UPDATED_AT'],
]);

while ($row = $result->fetch()) {
    // Ссылка для открытия в SidePanel (если используется)
    $editUrl = 'my_bpbutton_bpbutton_list.php?lang=' . LANGUAGE_ID . '&action=edit&ID=' . (int)$row['ID'];
    $listRow = $lAdmin->AddRow((string)$row['ID'], $row, $editUrl, Loc::getMessage('MY_BPBUTTON_LIST_EDIT_TITLE'));

    $fieldLabelParts = [];
    if (!empty($row['UF_FIELD_NAME'])) {
        $fieldLabelParts[] = '[' . $row['UF_FIELD_NAME'] . ']';
    } else {
        $fieldLabelParts[] = 'ID=' . (int)$row['FIELD_ID'];
    }
    $fieldLabel = implode(' ', $fieldLabelParts);

    $listRow->AddViewField('FIELD', htmlspecialcharsbx($fieldLabel));
    $listRow->AddViewField('ENTITY_ID', htmlspecialcharsbx((string)$row['ENTITY_ID']));

        // HANDLER_URL с тултипом
    $handlerUrl = (string)$row['HANDLER_URL'];
    if ($handlerUrl !== '' && mb_strlen($handlerUrl) > 60) {
        $short = mb_substr($handlerUrl, 0, 57) . '...';
        $handlerHtml = '<span title="' . htmlspecialcharsbx($handlerUrl) . '" class="js-bpbutton-handler-url">' . htmlspecialcharsbx($short) . '</span>';
    } else {
        $handlerHtml = '<span class="js-bpbutton-handler-url" title="' . htmlspecialcharsbx($handlerUrl) . '">' . htmlspecialcharsbx($handlerUrl) . '</span>';
    }
    $listRow->AddViewField('HANDLER_URL', $handlerHtml);

    $listRow->AddViewField('TITLE', htmlspecialcharsbx((string)$row['TITLE']));

    // WIDTH с тултипом
    $width = (string)$row['WIDTH'];
    $widthHint = '';
    if ($width !== '') {
        if (mb_strpos($width, '%') !== false) {
            $widthHint = Loc::getMessage('MY_BPBUTTON_ADMIN_WIDTH_HINT_PERCENT') ?: 'Проценты от ширины экрана/SidePanel';
        } elseif (preg_match('/^\d+$/', $width)) {
            $widthHint = Loc::getMessage('MY_BPBUTTON_ADMIN_WIDTH_HINT_PIXELS') ?: 'Пиксели';
        }
    }
    $widthHtml = '<span class="js-bpbutton-width-cell" data-width="' . htmlspecialcharsbx($width) . '"';
    if ($widthHint !== '') {
        $widthHtml .= ' title="' . htmlspecialcharsbx($widthHint) . '"';
    }
    $widthHtml .= '>' . htmlspecialcharsbx($width) . '</span>';
    $listRow->AddViewField('WIDTH', $widthHtml);

    // ACTIVE с inline-переключателем
    $activeValue = $row['ACTIVE'] === 'Y' ? 'Y' : 'N';
    $activeText = $activeValue === 'Y'
        ? Loc::getMessage('MY_BPBUTTON_LIST_ACTIVE_Y')
        : Loc::getMessage('MY_BPBUTTON_LIST_ACTIVE_N');
    $activeHint = $activeValue === 'Y'
        ? (Loc::getMessage('MY_BPBUTTON_ADMIN_ACTIVE_HINT_Y') ?: 'Кнопка доступна пользователям CRM')
        : (Loc::getMessage('MY_BPBUTTON_ADMIN_ACTIVE_HINT_N') ?: 'Кнопка временно отключена');

    if ($moduleRight >= 'W') {
        // Inline-переключатель для администраторов
        $activeIcon = $activeValue === 'Y' ? '✓' : '✗';
        $activeColor = $activeValue === 'Y' ? 'green' : 'gray';
        $activeHtml = '<button type="button" class="ui-btn ui-btn-link js-bpbutton-active-toggle" '
            . 'data-id="' . (int)$row['ID'] . '" '
            . 'data-active="' . htmlspecialcharsbx($activeValue) . '" '
            . 'title="' . htmlspecialcharsbx($activeHint) . '" '
            . 'style="border: none; background: none; padding: 0; cursor: pointer; color: ' . $activeColor . ';">'
            . '<span style="margin-right: 4px;">' . htmlspecialcharsbx($activeIcon) . '</span>'
            . '<span class="js-bpbutton-active-text">' . htmlspecialcharsbx($activeText) . '</span>'
            . '</button>';
    } else {
        // Только отображение для пользователей без прав на запись
        $activeColor = $activeValue === 'Y' ? 'green' : 'gray';
        $activeHtml = '<span style="color: ' . $activeColor . ';" title="' . htmlspecialcharsbx($activeHint) . '">'
            . htmlspecialcharsbx($activeText)
            . '</span>';
    }
    $listRow->AddViewField('ACTIVE', '<span class="js-bpbutton-active-cell">' . $activeHtml . '</span>');

    $listRow->AddViewField('UPDATED_AT', htmlspecialcharsbx((string)$row['UPDATED_AT']));

    $actions = [];
    if ($moduleRight >= 'W') {
        $editUrl = 'my_bpbutton_bpbutton_list.php?lang=' . LANGUAGE_ID . '&action=edit&ID=' . (int)$row['ID'];
        // Используем SidePanel для открытия формы редактирования
        $actions[] = [
            'ICON'    => 'edit',
            'TEXT'    => Loc::getMessage('MY_BPBUTTON_LIST_EDIT_TITLE'),
            'ACTION'  => "BX.SidePanel && BX.SidePanel.Instance ? BX.SidePanel.Instance.open('" . CUtil::JSEscape($editUrl) . "', {width: '60%', cacheable: false, allowChangeHistory: true}) : window.location.href='" . CUtil::JSEscape($editUrl) . "';",
            'DEFAULT' => true,
        ];
        $actions[] = [
            'ICON'   => 'delete',
            'TEXT'   => Loc::getMessage('MY_BPBUTTON_LIST_ACTION_DELETE'),
            'ACTION' => "if(confirm('" . CUtil::JSEscape(Loc::getMessage('MY_BPBUTTON_LIST_ACTION_DELETE_CONFIRM')) . "')) " .
                $lAdmin->ActionDoGroup((int)$row['ID'], 'delete'),
        ];
    }

    if (!empty($actions)) {
        $listRow->AddActions($actions);
    }
}

$lAdmin->AddGroupActionTable([
    'activate'   => Loc::getMessage('MY_BPBUTTON_LIST_ACTION_ACTIVATE'),
    'deactivate' => Loc::getMessage('MY_BPBUTTON_LIST_ACTION_DEACTIVATE'),
    'delete'     => Loc::getMessage('MY_BPBUTTON_LIST_ACTION_DELETE'),
]);

$oFilter = new CAdminFilter(
    $sTableID . '_filter',
    [
        Loc::getMessage('MY_BPBUTTON_LIST_FILTER_ENTITY_ID'),
        Loc::getMessage('MY_BPBUTTON_LIST_FILTER_ACTIVE'),
        Loc::getMessage('MY_BPBUTTON_LIST_FILTER_FIELD_QUERY'),
        Loc::getMessage('MY_BPBUTTON_LIST_FILTER_HANDLER_URL'),
    ]
);

?>
<form name="find_form" method="get" action="<?= htmlspecialcharsbx($APPLICATION->GetCurPage()); ?>">
    <input type="hidden" name="lang" value="<?= LANGUAGE_ID ?>">
    <?php $oFilter->Begin(); ?>
    <tr>
        <td><?= Loc::getMessage('MY_BPBUTTON_LIST_FILTER_ENTITY_ID'); ?>:</td>
        <td><input type="text" name="find_entity_id" value="<?= htmlspecialcharsbx($findEntityId); ?>"></td>
    </tr>
    <tr>
        <td><?= Loc::getMessage('MY_BPBUTTON_LIST_FILTER_ACTIVE'); ?>:</td>
        <td>
            <select name="find_active">
                <option value=""><?= GetMessage('MAIN_ALL'); ?></option>
                <option value="Y"<?= $findActive === 'Y' ? ' selected' : ''; ?>><?= Loc::getMessage('MY_BPBUTTON_LIST_ACTIVE_Y'); ?></option>
                <option value="N"<?= $findActive === 'N' ? ' selected' : ''; ?>><?= Loc::getMessage('MY_BPBUTTON_LIST_ACTIVE_N'); ?></option>
            </select>
        </td>
    </tr>
    <tr>
        <td><?= Loc::getMessage('MY_BPBUTTON_LIST_FILTER_FIELD_QUERY'); ?>:</td>
        <td><input type="text" name="find_field_query" value="<?= htmlspecialcharsbx($findFieldQuery); ?>"></td>
    </tr>
    <tr>
        <td><?= Loc::getMessage('MY_BPBUTTON_LIST_FILTER_HANDLER_URL'); ?>:</td>
        <td><input type="text" name="find_handler_url_query" value="<?= htmlspecialcharsbx($findHandlerUrlQuery); ?>"></td>
    </tr>
    <?php
    $oFilter->Buttons([
        'table_id' => $sTableID,
        'url'      => $APPLICATION->GetCurPage(),
        'form'     => 'find_form',
    ]);
    $oFilter->End();
    ?>
</form>
<?php

$lAdmin->AddFooter([
    [
        'title' => GetMessage('MAIN_ADMIN_LIST_SELECTED'),
        'value' => '0',
    ],
    [
        'title' => GetMessage('MAIN_ADMIN_LIST_CHECKED'),
        'value' => '0',
    ],
    [
        'title' => GetMessage('MAIN_ADMIN_LIST_TOTAL'),
        'value' => (string)$totalRows,
    ],
]);

// Простая навигация (без CAdminResult/NavStart)
$pageCount = $pageSize > 0 ? (int)ceil($totalRows / $pageSize) : 1;
if ($pageCount < 1) {
    $pageCount = 1;
}

$baseParams = $request->getQueryList()->toArray();
unset($baseParams['page']);
$baseParams['pageSize'] = $pageSize;

$navHtml = '';
if ($pageCount > 1) {
    $navHtml .= '<div style="margin: 12px 0;">';
    $navHtml .= '<span style="margin-right: 12px;">Страница ' . $page . ' из ' . $pageCount . '</span>';

    if ($page > 1) {
        $baseParams['page'] = 1;
        $navHtml .= '<a href="' . htmlspecialcharsbx($APPLICATION->GetCurPageParam(http_build_query($baseParams), [])) . '">« Первая</a> ';
        $baseParams['page'] = $page - 1;
        $navHtml .= '<a href="' . htmlspecialcharsbx($APPLICATION->GetCurPageParam(http_build_query($baseParams), [])) . '">‹ Назад</a> ';
    }

    if ($page < $pageCount) {
        $baseParams['page'] = $page + 1;
        $navHtml .= '<a href="' . htmlspecialcharsbx($APPLICATION->GetCurPageParam(http_build_query($baseParams), [])) . '">Вперёд ›</a> ';
        $baseParams['page'] = $pageCount;
        $navHtml .= '<a href="' . htmlspecialcharsbx($APPLICATION->GetCurPageParam(http_build_query($baseParams), [])) . '">Последняя »</a>';
    }

    $navHtml .= '</div>';
}

$lAdmin->CheckListMode();

// Подключение JS-расширения для админ-списка
if (class_exists(Extension::class)) {
    Extension::load('my_bpbutton.admin_list');
}

echo $navHtml;
$lAdmin->DisplayList();
echo $navHtml;

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';

