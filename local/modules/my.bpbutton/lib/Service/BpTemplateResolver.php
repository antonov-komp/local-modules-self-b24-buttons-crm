<?php

declare(strict_types=1);

namespace My\BpButton\Service;

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
        $res = CBPWorkflowTemplateLoader::getList(
            ['SORT' => 'ASC', 'NAME' => 'ASC'],
            [
                'DOCUMENT_TYPE' => $documentType,
                'ACTIVE' => 'Y',
                'IS_SYSTEM' => 'N',
                '<AUTO_EXECUTE' => CBPDocumentEventType::Automation,
            ],
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
     */
    private function resolveEntityTypeId(string $entityId): int
    {
        $entityId = trim($entityId);
        if ($entityId === '') {
            return 0;
        }

        $entityTypeId = CCrmOwnerType::ResolveID($entityId);
        return ($entityTypeId > 0) ? $entityTypeId : 0;
    }
}
