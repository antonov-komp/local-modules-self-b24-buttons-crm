<?php

declare(strict_types=1);

namespace My\BpButton\Helper;

use Bitrix\Crm\Security\EntityAuthorization;
use My\BpButton\Service\BpTemplateResolver;

/**
 * Проверка прав на чтение CRM-сущности.
 *
 * Вынесено из ButtonController для переиспользования и тестируемости.
 */
final class CrmAccessChecker
{
    /**
     * Проверить право на чтение CRM-сущности.
     *
     * @param string $entityId CRM_LEAD, CRM_DEAL, CRM_DYNAMIC_123 и т.д.
     * @param int $elementId ID элемента
     * @return bool
     */
    public function canRead(string $entityId, int $elementId): bool
    {
        if ($elementId <= 0) {
            return false;
        }

        $entityTypeId = BpTemplateResolver::resolveEntityTypeIdFromEntityId($entityId);
        if ($entityTypeId <= 0) {
            return false;
        }

        return EntityAuthorization::checkReadPermission($entityTypeId, $elementId);
    }
}
