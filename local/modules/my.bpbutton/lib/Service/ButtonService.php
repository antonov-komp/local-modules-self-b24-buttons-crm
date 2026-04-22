<?php

declare(strict_types=1);

namespace My\BpButton\Service;

use Bitrix\Main\Diag\Debug;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Type\DateTime;
use CCrmBizProcHelper;
use My\BpButton\Helper\SecurityHelper;
use My\BpButton\Service\BpTemplateResolver;
use My\BpButton\Internals\LogsTable;
use My\BpButton\Repository\SettingsRepository;

Loc::loadMessages(__FILE__);

final class ButtonService
{
    private SettingsRepository $repository;

    public function __construct(?SettingsRepository $repository = null)
    {
        $this->repository = $repository ?? new SettingsRepository();
    }

    /**
     * @return array{
     *   success: bool,
     *   data?: array,
     *   error?: array{code: string, message: string}
     * }
     */
    public function getSidePanelConfig(string $entityId, int $elementId, int $fieldId, int $userId): array
    {
        $settings = $this->repository->getByFieldId($fieldId);

        if (!$settings) {
            return $this->error('SETTINGS_NOT_FOUND', Loc::getMessage('MY_BPBUTTON_SERVICE_SETTINGS_NOT_FOUND') ?: 'Кнопка не настроена.');
        }

        if (($settings['ACTIVE'] ?? 'N') !== 'Y') {
            return $this->error('BUTTON_INACTIVE', Loc::getMessage('MY_BPBUTTON_SERVICE_BUTTON_INACTIVE') ?: 'Действие недоступно. Кнопка отключена администратором.');
        }

        $actionType = trim((string)($settings['ACTION_TYPE'] ?? ''));
        if (!in_array($actionType, ['url', 'bp_launch', 'bp_launch_with_params', 'bp_launch_with_button_params'], true)) {
            $actionType = 'url';
        }

        if ($actionType === 'bp_launch') {
            return $this->getBpLaunchConfig($entityId, $elementId, $fieldId, $userId, $settings);
        }
        if ($actionType === 'bp_launch_with_params') {
            return $this->getBpLaunchWithParamsConfig($entityId, $elementId, $fieldId, $userId, $settings);
        }
        if ($actionType === 'bp_launch_with_button_params') {
            return $this->getBpLaunchWithButtonParamsConfig($entityId, $elementId, $fieldId, $userId, $settings);
        }

        $handlerUrl = trim((string)($settings['HANDLER_URL'] ?? ''));
        if ($handlerUrl === '') {
            return $this->error('SETTINGS_NOT_FOUND', Loc::getMessage('MY_BPBUTTON_SERVICE_HANDLER_NOT_SET') ?: 'Кнопка не настроена. Обратитесь к администратору.');
        }

        $title = trim((string)($settings['TITLE'] ?? ''));
        if ($title === '') {
            $title = Loc::getMessage('MY_BPBUTTON_SERVICE_DEFAULT_TITLE') ?: 'Действие';
        }

        $width = trim((string)($settings['WIDTH'] ?? ''));
        if ($width === '') {
            $width = '70%';
        }

        $context = [
            'settingsId' => (int)($settings['ID'] ?? 0),
            'entityId' => $entityId,
            'elementId' => $elementId,
            'fieldId' => $fieldId,
            'userId' => $userId,
        ];

        $finalUrl = $this->appendContextToUrl($handlerUrl, $context);

        return [
            'success' => true,
            'data' => [
                'actionType' => 'url',
                'url' => $finalUrl,
                'title' => $title,
                'width' => $width,
                'context' => $context,
            ],
        ];
    }

