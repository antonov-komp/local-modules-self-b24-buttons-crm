<?php

declare(strict_types=1);

namespace My\BpButton\Service;

use Bitrix\Crm\Model\Dynamic\TypeTable;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;

/**
 * Сервис для получения человекочитаемого названия сущности по ENTITY_ID.
 *
 * Поддерживает:
 * - Смарт-процессы: CRM_DYNAMIC_{typeId} или CRM_{typeId} — название из TypeTable.TITLE
 * - Стандартные сущности CRM (лиды, сделки, контакты, компании) — локализованные названия
 *
 * Формат CRM_{id} используется для userFieldEntityId смарт-процессов (ID из b_crm_dynamic_type).
 *
 * @see https://dev.1c-bitrix.ru/api_d7/bitrix/crm/model/dynamic/typetable/
 * @see https://training.bitrix24.com/api_d7/bitrix/crm/crm_owner_type/identifiers.php
 */
class EntityNameResolver
{
    /** Маппинг ENTITY_ID → ключ Loc::getMessage в модуле crm */
    private static array $entityLocKeys = [
        'CRM_LEAD'    => 'CRM_COMMON_LEADS',
        'CRM_DEAL'    => 'CRM_COMMON_DEALS',
        'CRM_CONTACT' => 'CRM_COMMON_CONTACTS',
        'CRM_COMPANY' => 'CRM_COMMON_COMPANIES',
    ];

    /** Entity type ID для стандартных сущностей CRM (CCrmOwnerType) */
    private static array $entityTypeIds = [
        'CRM_LEAD'    => 1,
        'CRM_DEAL'    => 2,
        'CRM_CONTACT' => 3,
        'CRM_COMPANY' => 4,
    ];

    /**
     * Разрешает ENTITY_ID в человекочитаемое название и entity type ID.
     *
     * @param string $entityId ENTITY_ID (например, CRM_DYNAMIC_123, CRM_LEAD, CRM_4)
     * @return array{entity_id: string, entity_name: string, entity_type_id: int|null}
     */
    public function resolve(string $entityId): array
    {
        $entityId = trim($entityId);
        if ($entityId === '') {
            return ['entity_id' => '', 'entity_name' => '', 'entity_type_id' => null];
        }

        // Смарт-процессы: CRM_DYNAMIC_123 или CRM_4 (userFieldEntityId для SPA)
        if (Loader::includeModule('crm') && preg_match('/^CRM_(?:DYNAMIC_)?(\d+)$/', $entityId, $m)) {
            $typeId = (int)$m[1];
            $row = TypeTable::getById($typeId)->fetch();
            $entityName = $row ? (string)($row['TITLE'] ?? $entityId) : $entityId;
            $entityTypeId = $row ? (int)($row['ENTITY_TYPE_ID'] ?? 0) : null;
            return [
                'entity_id'      => $entityId,
                'entity_name'    => $entityName,
                'entity_type_id' => $entityTypeId > 0 ? $entityTypeId : null,
            ];
        }

        // Стандартные сущности CRM
        if (isset(self::$entityLocKeys[$entityId]) && Loader::includeModule('crm')) {
            $langPath = $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/crm/lib/Service/Localization.php';
            if (file_exists($langPath)) {
                Loc::loadMessages($langPath);
            }
            $entityName = Loc::getMessage(self::$entityLocKeys[$entityId]) ?: $entityId;
            return [
                'entity_id'      => $entityId,
                'entity_name'    => $entityName,
                'entity_type_id' => self::$entityTypeIds[$entityId] ?? null,
            ];
        }

        return ['entity_id' => $entityId, 'entity_name' => $entityId, 'entity_type_id' => null];
    }
}
