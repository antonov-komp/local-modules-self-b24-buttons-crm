<?php

declare(strict_types=1);

namespace My\BpButton\UserField;

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\UI\Extension;
use Bitrix\Main\UserTable;
use My\BpButton\Internals\SettingsTable;

Loc::loadMessages(__FILE__);

class BpButtonUserType
{
    public const USER_TYPE_ID = 'bp_button_field';
    public const RENDER_COMPONENT = 'bitrix:main.field.bp_button_field';

    /**
     * Описание пользовательского типа поля.
     *
     * @return array
     */
    public static function getUserTypeDescription(): array
    {
        $description = (string)Loc::getMessage('BPBUTTON_USER_TYPE_NAME');
        if ($description === '') {
            $description = 'Кнопка бизнес‑процесса (bp_button_field)';
        }
        return [
            'USER_TYPE_ID'  => self::USER_TYPE_ID,
            'CLASS_NAME'    => static::class,
            'DESCRIPTION'   => $description,
            'BASE_TYPE'     => 'string',
            'RENDER_COMPONENT' => self::RENDER_COMPONENT,
            // Callback для просмотра (используется в первую очередь)
            'VIEW_CALLBACK' => [static::class, 'getPublicViewHTML'],
            'EDIT_CALLBACK' => [static::class, 'getPublicEditHTML'],
            // Методы для рендеринга поля
            'GetDBColumnType' => [static::class, 'getDBColumnType'],
            'GetPublicViewHTML' => [static::class, 'getPublicViewHTML'],
            'GetPublicEditHTML' => [static::class, 'getPublicEditHTML'],
            'GetPublicTextHTML' => [static::class, 'getPublicTextHTML'],
            'GetPublicViewHTMLMulty' => [static::class, 'getPublicViewHTMLMulty'],
            'GetPublicEditHTMLMulty' => [static::class, 'getPublicEditHTMLMulty'],
            'GetAdminListViewHTML' => [static::class, 'getAdminListViewHTML'],
            'GetEditFormHTML' => [static::class, 'getEditFormHTML'],
            'GetViewHTML' => [static::class, 'getViewHTML'],
            'GetSettingsHTML' => [static::class, 'getSettingsHTML'],
            'PrepareSettings' => [static::class, 'prepareSettings'],
            // Альтернативные методы с маленькой буквы (для совместимости)
            'getpublicviewhtml' => [static::class, 'getPublicViewHTML'],
            'getpublicedithtml' => [static::class, 'getPublicEditHTML'],
            'geteditformhtml' => [static::class, 'getEditFormHTML'],
        ];
    }

    /**
     * Тип колонки в БД для хранения служебного значения.
     *
     * @return string
     */
    public static function getDBColumnType(): string
    {
        // Для хранения служебных данных достаточно строки.
        return 'varchar(255)';
    }

    /**
     * HTML в административном списке.
     *
     * В списке показываем кнопку, а не текстовое значение.
     *
     * @param array       $field
     * @param array|null  $value
     * @param array       $row
     * @param array       $additional
     *
     * @return string
     */
    public static function getAdminListViewHTML(array $field, ?array $value, array $row, array $additional): string
    {
        // Передаем данные строки в дополнительные параметры для получения ID элемента
        if (!isset($additional['ELEMENT_ID']) && isset($row['ID'])) {
            $additional['ELEMENT_ID'] = $row['ID'];
        }
        // В списке также показываем кнопку
        return static::getPublicViewHTML($field, $value, $additional);
    }

    /**
     * HTML элемента при редактировании записи (админка / форма настроек поля).
     *
     * В админке также показываем кнопку, а не текстовое поле.
     *
     * @param array       $field
     * @param array|null  $value
     * @param array       $additional
     *
     * @return string
     */
    public static function getEditFormHTML(array $field, ?array $value, array $additional): string
    {
        // В админке также используем кнопку
        return static::getPublicViewHTML($field, $value, $additional);
    }

