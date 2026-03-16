<?php

declare(strict_types=1);

namespace My\BpButton\Repository;

use My\BpButton\Internals\SettingsTable;

/**
 * Централизованный доступ к SettingsTable для операций чтения.
 *
 * Устраняет дублирование getList/getByPrimary в ButtonService, SettingsResolver.
 * Кеширование в рамках одного HTTP-запроса.
 */
final class SettingsRepository
{
    private static array $cacheByFieldId = [];
    private static array $cacheById = [];

    /**
     * Получить запись настроек по FIELD_ID.
     *
     * @param int $fieldId ID пользовательского поля
     * @return array|null Запись или null
     */
    public function getByFieldId(int $fieldId): ?array
    {
        if ($fieldId <= 0) {
            return null;
        }

        if (!isset(self::$cacheByFieldId[$fieldId])) {
            $row = SettingsTable::getList([
                'filter' => ['=FIELD_ID' => $fieldId],
                'limit' => 1,
            ])->fetch();

            self::$cacheByFieldId[$fieldId] = $row ?: null;
        }

        return self::$cacheByFieldId[$fieldId];
    }

    /**
     * Получить запись настроек по ID.
     *
     * @param int $id ID записи в my_bpbutton_settings
     * @return array|null Запись или null
     */
    public function getById(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        if (!isset(self::$cacheById[$id])) {
            $row = SettingsTable::getList([
                'filter' => ['=ID' => $id],
                'limit' => 1,
            ])->fetch();

            self::$cacheById[$id] = $row ?: null;
        }

        return self::$cacheById[$id];
    }

    /**
     * Варианты ENTITY_ID для поиска.
     * - CRM_DYNAMIC_X ↔ CRM_X (смарт-процессы: в URL type/X, в полях — CRM_X)
     * - CRM_LEAD ↔ CRM_1 и т.д. (стандартные сущности)
     *
     * @return string[]
     */
    private static function getEntityIdVariants(string $entityId): array
    {
        $entityId = trim($entityId);
        if ($entityId === '') {
            return [];
        }
        $variants = [$entityId];

        // Смарт-процессы: CRM_DYNAMIC_4 ↔ CRM_4
        if (preg_match('/^CRM_DYNAMIC_(\d+)$/', $entityId, $m)) {
            $variants[] = 'CRM_' . $m[1];
        } elseif (preg_match('/^CRM_(\d+)$/', $entityId, $m)) {
            $variants[] = 'CRM_DYNAMIC_' . $m[1];
        }

        // Стандартные сущности (лид, сделка, контакт, компания)
        $map = [
            'CRM_LEAD' => 'CRM_1',
            'CRM_DEAL' => 'CRM_2',
            'CRM_CONTACT' => 'CRM_3',
            'CRM_COMPANY' => 'CRM_4',
            'CRM_1' => 'CRM_LEAD',
            'CRM_2' => 'CRM_DEAL',
            'CRM_3' => 'CRM_CONTACT',
            'CRM_4' => 'CRM_COMPANY',
        ];
        if (isset($map[$entityId])) {
            $variants[] = $map[$entityId];
        }

        return array_unique($variants);
    }

    /**
     * Проверить, нужно ли скрывать вкладку «Бизнес-процессы» для сущности.
     *
     * @param string $entityId ENTITY_ID (CRM_LEAD, CRM_4, CRM_DYNAMIC_123 и т.д.)
     * @return bool
     */
    public function shouldHideBpTabForEntity(string $entityId): bool
    {
        $variants = self::getEntityIdVariants($entityId);
        if (empty($variants)) {
            return false;
        }

        $filter = [
            '=ACTION_TYPE' => 'bp_launch',
            '=HIDE_BP_TAB' => 'Y',
            '=ACTIVE' => 'Y',
        ];
        if (count($variants) === 1) {
            $filter['=ENTITY_ID'] = $variants[0];
        } else {
            $filter['@ENTITY_ID'] = $variants;
        }

        $row = SettingsTable::getList([
            'filter' => $filter,
            'limit' => 1,
        ])->fetch();

        return $row !== false;
    }

    /**
     * Сброс кеша (для тестов или при необходимости).
     */
    public static function clearCache(): void
    {
        self::$cacheByFieldId = [];
        self::$cacheById = [];
    }
}