    private function getBpLaunchWithButtonParamsConfig(string $entityId, int $elementId, int $fieldId, int $userId, array $settings): array
    {
        $templateId = (int)($settings['BP_TEMPLATE_ID'] ?? 0);
        if ($templateId <= 0) {
            return $this->error('SETTINGS_NOT_FOUND', Loc::getMessage('MY_BPBUTTON_SERVICE_BP_TEMPLATE_NOT_SET') ?: 'Шаблон БП не выбран. Настройте кнопку.');
        }

        $paramName = trim((string)($settings['PARAM_NAME'] ?? ''));
        $paramTitle = trim((string)($settings['PARAM_TITLE'] ?? ''));
        $rawOptions = (string)($settings['PARAM_BUTTONS'] ?? '');
        $options = [];
        if ($rawOptions !== '') {
            $decoded = json_decode($rawOptions, true);
            if (is_array($decoded)) {
                $options = array_values(array_filter(array_map(
                    static fn($v) => trim((string)$v),
                    $decoded
                ), static fn($v) => $v !== ''));
            }
        }
        if ($paramName === '' || !preg_match('~^[A-Za-z][A-Za-z0-9_]*$~', $paramName)) {
            return $this->error('SETTINGS_NOT_FOUND', Loc::getMessage('MY_BPBUTTON_SERVICE_PARAM_NAME_INVALID') ?: 'Некорректное имя параметра запуска БП.');
        }
        if ($paramTitle === '') {
            $paramTitle = Loc::getMessage('MY_BPBUTTON_SERVICE_PARAM_TITLE_DEFAULT') ?: 'Параметр';
        }
        if (empty($options)) {
            return $this->error('SETTINGS_NOT_FOUND', Loc::getMessage('MY_BPBUTTON_SERVICE_PARAM_BUTTON_VALUE_INVALID') ?: 'Выбран недопустимый вариант кнопки.');
        }

        $buttonOptions = array_map(static fn($option) => ['title' => $option, 'value' => $option], $options);
        $context = [
            'settingsId' => (int)($settings['ID'] ?? 0),
            'entityId' => $entityId,
            'elementId' => $elementId,
            'fieldId' => $fieldId,
            'userId' => $userId,
        ];

        return [
            'success' => true,
            'data' => [
                'actionType' => 'bp_launch_with_button_params',
                'bpTemplateId' => $templateId,
                'paramMeta' => [
                    'name' => $paramName,
                    'title' => $paramTitle,
                    'mode' => 'button_select',
                ],
                'buttonOptions' => $buttonOptions,
                'context' => $context,
            ],
        ];
    }

    /**
     * Конфигурация для запуска бизнес-процесса (actionType = bp_launch).
     */
    private function getBpLaunchConfig(string $entityId, int $elementId, int $fieldId, int $userId, array $settings): array
    {
        $templateId = (int)($settings['BP_TEMPLATE_ID'] ?? 0);
        if ($templateId <= 0) {
            return $this->error('SETTINGS_NOT_FOUND', Loc::getMessage('MY_BPBUTTON_SERVICE_BP_TEMPLATE_NOT_SET') ?: 'Шаблон БП не выбран. Настройте кнопку.');
        }

        if (!Loader::includeModule('bizproc') || !Loader::includeModule('crm')) {
            return $this->error('INTERNAL_ERROR', Loc::getMessage('MY_BPBUTTON_SERVICE_BP_MODULE_REQUIRED') ?: 'Требуется модуль bizproc.');
        }

        $entityTypeId = BpTemplateResolver::resolveEntityTypeIdFromEntityId($entityId);
        if ($entityTypeId <= 0) {
            return $this->error('INTERNAL_ERROR', Loc::getMessage('MY_BPBUTTON_SERVICE_INVALID_ENTITY') ?: 'Некорректный тип сущности.');
        }

        $starterConfig = CCrmBizProcHelper::getBpStarterConfig($entityTypeId, $elementId);
        if (empty($starterConfig['signedDocumentType']) || empty($starterConfig['signedDocumentId'])) {
            return $this->error('INTERNAL_ERROR', Loc::getMessage('MY_BPBUTTON_SERVICE_BP_STARTER_FAILED') ?: 'Не удалось подготовить конфигурацию запуска БП.');
        }

        $context = [
            'settingsId' => (int)($settings['ID'] ?? 0),
            'entityId' => $entityId,
            'elementId' => $elementId,
            'fieldId' => $fieldId,
            'userId' => $userId,
        ];

        return [
            'success' => true,
            'data' => [
                'actionType' => 'bp_launch',
                'bpTemplateId' => $templateId,
                'starterConfig' => $starterConfig,
                'context' => $context,
            ],
        ];
    }

