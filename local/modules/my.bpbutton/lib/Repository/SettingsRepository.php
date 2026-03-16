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
     * Проверить, нужно ли скрывать вкладку «Бизнес-процессы» для сущности.
     *
     * @param string $entityId ENTITY_ID (CRM_LEAD, CRM_DEAL, CRM_DYNAMIC_123 и т.д.)
     * @return bool
     */
    public function shouldHideBpTabForEntity(string $entityId): bool
    {
        $entityId = trim($entityId);
        if ($entityId === '') {
            return false;
        }

        $row = SettingsTable::getList([
            'filter' => [
                '=ENTITY_ID' => $entityId,
                '=ACTION_TYPE' => 'bp_launch',
                '=HIDE_BP_TAB' => 'Y',
                '=ACTIVE' => 'Y',
            ],
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
