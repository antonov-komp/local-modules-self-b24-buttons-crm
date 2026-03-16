<?php

declare(strict_types=1);

namespace My\BpButton\Service;

use Bitrix\Main\Localization\Loc;
use My\BpButton\Repository\SettingsRepository;

/**
 * Получение настроек отображения кнопки (BUTTON_TEXT, BUTTON_SIZE) по FIELD_ID
 * с кешированием в рамках одного HTTP-запроса.
 */
final class SettingsResolver
{
    private static array $cache = [];
    private SettingsRepository $repository;

    public function __construct(?SettingsRepository $repository = null)
    {
        $this->repository = $repository ?? new SettingsRepository();
    }

    /**
     * Получить текст кнопки для поля.
     *
     * @param int $fieldId ID пользовательского поля
     * @return string Текст кнопки (из настроек или fallback)
     */
    public function getButtonText(int $fieldId): string
    {
        $settings = $this->getDisplaySettings($fieldId);
        $buttonText = $settings['buttonText'] ?? '';

        if ($buttonText !== '') {
            return $buttonText;
        }

        return Loc::getMessage('BPBUTTON_USER_TYPE_BUTTON_TEXT')
            ?: Loc::getMessage('BPBUTTON_USER_TYPE_NAME')
            ?: 'Кнопка';
    }

    /**
     * Получить размер кнопки для поля.
     *
     * @param int $fieldId ID пользовательского поля
     * @return string 'default' | 'sm' | 'lg'
     */
    public function getButtonSize(int $fieldId): string
    {
        $settings = $this->getDisplaySettings($fieldId);
        return $settings['buttonSize'] ?? 'default';
    }

    /**
     * Получить все настройки отображения за один запрос (оптимизация).
     *
     * @param int $fieldId ID пользовательского поля
     * @return array{buttonText: string, buttonSize: string}
     */
    public function getDisplaySettings(int $fieldId): array
    {
        if ($fieldId <= 0) {
            return ['buttonText' => '', 'buttonSize' => 'default'];
        }

        if (!isset(self::$cache[$fieldId])) {
            try {
                $settingsRow = $this->repository->getByFieldId($fieldId);

                if ($settingsRow) {
                    $buttonText = trim((string)($settingsRow['BUTTON_TEXT'] ?? ''));
                    $size = trim((string)($settingsRow['BUTTON_SIZE'] ?? ''));
                    $buttonSize = in_array($size, ['sm', 'lg'], true) ? $size : 'default';
                } else {
                    $buttonText = '';
                    $buttonSize = 'default';
                }

                self::$cache[$fieldId] = [
                    'buttonText' => $buttonText,
                    'buttonSize' => $buttonSize,
                ];
            } catch (\Throwable $e) {
                self::$cache[$fieldId] = [
                    'buttonText' => '',
                    'buttonSize' => 'default',
                ];
            }
        }

        return self::$cache[$fieldId];
    }
}