    /**
     * Конфигурация для запуска бизнес-процесса с параметром (actionType = bp_launch_with_params).
     */
    private function getBpLaunchWithParamsConfig(string $entityId, int $elementId, int $fieldId, int $userId, array $settings): array
    {
        $templateId = (int)($settings['BP_TEMPLATE_ID'] ?? 0);
        if ($templateId <= 0) {
            return $this->error('SETTINGS_NOT_FOUND', Loc::getMessage('MY_BPBUTTON_SERVICE_BP_TEMPLATE_NOT_SET') ?: 'Шаблон БП не выбран. Настройте кнопку.');
        }

        $paramName = trim((string)($settings['PARAM_NAME'] ?? ''));
        $paramTitle = trim((string)($settings['PARAM_TITLE'] ?? ''));
        if ($paramName === '' || !preg_match('~^[A-Za-z][A-Za-z0-9_]*$~', $paramName)) {
            return $this->error('SETTINGS_NOT_FOUND', Loc::getMessage('MY_BPBUTTON_SERVICE_PARAM_NAME_INVALID') ?: 'Некорректное имя параметра запуска БП.');
        }
        if ($paramTitle === '') {
            $paramTitle = Loc::getMessage('MY_BPBUTTON_SERVICE_PARAM_TITLE_DEFAULT') ?: 'Параметр';
        }

        $context = [
            'settingsId' => (int)($settings['ID'] ?? 0),
            'entityId' => $entityId,
            'elementId' => $elementId,
            'fieldId' => $fieldId,
            'userId' => $userId,
        ];

        return [
            'success' => true,
            'data' => [
                'actionType' => 'bp_launch_with_params',
                'bpTemplateId' => $templateId,
                'paramMeta' => [
                    'name' => $paramName,
                    'title' => $paramTitle,
                    'type' => 'string',
                ],
                'context' => $context,
            ],
        ];
    }

    /**
     * Запуск БП с пользовательским строковым параметром.
     */
    public function startBpWithParams(string $entityId, int $elementId, int $fieldId, int $userId, string $value): array
    {
        $settings = $this->repository->getByFieldId($fieldId);
        if (!$settings) {
            return $this->error('SETTINGS_NOT_FOUND', Loc::getMessage('MY_BPBUTTON_SERVICE_SETTINGS_NOT_FOUND') ?: 'Кнопка не настроена.');
        }
        if (($settings['ACTIVE'] ?? 'N') !== 'Y') {
            return $this->error('BUTTON_INACTIVE', Loc::getMessage('MY_BPBUTTON_SERVICE_BUTTON_INACTIVE') ?: 'Действие недоступно. Кнопка отключена администратором.');
        }
        if (!in_array(($settings['ACTION_TYPE'] ?? 'url'), ['bp_launch_with_params', 'bp_launch_with_button_params'], true)) {
            return $this->error('SETTINGS_NOT_FOUND', Loc::getMessage('MY_BPBUTTON_SERVICE_ACTION_NOT_SUPPORTED') ?: 'Текущий режим кнопки не поддерживает запуск с параметрами.');
        }

        if (!Loader::includeModule('bizproc') || !Loader::includeModule('crm')) {
            return $this->error('INTERNAL_ERROR', Loc::getMessage('MY_BPBUTTON_SERVICE_BP_MODULE_REQUIRED') ?: 'Требуется модуль bizproc.');
        }

        $templateId = (int)($settings['BP_TEMPLATE_ID'] ?? 0);
        $paramName = trim((string)($settings['PARAM_NAME'] ?? ''));
        $value = trim($value);

        if ($templateId <= 0) {
            return $this->error('SETTINGS_NOT_FOUND', Loc::getMessage('MY_BPBUTTON_SERVICE_BP_TEMPLATE_NOT_SET') ?: 'Шаблон БП не выбран. Настройте кнопку.');
        }
        if ($paramName === '' || !preg_match('~^[A-Za-z][A-Za-z0-9_]*$~', $paramName)) {
            return $this->error('SETTINGS_NOT_FOUND', Loc::getMessage('MY_BPBUTTON_SERVICE_PARAM_NAME_INVALID') ?: 'Некорректное имя параметра запуска БП.');
        }
        if ($value === '') {
            return $this->error('VALIDATION_ERROR', Loc::getMessage('MY_BPBUTTON_SERVICE_PARAM_VALUE_REQUIRED') ?: 'Введите значение параметра.');
        }

        $entityTypeId = BpTemplateResolver::resolveEntityTypeIdFromEntityId($entityId);
        if ($entityTypeId <= 0) {
            return $this->error('INTERNAL_ERROR', Loc::getMessage('MY_BPBUTTON_SERVICE_INVALID_ENTITY') ?: 'Некорректный тип сущности.');
        }

        $documentId = CCrmBizProcHelper::ResolveDocumentId($entityTypeId, $elementId);
        if (!is_array($documentId) || count($documentId) < 3) {
            return $this->error('INTERNAL_ERROR', Loc::getMessage('MY_BPBUTTON_SERVICE_INVALID_DOCUMENT') ?: 'Не удалось подготовить документ для запуска БП.');
        }

        if ($userId <= 0) {
            return $this->error('ACCESS_DENIED', Loc::getMessage('MY_BPBUTTON_SERVICE_ACCESS_DENIED') ?: 'Недостаточно прав для запуска БП.');
        }

        if (class_exists('\CBPDocument') && class_exists('\CBPCanUserOperateOperation')) {
            $canStart = \CBPDocument::canUserOperateDocument(
                \CBPCanUserOperateOperation::StartWorkflow,
                $userId,
                $documentId,
                ['WorkflowTemplateId' => $templateId]
            );
            if (!$canStart) {
                return $this->error('ACCESS_DENIED', Loc::getMessage('MY_BPBUTTON_SERVICE_ACCESS_DENIED') ?: 'Недостаточно прав для запуска БП.');
            }
        }

        $errors = [];
        $parameters = [
            \CBPDocument::PARAM_TAGRET_USER => 'user_' . $userId,
            $paramName => $value,
        ];
        $workflowId = \CBPDocument::StartWorkflow($templateId, $documentId, $parameters, $errors);
        if (!$workflowId) {
            $errorText = '';
            if (!empty($errors[0]['message'])) {
                $errorText = (string)$errors[0]['message'];
            }
            return $this->error('BP_START_FAILED', $errorText !== '' ? $errorText : (Loc::getMessage('MY_BPBUTTON_SERVICE_BP_START_FAILED') ?: 'Не удалось запустить бизнес-процесс.'));
        }

        return [
            'success' => true,
            'data' => [
                'workflowId' => (string)$workflowId,
            ],
        ];
    }

