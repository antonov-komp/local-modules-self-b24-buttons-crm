<?php

declare(strict_types=1);

namespace My\BpButton\Controller;

use Bitrix\Crm\Security\EntityAuthorization;
use Bitrix\Main\Engine\Controller;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\SystemException;
use My\BpButton\Service\ButtonService;

Loc::loadMessages(__FILE__);

final class ButtonController extends Controller
{
    public function getConfigAction(string $entityId, int $elementId, int $fieldId): array
    {
        try {
            if (!check_bitrix_sessid()) {
                return $this->error('INVALID_SESSION', Loc::getMessage('MY_BPBUTTON_CTRL_INVALID_SESSION'));
            }

            if (!Loader::includeModule('crm')) {
                return $this->error('INTERNAL_ERROR', Loc::getMessage('MY_BPBUTTON_CTRL_INTERNAL_ERROR'));
            }

            if (!$this->canReadCrmEntity($entityId, $elementId)) {
                return $this->error('ACCESS_DENIED', Loc::getMessage('MY_BPBUTTON_CTRL_ACCESS_DENIED'));
            }

            if (!Loader::includeModule('my.bpbutton')) {
                return $this->error('INTERNAL_ERROR', Loc::getMessage('MY_BPBUTTON_CTRL_INTERNAL_ERROR'));
            }

            $userId = 0;
            if (isset($GLOBALS['USER']) && $GLOBALS['USER'] instanceof \CUser) {
                $userId = (int)$GLOBALS['USER']->GetID();
            }

            $service = new ButtonService();
            $result = $service->getSidePanelConfig($entityId, $elementId, $fieldId, $userId);

            if (isset($result['success']) && $result['success'] === true) {
                return $result;
            }

            if (isset($result['success']) && $result['success'] === false) {
                return $result;
            }

            return $this->error('INTERNAL_ERROR', Loc::getMessage('MY_BPBUTTON_CTRL_INTERNAL_ERROR'));
        } catch (SystemException $e) {
            return $this->error('INTERNAL_ERROR', Loc::getMessage('MY_BPBUTTON_CTRL_INTERNAL_ERROR'));
        } catch (\Throwable $e) {
            return $this->error('INTERNAL_ERROR', Loc::getMessage('MY_BPBUTTON_CTRL_INTERNAL_ERROR'));
        }
    }

    private function canReadCrmEntity(string $entityId, int $elementId): bool
    {
        if ($elementId <= 0) {
            return false;
        }

        $entityTypeId = 0;
        $normalized = mb_strtoupper(trim($entityId));
        if ($normalized !== '' && ctype_digit($normalized)) {
            $entityTypeId = (int)$normalized;
        } elseif (preg_match('~^DYNAMIC_(\d+)$~', $normalized, $m)) {
            $entityTypeId = (int)$m[1];
        } elseif (class_exists(\CCrmOwnerType::class)) {
            $entityTypeId = (int)\CCrmOwnerType::ResolveID($normalized);
        }

        if ($entityTypeId <= 0) {
            return false;
        }

        return EntityAuthorization::checkReadPermission($entityTypeId, $elementId);
    }

    /**
     * @return array{success:false, error: array{code:string, message:string}}
     */
    private function error(string $code, ?string $message): array
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

