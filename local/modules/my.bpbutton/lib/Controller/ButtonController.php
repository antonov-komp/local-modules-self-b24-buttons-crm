<?php

declare(strict_types=1);

namespace My\BpButton\Controller;

use Bitrix\Main\Engine\Controller;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\SystemException;
use My\BpButton\Helper\CrmAccessChecker;
use My\BpButton\Helper\SecurityHelper;
use My\BpButton\Repository\SettingsRepository;
use My\BpButton\Service\ButtonService;

Loc::loadMessages(__FILE__);

final class ButtonController extends Controller
{
    private ?ButtonService $service = null;
    private ?CrmAccessChecker $crmAccessChecker = null;

    public function getConfigAction(string $entityId, int $elementId, int $fieldId): array
    {
        $userId = $this->getCurrentUserId();

        if (!$this->validateSession()) {
            return $this->errorResponse('INVALID_SESSION', Loc::getMessage('MY_BPBUTTON_CTRL_INVALID_SESSION'));
        }

        if (!$this->loadRequiredModules()) {
            return $this->errorResponse('INTERNAL_ERROR', Loc::getMessage('MY_BPBUTTON_CTRL_INTERNAL_ERROR'));
        }

        if (!$this->checkCrmAccess($entityId, $elementId)) {
            $this->getService()->logClick(
                [
                    'fieldId' => $fieldId,
                    'entityId' => $entityId,
                    'elementId' => $elementId,
                    'userId' => $userId,
                ],
                'ACCESS_DENIED',
                'Нет прав на чтение CRM-сущности'
            );
            return $this->errorResponse('ACCESS_DENIED', Loc::getMessage('MY_BPBUTTON_CTRL_ACCESS_DENIED'));
        }

        try {
            $result = $this->getService()->getSidePanelConfig($entityId, $elementId, $fieldId, $userId);
            $this->auditLogResult($result, $entityId, $elementId, $fieldId, $userId);
            return $result;
        } catch (SystemException $e) {
            SecurityHelper::safeLog($e, 'my.bpbutton', 'ButtonController::getConfigAction');
            $this->getService()->logClick(
                [
                    'fieldId' => $fieldId,
                    'entityId' => $entityId,
                    'elementId' => $elementId,
                    'userId' => $userId,
                ],
                'INTERNAL_ERROR',
                'Внутренняя ошибка при получении конфигурации'
            );
            return $this->errorResponse('INTERNAL_ERROR', Loc::getMessage('MY_BPBUTTON_CTRL_INTERNAL_ERROR'));
        } catch (\Throwable $e) {
            SecurityHelper::safeLog($e, 'my.bpbutton', 'ButtonController::getConfigAction');
            $this->getService()->logClick(
                [
                    'fieldId' => $fieldId,
                    'entityId' => $entityId,
                    'elementId' => $elementId,
                    'userId' => $userId,
                ],
                'INTERNAL_ERROR',
                'Внутренняя ошибка при получении конфигурации'
            );
            return $this->errorResponse('INTERNAL_ERROR', Loc::getMessage('MY_BPBUTTON_CTRL_INTERNAL_ERROR'));
        }
    }

    /**
     * TASK-014-A: проверка, нужно ли скрывать вкладку «Бизнес-процессы» для сущности.
     * Вызывается из entity-editor.js по data-entity-id кнопки на странице.
     *
     * @param string $entityId ENTITY_ID (CRM_4, CRM_LEAD и т.д.)
     * @return array{shouldHide: bool}
     */
    public function getShouldHideBpTabAction(string $entityId): array
    {
        $entityId = trim($entityId);
        $shouldHide = false;

        if ($entityId !== '' && Loader::includeModule('my.bpbutton')) {
            $repository = new SettingsRepository();
            $shouldHide = $repository->shouldHideBpTabForEntity($entityId);
        }

        return ['shouldHide' => $shouldHide];
    }