    public function startBpWithButtonParam(string $entityId, int $elementId, int $fieldId, int $userId, string $selectedValue): array
    {
        $settings = $this->repository->getByFieldId($fieldId);
        if (!$settings) {
            return $this->error('SETTINGS_NOT_FOUND', Loc::getMessage('MY_BPBUTTON_SERVICE_SETTINGS_NOT_FOUND') ?: 'Кнопка не настроена.');
        }
        if (($settings['ACTIVE'] ?? 'N') !== 'Y') {
            return $this->error('BUTTON_INACTIVE', Loc::getMessage('MY_BPBUTTON_SERVICE_BUTTON_INACTIVE') ?: 'Действие недоступно. Кнопка отключена администратором.');
        }
        if (($settings['ACTION_TYPE'] ?? 'url') !== 'bp_launch_with_button_params') {
            return $this->error('SETTINGS_NOT_FOUND', Loc::getMessage('MY_BPBUTTON_SERVICE_ACTION_NOT_SUPPORTED') ?: 'Текущий режим кнопки не поддерживает запуск с параметрами.');
        }

        $selectedValue = trim($selectedValue);
        if ($selectedValue === '') {
            return $this->error('VALIDATION_ERROR', Loc::getMessage('MY_BPBUTTON_SERVICE_PARAM_BUTTON_VALUE_INVALID') ?: 'Выбран недопустимый вариант кнопки.');
        }

        $rawOptions = (string)($settings['PARAM_BUTTONS'] ?? '');
        $options = [];
        if ($rawOptions !== '') {
            $decoded = json_decode($rawOptions, true);
            if (is_array($decoded)) {
                $options = array_values(array_filter(array_map(
                    static fn($v) => trim((string)$v),
                    $decoded
                ), static fn($v) => $v !== ''));
            }
        }
        if (empty($options) || !in_array($selectedValue, $options, true)) {
            return $this->error('VALIDATION_ERROR', Loc::getMessage('MY_BPBUTTON_SERVICE_PARAM_BUTTON_VALUE_INVALID') ?: 'Выбран недопустимый вариант кнопки.');
        }

        return $this->startBpWithParams($entityId, $elementId, $fieldId, $userId, $selectedValue);
    }

