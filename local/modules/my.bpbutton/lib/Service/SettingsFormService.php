<?php

declare(strict_types=1);

namespace My\BpButton\Service;

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\ORM\Data\UpdateResult;
use My\BpButton\Internals\SettingsTable;
use My\BpButton\Repository\SettingsRepository;

Loc::loadMessages(dirname(__DIR__, 2) . '/admin/bpbutton_list.php');

/**
 * Сервис валидации и сохранения настроек формы кнопки БП.
 *
 * Валидация полей HANDLER_URL, WIDTH, BUTTON_SIZE.
 * Сохранение в SettingsTable.
 */
class SettingsFormService
{
    private const ALLOWED_BUTTON_SIZES = ['default', 'sm', 'lg'];
    private SettingsRepository $repository;

    public function __construct(?SettingsRepository $repository = null)
    {
        $this->repository = $repository ?? new SettingsRepository();
    }

    /**
     * Валидация данных формы.
     *
     * @param array $data Данные из POST: HANDLER_URL, TITLE, WIDTH, BUTTON_TEXT, BUTTON_SIZE, ACTIVE
     * @return array{valid: bool, errors: string[], normalized: array}
     */
    public function validate(array $data): array
    {
        $errors = [];

        $actionType = trim((string)($data['ACTION_TYPE'] ?? ''));
        if (!in_array($actionType, ['url', 'bp_launch', 'bp_launch_with_params'], true)) {
            $actionType = 'url';
        }

        $handlerUrl = trim((string)($data['HANDLER_URL'] ?? ''));
        if ($actionType === 'url') {
            if ($handlerUrl === '') {
                $errors[] = Loc::getMessage('MY_BPBUTTON_EDIT_ERROR_HANDLER_REQUIRED') ?: 'Укажите URL обработчика.';
            } elseif (!preg_match('~^https?://~i', $handlerUrl) && ($handlerUrl[0] ?? '') !== '/') {
                $errors[] = Loc::getMessage('MY_BPBUTTON_EDIT_ERROR_INVALID_URL');
            }
        }

        $bpTemplateId = isset($data['BP_TEMPLATE_ID']) ? (int)$data['BP_TEMPLATE_ID'] : null;
        if (($actionType === 'bp_launch' || $actionType === 'bp_launch_with_params') && ($bpTemplateId === null || $bpTemplateId <= 0)) {
            $errors[] = Loc::getMessage('MY_BPBUTTON_EDIT_ERROR_BP_TEMPLATE_REQUIRED') ?: 'Выберите шаблон бизнес-процесса.';
        }

        $paramName = trim((string)($data['PARAM_NAME'] ?? ''));
        $paramTitle = trim((string)($data['PARAM_TITLE'] ?? ''));
        if ($actionType === 'bp_launch_with_params') {
            if ($paramName === '') {
                $errors[] = Loc::getMessage('MY_BPBUTTON_EDIT_ERROR_PARAM_NAME_REQUIRED') ?: 'Укажите имя параметра.';
            } elseif (!preg_match('~^[A-Za-z][A-Za-z0-9_]*$~', $paramName)) {
                $errors[] = Loc::getMessage('MY_BPBUTTON_EDIT_ERROR_PARAM_NAME_INVALID') ?: 'Имя параметра должно содержать латиницу, цифры и _. Первый символ — буква.';
            }

            if ($paramTitle === '') {
                $errors[] = Loc::getMessage('MY_BPBUTTON_EDIT_ERROR_PARAM_TITLE_REQUIRED') ?: 'Укажите название параметра.';
            }
        } else {
            $paramName = '';
            $paramTitle = '';
        }

        $width = trim((string)($data['WIDTH'] ?? ''));
        if ($width !== '' && !preg_match('~^\d+$~', $width) && !preg_match('~^\d+%$~', $width)) {
            $errors[] = Loc::getMessage('MY_BPBUTTON_EDIT_ERROR_INVALID_WIDTH');
        }

        $buttonSize = trim((string)($data['BUTTON_SIZE'] ?? ''));
        if ($buttonSize !== '' && !in_array($buttonSize, self::ALLOWED_BUTTON_SIZES, true)) {
            $buttonSize = 'default';
        }

        $title = trim((string)($data['TITLE'] ?? ''));
        $buttonText = trim((string)($data['BUTTON_TEXT'] ?? ''));
        $active = ($data['ACTIVE'] ?? '') === 'Y' ? 'Y' : 'N';
        $hideBpTab = ($data['HIDE_BP_TAB'] ?? '') === 'Y' ? 'Y' : 'N';

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'normalized' => [
                'ACTION_TYPE' => $actionType,
                'BP_TEMPLATE_ID' => ($actionType === 'bp_launch' && $bpTemplateId > 0) ? $bpTemplateId : null,
                'HANDLER_URL' => ($actionType === 'url' && $handlerUrl !== '') ? $handlerUrl : null,
                'TITLE' => $title !== '' ? $title : null,
                'WIDTH' => $width !== '' ? $width : null,
                'BUTTON_TEXT' => $buttonText !== '' ? $buttonText : null,
                'BUTTON_SIZE' => $buttonSize !== '' ? $buttonSize : null,
                'ACTIVE' => $active,
                'PARAM_NAME' => $paramName !== '' ? $paramName : null,
                'PARAM_TITLE' => $paramTitle !== '' ? $paramTitle : null,
                'HIDE_BP_TAB' => in_array($actionType, ['bp_launch', 'bp_launch_with_params'], true) ? $hideBpTab : 'N',
            ],
        ];
    }

    /**
     * Сохранение настроек в SettingsTable.
     *
     * @param int $id ID записи в my_bpbutton_settings
     * @param array $data Нормализованные данные (результат validate)
     * @return UpdateResult
     */
    public function save(int $id, array $data): UpdateResult
    {
        $updateData = [
            'ACTION_TYPE' => $data['ACTION_TYPE'] ?? 'url',
            'BP_TEMPLATE_ID' => $data['BP_TEMPLATE_ID'] ?? null,
            'PARAM_NAME' => $data['PARAM_NAME'] ?? null,
            'PARAM_TITLE' => $data['PARAM_TITLE'] ?? null,
            'HANDLER_URL' => $data['HANDLER_URL'],
            'TITLE' => $data['TITLE'],
            'WIDTH' => $data['WIDTH'],
            'BUTTON_TEXT' => $data['BUTTON_TEXT'],
            'BUTTON_SIZE' => $data['BUTTON_SIZE'],
            'ACTIVE' => $data['ACTIVE'],
            'HIDE_BP_TAB' => $data['HIDE_BP_TAB'] ?? 'N',
            'UPDATED_AT' => new \Bitrix\Main\Type\DateTime(),
        ];

        return SettingsTable::update($id, $updateData);
    }

    /**
     * Получить запись настроек по ID с проверкой существования.
     *
     * @param int $id
     * @return array|null
     */
    public function getById(int $id): ?array
    {
        return $this->repository->getById($id);
    }

    /**
     * Получить ID записи по FIELD_ID (для редиректа с FIELD_ID).
     *
     * @param int $fieldId
     * @return int|null
     */
    public function getIdByFieldId(int $fieldId): ?int
    {
        $row = $this->repository->getByFieldId($fieldId);
        return $row && !empty($row['ID']) ? (int)$row['ID'] : null;
    }

    /**
     * Переключение активности записи (для inline-переключателя в списке).
     *
     * @param int $id ID записи
     * @param string $active 'Y' или 'N'
     * @return UpdateResult
     */
    public function toggleActive(int $id, string $active): UpdateResult
    {
        return SettingsTable::update($id, [
            'ACTIVE' => $active,
            'UPDATED_AT' => new \Bitrix\Main\Type\DateTime(),
        ]);
    }
}