    public function startBpWithParamsAction(string $entityId, int $elementId, int $fieldId, string $value): array
    {
        $userId = $this->getCurrentUserId();

        if (!$this->validateSession()) {
            return $this->errorResponse('INVALID_SESSION', Loc::getMessage('MY_BPBUTTON_CTRL_INVALID_SESSION'));
        }

        if (!$this->loadRequiredModules()) {
            return $this->errorResponse('INTERNAL_ERROR', Loc::getMessage('MY_BPBUTTON_CTRL_INTERNAL_ERROR'));
        }

        if (!$this->checkCrmAccess($entityId, $elementId)) {
            return $this->errorResponse('ACCESS_DENIED', Loc::getMessage('MY_BPBUTTON_CTRL_ACCESS_DENIED'));
        }

        try {
            $result = $this->getService()->startBpWithParams($entityId, $elementId, $fieldId, $userId, $value);
            if (($result['success'] ?? false) === true) {
                $this->getService()->logClick(
                    [
                        'fieldId' => $fieldId,
                        'entityId' => $entityId,
                        'elementId' => $elementId,
                        'userId' => $userId,
                    ],
                    'SUCCESS',
                    null
                );
            } else {
                $this->getService()->logClick(
                    [
                        'fieldId' => $fieldId,
                        'entityId' => $entityId,
                        'elementId' => $elementId,
                        'userId' => $userId,
                    ],
                    (string)($result['error']['code'] ?? 'ERROR'),
                    (string)($result['error']['message'] ?? '')
                );
            }
            return $result;
        } catch (\Throwable $e) {
            SecurityHelper::safeLog($e, 'my.bpbutton', 'ButtonController::startBpWithParamsAction');
            return $this->errorResponse('INTERNAL_ERROR', Loc::getMessage('MY_BPBUTTON_CTRL_INTERNAL_ERROR'));
        }
    }

    private function getCurrentUserId(): int
    {
        if (isset($GLOBALS['USER']) && $GLOBALS['USER'] instanceof \CUser) {
            return (int)$GLOBALS['USER']->GetID();
        }
        return 0;
    }

    private function validateSession(): bool
    {
        return check_bitrix_sessid();
    }

    private function loadRequiredModules(): bool
    {
        return Loader::includeModule('crm') && Loader::includeModule('my.bpbutton');
    }

    private function checkCrmAccess(string $entityId, int $elementId): bool
    {
        return $this->getCrmAccessChecker()->canRead($entityId, $elementId);
    }

    /**
     * @param array $result Ответ getSidePanelConfig (success/error)
     */
    private function auditLogResult(array $result, string $entityId, int $elementId, int $fieldId, int $userId): void
    {
        $context = [
            'fieldId' => $fieldId,
            'entityId' => $entityId,
            'elementId' => $elementId,
            'userId' => $userId,
        ];

        if (isset($result['success']) && $result['success'] === true) {
            $context['settingsId'] = $result['data']['context']['settingsId'] ?? 0;
            $this->getService()->logClick($context, 'SUCCESS', null);
            return;
        }

        if (isset($result['success']) && $result['success'] === false) {
            $code = (string)($result['error']['code'] ?? 'ERROR');
            $message = (string)($result['error']['message'] ?? '');
            $this->getService()->logClick($context, $code, $message);
        }
    }

    private function getService(): ButtonService
    {
        if ($this->service === null) {
            $this->service = new ButtonService();
        }
        return $this->service;
    }

    private function getCrmAccessChecker(): CrmAccessChecker
    {
        if ($this->crmAccessChecker === null) {
            $this->crmAccessChecker = new CrmAccessChecker();
        }
        return $this->crmAccessChecker;
    }

    /**
     * @return array{success:false, error: array{code:string, message:string}}
     */
    private function errorResponse(string $code, ?string $message): array
    {
        $message = (string)($message ?: Loc::getMessage('MY_BPBUTTON_CTRL_INTERNAL_ERROR'));

        return [
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ];
    }
}
