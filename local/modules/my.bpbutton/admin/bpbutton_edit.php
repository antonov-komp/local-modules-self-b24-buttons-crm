<?php

declare(strict_types=1);

use Bitrix\Main\Application;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\UserFieldTable;
use Bitrix\Main\UI\Extension;
use My\BpButton\Service\EntityNameResolver;
use My\BpButton\Service\SettingsFormService;

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php';

Loc::loadMessages(dirname(__DIR__) . '/admin/bpbutton_list.php');

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

$request = Application::getInstance()->getContext()->getRequest();
$isPost = $request->isPost() && check_bitrix_sessid();

// Роутинг ID: из GET или по FIELD_ID
$id = (int)$request->get('ID');
$fieldIdParam = (int)$request->get('FIELD_ID');

if ($fieldIdParam > 0) {
    $formService = new SettingsFormService();
    $resolvedId = $formService->getIdByFieldId($fieldIdParam);
    if ($resolvedId !== null) {
        $id = $resolvedId;
    }
}

if ($id <= 0) {
    LocalRedirect('my_bpbutton_bpbutton_list.php?lang=' . LANGUAGE_ID);
    return;
}

$isAjax = $request->get('IFRAME') === 'Y' && $request->get('IFRAME_TYPE') === 'SIDE_SLIDER';

// Обработка POST (AJAX) — до вывода HTML
if ($isPost && $isAjax && (isset($_POST['save']) || isset($_POST['apply'])) && $moduleRight >= 'W') {
    $formService = new SettingsFormService();
    $postId = (int)($request->getPost('ID') ?: $id);
    $settingsRow = $formService->getById($postId);

    if ($settingsRow) {
        $postData = [
            'HANDLER_URL' => $request->getPost('HANDLER_URL'),
            'TITLE' => $request->getPost('TITLE'),
            'WIDTH' => $request->getPost('WIDTH'),
            'BUTTON_TEXT' => $request->getPost('BUTTON_TEXT'),
            'BUTTON_SIZE' => $request->getPost('BUTTON_SIZE'),
            'ACTIVE' => $request->getPost('ACTIVE'),
        ];
        $validation = $formService->validate($postData);

        if ($validation['valid']) {
            $result = $formService->save($postId, $validation['normalized']);
            if ($result->isSuccess()) {
                header('Content-Type: application/json; charset=' . SITE_CHARSET);
                echo json_encode([
                    'status' => 'success',
                    'formParams' => ['ID' => $postId, 'action' => 'edit'],
                ]);
                return;
            }
            $validation['errors'][] = implode("\n", $result->getErrorMessages());
        }

        if (!empty($validation['errors'])) {
            header('Content-Type: application/json; charset=' . SITE_CHARSET);
            echo json_encode([
                'status' => 'error',
                'message' => implode("\n", $validation['errors']),
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

$APPLICATION->SetTitle(Loc::getMessage('MY_BPBUTTON_EDIT_TAB_MAIN'));
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php';

if ($moduleRight < 'W') {
    ShowError(Loc::getMessage('ACCESS_DENIED'));
    require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';
    return;
}

$formService = new SettingsFormService();
$settingsRow = $formService->getById($id);
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

// Обработка обычного POST
if ($isPost && (isset($_POST['save']) || isset($_POST['apply']))) {
    $postData = [
        'HANDLER_URL' => $request->getPost('HANDLER_URL'),
        'TITLE' => $request->getPost('TITLE'),
        'WIDTH' => $request->getPost('WIDTH'),
        'BUTTON_TEXT' => $request->getPost('BUTTON_TEXT'),
        'BUTTON_SIZE' => $request->getPost('BUTTON_SIZE'),
        'ACTIVE' => $request->getPost('ACTIVE'),
    ];
    $validation = $formService->validate($postData);

    if ($validation['valid']) {
        $result = $formService->save($id, $validation['normalized']);
        if ($result->isSuccess()) {
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
                    'TYPE' => 'OK',
                ]);
            } else {
                CAdminMessage::ShowMessage([
                    'MESSAGE' => Loc::getMessage('MY_BPBUTTON_EDIT_SAVE_SUCCESS'),
                    'TYPE' => 'OK',
                ]);

                if (isset($_POST['save'])) {
                    LocalRedirect('my_bpbutton_bpbutton_list.php?lang=' . LANGUAGE_ID);
                    return;
                }
                LocalRedirect('my_bpbutton_bpbutton_edit.php?lang=' . LANGUAGE_ID . '&ID=' . $id);
                return;
            }
        } else {
            $errors = $result->getErrorMessages();
        }
    } else {
        $errors = $validation['errors'];
    }

    if (!empty($errors)) {
        CAdminMessage::ShowMessage([
            'MESSAGE' => Loc::getMessage('MY_BPBUTTON_EDIT_SAVE_ERROR', ['#ERROR#' => implode("\n", $errors)]),
            'TYPE' => 'ERROR',
        ]);
    }
}

$tabControl = new CAdminTabControl('tabControl', [
    [
        'DIV' => 'edit_main',
        'TAB' => Loc::getMessage('MY_BPBUTTON_EDIT_TAB_MAIN'),
        'TITLE' => Loc::getMessage('MY_BPBUTTON_EDIT_TAB_MAIN_TITLE'),
    ],
]);

?>
<form method="post" action="<?= htmlspecialcharsbx($APPLICATION->GetCurPageParam('', ['mode'])); ?>">
    <?= bitrix_sessid_post(); ?>
    <input type="hidden" name="lang" value="<?= LANGUAGE_ID ?>">
    <input type="hidden" name="ID" value="<?= (int)$id ?>">
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
    <?php
    $entityResolver = new EntityNameResolver();
    $entityResolved = $entityResolver->resolve((string)($settingsRow['ENTITY_ID'] ?? ''));
    ?>
    <tr>
        <td><?= Loc::getMessage('MY_BPBUTTON_EDIT_FIELD_ENTITY_ID'); ?>:</td>
        <td><?= htmlspecialcharsbx($entityResolved['entity_id']); ?></td>
    </tr>
    <tr>
        <td><?= Loc::getMessage('MY_BPBUTTON_EDIT_FIELD_ENTITY_NAME'); ?>:</td>
        <td><?= htmlspecialcharsbx($entityResolved['entity_name']); ?></td>
    </tr>
    <?php if (isset($entityResolved['entity_type_id']) && $entityResolved['entity_type_id'] !== null): ?>
    <tr>
        <td><?= Loc::getMessage('MY_BPBUTTON_EDIT_FIELD_ENTITY_TYPE_ID'); ?>:</td>
        <td>
            <code><?= (int)$entityResolved['entity_type_id']; ?></code>
            <div style="margin-top: 4px; color: #666; font-size: 11px;"><?= htmlspecialcharsbx(Loc::getMessage('MY_BPBUTTON_EDIT_FIELD_ENTITY_TYPE_ID_HINT') ?: ''); ?></div>
        </td>
    </tr>
    <?php endif; ?>
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
