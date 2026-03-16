<?php

declare(strict_types=1);

namespace My\BpButton\UserField;

use Bitrix\Main\Localization\Loc;
use My\BpButton\Internals\SettingsTable;
use My\BpButton\Service\SettingsResolver;
use My\BpButton\UserField\ButtonHtmlRenderer;

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
        $renderer = new ButtonHtmlRenderer(new SettingsResolver());
        return $renderer->render($field, $value, $additional ?? []);
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
            $settingsUrl = '/bitrix/admin/my_bpbutton_bpbutton_edit.php?lang=' . LANGUAGE_ID . '&FIELD_ID=' . $fieldId;
            $buttonLabel = Loc::getMessage('BPBUTTON_SETTINGS_BUTTON_LABEL') ?: 'Настроить кнопку';
            $buttonTitle = Loc::getMessage('BPBUTTON_SETTINGS_BUTTON_TITLE') ?: 'Открыть страницу настроек';
            $description = Loc::getMessage('BPBUTTON_SETTINGS_DESCRIPTION') ?: 'Настройте URL обработчика, заголовок и другие параметры.';

            $settingsRow = null;
            try {
                $settingsRow = SettingsTable::getList([
                    'filter' => ['=FIELD_ID' => $fieldId],
                    'limit' => 1,
                ])->fetch();
            } catch (\Throwable $e) {
                // Игнорируем ошибки БД
            }

            $summaryHtml = '';
            if ($settingsRow) {
                $url = trim((string)($settingsRow['HANDLER_URL'] ?? ''));
                $title = trim((string)($settingsRow['TITLE'] ?? ''));
                $active = ($settingsRow['ACTIVE'] ?? 'Y') === 'Y';
                $activeText = $active
                    ? (Loc::getMessage('BPBUTTON_SETTINGS_ACTIVE') ?: 'Активна')
                    : (Loc::getMessage('BPBUTTON_SETTINGS_INACTIVE') ?: 'Не активна');

                $currentLabel = Loc::getMessage('BPBUTTON_SETTINGS_CURRENT') ?: 'Текущие настройки:';
                $urlLabel = Loc::getMessage('BPBUTTON_SETTINGS_URL') ?: 'URL: %s';
                $titleLabel = Loc::getMessage('BPBUTTON_SETTINGS_TITLE') ?: 'Заголовок: %s';

                $summaryHtml = '<div style="margin-top: 12px; padding: 10px; background: #f9f9f9; border-radius: 4px; font-size: 12px;">';
                $summaryHtml .= '<div style="font-weight: 600; margin-bottom: 6px;">' . htmlspecialcharsbx($currentLabel) . '</div>';
                $summaryHtml .= '<div style="color: #535c69;">' . htmlspecialcharsbx(sprintf($urlLabel, $url ?: '—')) . '</div>';
                $summaryHtml .= '<div style="color: #535c69;">' . htmlspecialcharsbx(sprintf($titleLabel, $title ?: '—')) . '</div>';
                $summaryHtml .= '<div style="color: #535c69;">' . htmlspecialcharsbx($activeText) . '</div>';
                $summaryHtml .= '</div>';
            } else {
                $notConfigured = Loc::getMessage('BPBUTTON_SETTINGS_NOT_CONFIGURED') ?: 'Кнопка ещё не настроена.';
                $summaryHtml = '<div style="margin-top: 12px; color: #828b95; font-size: 12px;">' . htmlspecialcharsbx($notConfigured) . '</div>';
            }

            return sprintf(
                '<tr>
                    <td colspan="2">
                        <div style="margin: 10px 0;">
                            <a href="%s" target="_blank" class="adm-btn" title="%s">%s</a>
                            <span style="margin-left: 10px; color: #535c69; font-size: 12px;">%s</span>
                            %s
                        </div>
                    </td>
                </tr>',
                htmlspecialcharsbx($settingsUrl),
                htmlspecialcharsbx($buttonTitle),
                htmlspecialcharsbx($buttonLabel),
                htmlspecialcharsbx($description),
                $summaryHtml
            );
        }

        $beforeCreate = Loc::getMessage('BPBUTTON_SETTINGS_BEFORE_CREATE') ?: 'После создания поля вы сможете настроить параметры кнопки.';
        return '<tr><td colspan="2"><div style="margin: 10px 0; color: #535c69; font-size: 12px;">' . htmlspecialcharsbx($beforeCreate) . '</div></td></tr>';
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

