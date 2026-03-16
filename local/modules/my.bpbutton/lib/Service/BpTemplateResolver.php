<?php

declare(strict_types=1);

namespace My\BpButton\Service;

use Bitrix\Crm\Model\Dynamic\TypeTable;
use Bitrix\Main\Loader;
use CCrmBizProcHelper;
use CCrmOwnerType;
use CBPWorkflowTemplateLoader;
use CBPDocumentEventType;

/**
 * Сервис получения списка шаблонов бизнес-процессов по ENTITY_ID.
 *
 * Используется для подтипа «Запуск бизнес-процесса» в настройках поля bp_button_field.
 * Документация: TASK-014-bp-launch-subtype-in-field-settings.
 */
final class BpTemplateResolver
{
    /**
     * Получить список шаблонов БП для сущности.
     *
     * @param string $entityId ENTITY_ID (CRM_LEAD, CRM_DEAL, CRM_DYNAMIC_123 и т.д.)
     * @return array<int, array{ID: int, NAME: string}>
     */
    public function getTemplatesByEntityId(string $entityId): array
    {
        if (!Loader::includeModule('bizproc') || !Loader::includeModule('crm')) {
            return [];
        }

        if (!\CBPRuntime::isFeatureEnabled()) {
            return [];
        }

        $entityTypeId = $this->resolveEntityTypeId($entityId);
        if ($entityTypeId <= 0) {
            return [];
        }

        $documentType = CCrmBizProcHelper::ResolveDocumentType($entityTypeId);
        if (!$documentType || !is_array($documentType)) {
            return [];
        }

        $templates = [];
        $filter = [
            'DOCUMENT_TYPE' => $documentType,
            'ACTIVE' => 'Y',
            'IS_SYSTEM' => 'N',
            '<AUTO_EXECUTE' => CBPDocumentEventType::Automation,
        ];
        $res = CBPWorkflowTemplateLoader::getList(
            ['SORT' => 'ASC', 'NAME' => 'ASC'],
            $filter,
            false,
            false,
            ['ID', 'NAME']
        );

        while ($row = $res->Fetch()) {
            $templates[] = [
                'ID' => (int)$row['ID'],
                'NAME' => (string)($row['NAME'] ?? ''),
            ];
        }

        return $templates;
    }

    /**
     * Преобразование ENTITY_ID (строка) в entity type id (число).
     *
     * Поддерживает форматы пользовательских полей:
     * - CRM_LEAD, CRM_DEAL, CRM_CONTACT, CRM_COMPANY
     * - CRM_DYNAMIC_123 (смарт-процесс, typeId из b_crm_dynamic_type)
     * - CRM_123 (альтернативный формат для смарт-процессов)
     *
     * Публичный статический метод для использования в ButtonService и других сервисах.
     */
    public static function resolveEntityTypeIdFromEntityId(string $entityId): int
    {
        $entityId = trim($entityId);
        if ($entityId === '') {
            return 0;
        }

        $entityTypeId = CCrmOwnerType::ResolveIDByUFEntityID($entityId);
        if ($entityTypeId !== '' && $entityTypeId !== null && (int)$entityTypeId > 0) {
            return (int)$entityTypeId;
        }

        // CRM_DYNAMIC_123: ResolveIDByUFEntityID не обрабатывает этот формат
        if (preg_match('/^CRM_(?:DYNAMIC_)?(\d+)$/', $entityId, $m)) {
            $typeId = (int)$m[1];
            $row = TypeTable::getById($typeId)->fetch();
            if ($row && isset($row['ENTITY_TYPE_ID']) && (int)$row['ENTITY_TYPE_ID'] > 0) {
                return (int)$row['ENTITY_TYPE_ID'];
            }
            return $typeId;
        }

        $entityTypeId = CCrmOwnerType::ResolveID($entityId);
        return ($entityTypeId > 0) ? $entityTypeId : 0;
    }

    /**
     * @see resolveEntityTypeIdFromEntityId
     */
    private function resolveEntityTypeId(string $entityId): int
    {
        return self::resolveEntityTypeIdFromEntityId($entityId);
    }
}