    /**
     * HTML для просмотра в админке (форма редактирования записи).
     *
     * @param array       $field
     * @param array|null  $value
     * @param array       $additional
     *
     * @return string
     */
    public static function getViewHTML(array $field, ?array $value, array $additional = []): string
    {
        // В админке используем тот же рендеринг, что и в публичной части
        return static::getPublicViewHTML($field, $value, $additional);
    }

    /**
     * Получение текста кнопки для поля.
     * Берёт BUTTON_TEXT из настроек (SettingsTable), при отсутствии — из языковых файлов.
     * Кеширует настройки по FIELD_ID в рамках одного запроса.
     *
     * @param array $field
     * @return string
     */
    protected static function getButtonTextForField(array $field): string
    {
        static $settingsCache = [];

        $fieldId = (int)($field['ID'] ?? 0);
        if ($fieldId <= 0) {
            return Loc::getMessage('BPBUTTON_USER_TYPE_BUTTON_TEXT')
                ?: Loc::getMessage('BPBUTTON_USER_TYPE_NAME')
                ?: 'Кнопка';
        }

        if (!isset($settingsCache[$fieldId])) {
            try {
                $settingsRow = SettingsTable::getList([
                    'select' => ['BUTTON_TEXT'],
                    'filter' => ['=FIELD_ID' => $fieldId],
                    'limit'  => 1,
                ])->fetch();
                $settingsCache[$fieldId] = $settingsRow ? trim((string)($settingsRow['BUTTON_TEXT'] ?? '')) : '';
            } catch (\Throwable $e) {
                $settingsCache[$fieldId] = '';
            }
        }

        $buttonText = $settingsCache[$fieldId];
        if ($buttonText !== '') {
            return $buttonText;
        }

        return Loc::getMessage('BPBUTTON_USER_TYPE_BUTTON_TEXT')
            ?: Loc::getMessage('BPBUTTON_USER_TYPE_NAME')
            ?: 'Кнопка';
    }

    /**
     * Получение размера кнопки для поля.
     * Берёт BUTTON_SIZE из настроек: default, sm, lg.
     *
     * @param array $field
     * @return string
     */
    protected static function getButtonSizeForField(array $field): string
    {
        static $settingsCache = [];

        $fieldId = (int)($field['ID'] ?? 0);
        if ($fieldId <= 0) {
            return 'default';
        }

        if (!isset($settingsCache[$fieldId])) {
            try {
                $settingsRow = SettingsTable::getList([
                    'select' => ['BUTTON_SIZE'],
                    'filter' => ['=FIELD_ID' => $fieldId],
                    'limit'  => 1,
                ])->fetch();
                $size = $settingsRow ? trim((string)($settingsRow['BUTTON_SIZE'] ?? '')) : '';
                $settingsCache[$fieldId] = in_array($size, ['sm', 'lg'], true) ? $size : 'default';
            } catch (\Throwable $e) {
                $settingsCache[$fieldId] = 'default';
            }
        }

        return $settingsCache[$fieldId];
    }

