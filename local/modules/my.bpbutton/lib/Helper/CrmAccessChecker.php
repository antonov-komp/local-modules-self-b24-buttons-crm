<?php

declare(strict_types=1);

namespace My\BpButton\Helper;

use Bitrix\Crm\Security\EntityAuthorization;

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
     * @param string $entityId LEAD, DEAL, CONTACT, DYNAMIC_123 и т.д.
     * @param int $elementId ID элемента
     * @return bool
     */
    public function canRead(string $entityId, int $elementId): bool
    {
        if ($elementId <= 0) {
            return false;
        }

        $entityTypeId = $this->resolveEntityTypeId($entityId);
        if ($entityTypeId <= 0) {
            return false;
        }

        return EntityAuthorization::checkReadPermission($entityTypeId, $elementId);
    }

    /**
     * Преобразовать entityId в числовой entityTypeId.
     */
    private function resolveEntityTypeId(string $entityId): int
    {
        $normalized = mb_strtoupper(trim($entityId));
        if ($normalized === '') {
            return 0;
        }

        if (ctype_digit($normalized)) {
            return (int)$normalized;
        }

        if (preg_match('~^DYNAMIC_(\d+)$~', $normalized, $m)) {
            return (int)$m[1];
        }

        if (class_exists(\CCrmOwnerType::class)) {
            return (int)\CCrmOwnerType::ResolveID($normalized);
        }

        return 0;
    }
}
