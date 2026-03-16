<?php

declare(strict_types=1);

namespace My\BpButton\UserField;

use Bitrix\Main\UI\Extension;
use Bitrix\Main\UserTable;
use My\BpButton\Service\SettingsResolver;

/**
 * Генерация HTML кнопки для отображения в карточке CRM, админ-списке, форме редактирования.
 */
final class ButtonHtmlRenderer
{
    public function __construct(
        private readonly SettingsResolver $settingsResolver
    ) {
    }

    /**
     * Отрисовать кнопку.
     *
     * @param array       $field    Данные поля (ENTITY_ID, ID, VALUE и т.д.)
     * @param array|null  $value    Значение поля (может быть null)
     * @param array       $additional Доп. параметры (ELEMENT_ID, ENTITY_ID, USER и т.д.)
     * @return string HTML кнопки с обёрткой и init script
     */
    public function render(array $field, ?array $value, array $additional = []): string
    {
        $normalized = $this->normalizeParameters($field, $value, $additional);
        $value = $normalized['value'];
        $additional = $normalized['additional'];

        if (class_exists(Extension::class)) {
            try {
                Extension::load('ui.buttons');
                Extension::load('my_bpbutton.button');
            } catch (\Exception $e) {
                // Игнорируем ошибки загрузки расширений
            }
        }

        $fieldId = (int)($field['ID'] ?? 0);
        $buttonText = $this->settingsResolver->getButtonText($fieldId);
        $buttonSize = $this->settingsResolver->getButtonSize($fieldId);

        $sizeStyle = '';
        if ($buttonSize === 'sm') {
            $sizeStyle = 'padding: 4px 12px; font-size: 12px;';
        } elseif ($buttonSize === 'lg') {
            $sizeStyle = 'padding: 12px 28px; font-size: 16px;';
        }

        $context = $this->resolveContext($field, $additional);
        $buttonId = 'bpbutton_' . uniqid();

        $html = $this->buildButtonHtml($buttonId, $buttonText, $sizeStyle, $context);
        $initScript = $this->buildInitScript($buttonId);

        return $html . $initScript;
    }

    /**
     * Нормализовать $value и $additional (защита от разных форматов Bitrix).
     *
     * @return array{value: array, additional: array}
     */
    protected function normalizeParameters(array $field, mixed $value, array $additional): array
    {
        if ($value === null) {
            if (isset($field['VALUE'])) {
                if (is_array($field['VALUE'])) {
                    $value = ['VALUE' => reset($field['VALUE'])];
                } else {
                    $value = ['VALUE' => $field['VALUE']];
                }
            } else {
                $value = [];
            }
        }
        if (!is_array($additional)) {
            $additional = [];
        }
        if (!is_array($field)) {
            $field = [];
        }

        return [
            'value' => is_array($value) ? $value : [],
            'additional' => $additional,
        ];
    }

    /**
     * Извлечь контекст для data-атрибутов: entityId, elementId, fieldId, userId.
     *
     * @return array{entityId: string, elementId: string, fieldId: string, userId: string}
     */
    protected function resolveContext(array $field, array $additional): array
    {
        $entityId = (string)($field['ENTITY_ID'] ?? $additional['ENTITY_ID'] ?? '');
        $fieldId = (string)($field['ID'] ?? '');

        $elementId = '';
        // ENTITY_VALUE_ID — ID документа в карточке CRM (lead, deal, contact и т.д.)
        if (!empty($field['ENTITY_VALUE_ID']) && (int)$field['ENTITY_VALUE_ID'] > 0) {
            $elementId = (string)$field['ENTITY_VALUE_ID'];
        } elseif (!empty($additional['ENTITY_VALUE_ID']) && (int)$additional['ENTITY_VALUE_ID'] > 0) {
            $elementId = (string)$additional['ENTITY_VALUE_ID'];
        } elseif (!empty($additional['ELEMENT_ID'])) {
            $elementId = (string)$additional['ELEMENT_ID'];
        } elseif (!empty($additional['VALUE'])) {
            $elementId = (string)$additional['VALUE'];
        } elseif (!empty($_REQUEST['ID'])) {
            $elementId = (string)$_REQUEST['ID'];
        } elseif (isset($GLOBALS['APPLICATION']) && $GLOBALS['APPLICATION'] instanceof \CMain) {
            $url = $GLOBALS['APPLICATION']->GetCurPageParam();
            if ($url !== '' && preg_match('/[?&]ID=(\d+)/', $url, $matches)) {
                $elementId = $matches[1];
            }
        }

        $userId = '';
        if (is_array($additional['USER'] ?? null) && isset($additional['USER']['ID'])) {
            $userId = (string)$additional['USER']['ID'];
        } elseif (isset($GLOBALS['USER']) && $GLOBALS['USER'] instanceof \CUser) {
            $userId = (string)$GLOBALS['USER']->GetID();
        } else {
            try {
                $user = UserTable::getList([
                    'select' => ['ID'],
                    'limit' => 1,
                ])->fetch();

                if ($user && isset($user['ID'])) {
                    $userId = (string)$user['ID'];
                }
            } catch (\Exception $e) {
                // Игнорируем ошибки получения пользователя
            }
        }

        return [
            'entityId' => $entityId,
            'elementId' => $elementId,
            'fieldId' => $fieldId,
            'userId' => $userId,
        ];
    }

    /**
     * Собрать HTML кнопки (button + wrapper).
     */
    protected function buildButtonHtml(
        string $buttonId,
        string $buttonText,
        string $sizeStyle,
        array $context
    ): string {
        $attributes = [
            'type="button"',
            'id="' . htmlspecialcharsbx($buttonId) . '"',
            'class="ui-btn ui-btn-primary js-bpbutton-field"',
            'data-editor-control-type="button"',
            'data-entity-id="' . htmlspecialcharsbx($context['entityId']) . '"',
            'data-element-id="' . htmlspecialcharsbx($context['elementId']) . '"',
            'data-field-id="' . htmlspecialcharsbx($context['fieldId']) . '"',
            'data-user-id="' . htmlspecialcharsbx($context['userId']) . '"',
        ];
        if ($sizeStyle !== '') {
            $attributes[] = 'style="' . htmlspecialcharsbx($sizeStyle) . '"';
        }

        return sprintf(
            '<div class="bpbutton-field-wrapper" data-field-type="bp_button_field">
                <button %s>%s</button>
            </div>',
            implode(' ', $attributes),
            htmlspecialcharsbx($buttonText)
        );
    }

    /**
     * Собрать inline script для инициализации BX.MyBpButton.Button.bind().
     */
    protected function buildInitScript(string $buttonId): string
    {
        if (!class_exists(Extension::class)) {
            return '';
        }

        try {
            Extension::load('ui.buttons');
            Extension::load('my_bpbutton.button');
        } catch (\Exception $e) {
            return '';
        }

        return '<script>
            (function() {
                function initButton() {
                    if (typeof BX === "undefined" || !BX.MyBpButton || !BX.MyBpButton.Button) {
                        setTimeout(initButton, 100);
                        return;
                    }
                    var button = document.getElementById("' . htmlspecialcharsbx($buttonId) . '");
                    if (button && !button.dataset.bpbuttonInit) {
                        BX.MyBpButton.Button.bind(button);
                    }
                }
                if (typeof BX !== "undefined" && BX.ready) {
                    BX.ready(initButton);
                } else {
                    setTimeout(initButton, 100);
                }
            })();
        </script>';
    }
}