    /**
     * Публичное представление поля в карточке CRM — кнопка Bitrix UI.
     *
     * @param array       $field
     * @param array|null  $value
     * @param array       $additional
     *
     * @return string
     */
    public static function getPublicViewHTML(array $field, ?array $value = null, array $additional = []): string
    {
        // Нормализуем параметры - Bitrix24 может передавать их в разных форматах
        // VIEW_CALLBACK передает только $field и $additional, без $value
        if ($value === null) {
            // Если значение не передано, пытаемся получить его из поля
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
        
        // Подключаем стандартные UI‑стили и JS-логику кнопки только там, где реально отрисовано поле.
        if (class_exists(Extension::class)) {
            try {
                Extension::load('ui.buttons');
                Extension::load('my_bpbutton.button');
            } catch (\Exception $e) {
                // Игнорируем ошибки загрузки расширений
            }
        }

        // Текст кнопки: из настроек (BUTTON_TEXT) или fallback на языковые файлы
        $buttonText = static::getButtonTextForField($field);

        // Размер кнопки: из настроек (BUTTON_SIZE)
        $buttonSize = static::getButtonSizeForField($field);
        $sizeStyle = '';
        if ($buttonSize === 'sm') {
            $sizeStyle = 'padding: 4px 12px; font-size: 12px;';
        } elseif ($buttonSize === 'lg') {
            $sizeStyle = 'padding: 12px 28px; font-size: 16px;';
        }

        // Получаем ID сущности из поля или из дополнительных параметров
        $entityId = (string)($field['ENTITY_ID'] ?? $additional['ENTITY_ID'] ?? '');
        $fieldId = (string)($field['ID'] ?? '');
        
        // Получаем ID элемента из разных источников
        $elementId = '';
        if (!empty($additional['ELEMENT_ID'])) {
            $elementId = (string)$additional['ELEMENT_ID'];
        } elseif (!empty($additional['VALUE'])) {
            $elementId = (string)$additional['VALUE'];
        } elseif (!empty($row['ID'] ?? null)) {
            $elementId = (string)$row['ID'];
        } elseif (!empty($_REQUEST['ID'])) {
            $elementId = (string)$_REQUEST['ID'];
        } elseif (!empty($GLOBALS['APPLICATION']->GetCurPageParam())) {
            // Пытаемся извлечь ID из URL
            $url = $GLOBALS['APPLICATION']->GetCurPageParam();
            if (preg_match('/[?&]ID=(\d+)/', $url, $matches)) {
                $elementId = $matches[1];
            }
        }
        
        $userId = '';

        // Получаем ID пользователя
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

        // Формируем HTML кнопки
        $buttonId = 'bpbutton_' . uniqid();
        $attributes = [
            'type="button"',
            'id="' . htmlspecialcharsbx($buttonId) . '"',
            'class="ui-btn ui-btn-primary js-bpbutton-field"',
            'data-editor-control-type="button"',
            'data-entity-id="' . htmlspecialcharsbx($entityId) . '"',
            'data-element-id="' . htmlspecialcharsbx($elementId) . '"',
            'data-field-id="' . htmlspecialcharsbx($fieldId) . '"',
            'data-user-id="' . htmlspecialcharsbx($userId) . '"',
        ];
        if ($sizeStyle !== '') {
            $attributes[] = 'style="' . htmlspecialcharsbx($sizeStyle) . '"';
        }

        // Подключаем расширения и добавляем скрипт инициализации
        $initScript = '';
        if (class_exists(Extension::class)) {
            try {
                Extension::load('ui.buttons');
                Extension::load('my_bpbutton.button');
                
                // Скрипт для инициализации кнопки
                $initScript = '<script>
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
            } catch (\Exception $e) {
                // Игнорируем ошибки
            }
        }

        // Формируем HTML кнопки
        $html = sprintf(
            '<div class="bpbutton-field-wrapper" data-field-type="bp_button_field">
                <button %s>%s</button>
            </div>%s',
            implode(' ', $attributes),
            htmlspecialcharsbx($buttonText),
            $initScript
        );
        
        return $html;
    }

    /**
     * Публичное представление в режиме редактирования (CRM карточка).
     *
     * Для MVP используем тот же UI, что и во "view": кнопка открывает SidePanel.
     */
    public static function getPublicEditHTML(array $field, ?array $value, array $additional = []): string
    {
        return static::getPublicViewHTML($field, $value, $additional);
    }

    /**
     * Текстовое представление (например, для некоторых списков/экспорта).
     */
    public static function getPublicTextHTML(array $field, ?array $value, array $additional = []): string
    {
        return htmlspecialcharsbx(Loc::getMessage('BPBUTTON_USER_TYPE_BUTTON_TEXT') ?: (Loc::getMessage('BPBUTTON_USER_TYPE_NAME') ?: 'Кнопка'));
    }

    /**
     * Multy-варианты: у нас поле логически одиночное, поэтому рендерим как одиночное.
     */
    public static function getPublicViewHTMLMulty(array $field, ?array $value, array $additional = []): string
    {
        return static::getPublicViewHTML($field, $value, $additional);
    }

    public static function getPublicEditHTMLMulty(array $field, ?array $value, array $additional = []): string
    {
        return static::getPublicEditHTML($field, $value, $additional);
    }

    /**
     * HTML настроек поля в админке (форма создания/редактирования поля).
     *
     * Добавляем ссылку на страницу настроек модуля для управления кнопкой.
     *
     * @param array $field
     * @param string $htmlControlName
     * @param array $additional
     *
     * @return string
     */
    public static function getSettingsHTML(array $field, string $htmlControlName, array $additional): string
    {
        $fieldId = (int)($field['ID'] ?? 0);
        
        if ($fieldId > 0) {
            // Если поле уже создано, показываем ссылку на настройки (передаём FIELD_ID для поиска записи SettingsTable)
            $settingsUrl = '/bitrix/admin/my_bpbutton_bpbutton_list.php?lang=' . LANGUAGE_ID . '&FIELD_ID=' . $fieldId . '&action=edit';
            
            return sprintf(
                '<tr>
                    <td colspan="2">
                        <div style="margin: 10px 0;">
                            <a href="%s" target="_blank" class="adm-btn">Настроить кнопку</a>
                            <span style="margin-left: 10px; color: #666;">
                                Настройте URL обработчика, заголовок и другие параметры кнопки
                            </span>
                        </div>
                    </td>
                </tr>',
                htmlspecialcharsbx($settingsUrl)
            );
        }
        
        return '<tr><td colspan="2"><div style="margin: 10px 0; color: #666;">После создания поля вы сможете настроить параметры кнопки в разделе настроек модуля.</div></td></tr>';
    }

    /**
     * Подготовка настроек поля перед сохранением.
     *
     * Bitrix24 передает только один параметр - массив $field,
     * в котором настройки находятся в ключе 'SETTINGS'.
     *
     * @param array $field Массив с данными поля, включая 'SETTINGS'
     *
     * @return array Массив настроек
     */
    public static function prepareSettings(array $field): array
    {
        // Извлекаем настройки из поля, если они есть
        $settings = $field['SETTINGS'] ?? [];
        
        // Возвращаем настройки как есть, без изменений.
        return is_array($settings) ? $settings : [];
    }

    /**
     * Рендеринг поля через компонент (новый подход D7).
     *
     * Используется Bitrix24 для рендеринга поля через компонент.
     * Для режима main.admin_settings используется getSettingsHTML — компонент не имеет шаблона.
     *
     * @param array $userField
     * @param array|null $additionalParameters
     * @return string
     */
    public static function renderField(array $userField, ?array $additionalParameters = []): string
    {
        $additionalParameters = $additionalParameters ?? [];
        $mode = $additionalParameters['mode'] ?? '';

        // Режим настроек поля: компонент не имеет шаблона main.admin_settings, используем getSettingsHTML
        if ($mode === 'main.admin_settings') {
            $htmlControlName = $additionalParameters['NAME'] ?? 'settings';
            return static::getSettingsHTML($userField, $htmlControlName, $additionalParameters);
        }

        global $APPLICATION;

        // Используем компонент для рендеринга (как в BaseType::getHtml)
        ob_start();
        $APPLICATION->IncludeComponent(
            self::RENDER_COMPONENT,
            '',
            [
                '~userField' => $userField,
                'additionalParameters' => $additionalParameters,
            ],
            null,
            ['HIDE_ICONS' => 'Y']
        );
        return ob_get_clean();
    }

    /**
     * Рендеринг поля в режиме просмотра (новый подход D7).
     *
     * @param array $userField
     * @param array|null $additionalParameters
     * @return string
     */
    public static function renderView(array $userField, ?array $additionalParameters = []): string
    {
        $additionalParameters['mode'] = 'main.view';
        return static::renderField($userField, $additionalParameters);
    }

    /**
     * Рендеринг поля в режиме редактирования (новый подход D7).
     *
     * @param array $userField
     * @param array|null $additionalParameters
     * @return string
     */
    public static function renderEdit(array $userField, ?array $additionalParameters = []): string
    {
        $additionalParameters['mode'] = 'main.edit';
        return static::renderField($userField, $additionalParameters);
    }
}