    /**
     * Пишет деловой факт нажатия в таблицу логов.
     *
     * @param array{
     *   settingsId?:int,
     *   fieldId?:int,
     *   entityId?:string,
     *   elementId?:int,
     *   userId?:int
     * } $context
     */
    public function logClick(array $context, string $status, ?string $message = null): void
    {
        try {
            $settingsId = (int)($context['settingsId'] ?? 0);
            $fieldId = (int)($context['fieldId'] ?? 0);
            $entityId = trim((string)($context['entityId'] ?? ''));
            $elementId = (int)($context['elementId'] ?? 0);
            $userId = (int)($context['userId'] ?? 0);
            $status = trim($status);

            if ($settingsId <= 0 && $fieldId > 0) {
                $row = $this->repository->getByFieldId($fieldId);
                $settingsId = $row ? (int)($row['ID'] ?? 0) : 0;
            }

            if ($fieldId <= 0 || $entityId === '' || $elementId <= 0 || $userId <= 0 || $status === '') {
                return;
            }

            $message = $this->sanitizeLogMessage($message);

            LogsTable::add([
                'SETTINGS_ID' => max(0, $settingsId),
                'FIELD_ID' => $fieldId,
                'ENTITY_ID' => $entityId,
                'ELEMENT_ID' => $elementId,
                'USER_ID' => $userId,
                'STATUS' => mb_substr($status, 0, 50),
                'MESSAGE' => $message,
                'CREATED_AT' => new DateTime(),
            ]);
        } catch (\Throwable $e) {
            // Логирование не должно ломать основной сценарий. Фиксируем только в техлог.
            // Используем безопасное логирование для предотвращения утечки чувствительных данных
            SecurityHelper::safeLog($e, 'my.bpbutton', 'ButtonService::logClick');
            
            // Дополнительное логирование через Debug (если доступно)
            if (class_exists(Debug::class)) {
                SecurityHelper::safeDebugLog($e, 'my.bpbutton logClick failed', 'my_bpbutton.log', 'ButtonService::logClick');
            }
        }
    }

    /**
     * @param array{settingsId?:int, entityId:string, elementId:int, fieldId:int, userId:int} $context
     */
    private function appendContextToUrl(string $url, array $context): string
    {
        $query = http_build_query([
            'ENTITY_ID' => $context['entityId'],
            'ELEMENT_ID' => $context['elementId'],
            'FIELD_ID' => $context['fieldId'],
            'USER_ID' => $context['userId'],
        ]);

        if ($query === '') {
            return $url;
        }

        return str_contains($url, '?')
            ? $url . '&' . $query
            : $url . '?' . $query;
    }

    /**
     * @return array{success:false, error: array{code:string, message:string}}
     */
    private function error(string $code, string $message): array
    {
        return [
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ];
    }

    /**
     * Очистка сообщения для логирования от чувствительных данных
     * 
     * Маскирует токены, пароли, секреты и другие чувствительные данные
     * перед записью в бизнес-логи (my_bpbutton_logs)
     * 
     * @param string|null $message Исходное сообщение
     * @return string|null Очищенное сообщение или null
     */
    private function sanitizeLogMessage(?string $message): ?string
    {
        $message = trim((string)($message ?? ''));
        if ($message === '') {
            return null;
        }

        // Убираем потенциально чувствительные query-параметры
        $sensitiveParams = [
            'auth', 'token', 'access_token', 'refresh_token', 'password', 'secret',
            'key', 'api_key', 'apikey', 'session', 'sessid', 'sid',
            'credentials', 'credential', 'passwd', 'pwd'
        ];
        $pattern = '~([?&](' . implode('|', $sensitiveParams) . ')=)[^&\s]+~iu';
        $message = preg_replace($pattern, '$1***', $message) ?: $message;

        // Убираем полные URL с параметрами авторизации
        $message = preg_replace('~https?://[^\s]+(?:token|password|secret|key|auth)=[^\s]+~iu', '[URL_WITH_CREDENTIALS]', $message);

        // Убираем email-адреса (оставляем только домен)
        $message = preg_replace('~\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b~u', '[EMAIL]', $message);

        // Убираем номера телефонов (оставляем только формат)
        $message = preg_replace('~\b\+?\d{1,3}[-.\s]?\(?\d{1,4}\)?[-.\s]?\d{1,4}[-.\s]?\d{1,9}\b~u', '[PHONE]', $message);

        // Убираем полные пути к файлам (оставляем только имя файла)
        $message = preg_replace('~(/[^\s:]+/)([^/\s]+\.(?:php|js|html|htm))~u', '$2', $message);

        // Ограничиваем размер, чтобы не раздувать логи
        $message = mb_substr($message, 0, 1000);

        return $message;
    }
}
