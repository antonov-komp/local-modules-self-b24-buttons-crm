<?php

declare(strict_types=1);

namespace My\BpButton\Service;

use Bitrix\Main\Diag\Debug;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Type\DateTime;
use CCrmBizProcHelper;
use CCrmOwnerType;
use My\BpButton\Helper\SecurityHelper;
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
        if ($actionType !== 'url' && $actionType !== 'bp_launch') {
            $actionType = 'url';
        }

        if ($actionType === 'bp_launch') {
            return $this->getBpLaunchConfig($entityId, $elementId, $fieldId, $userId, $settings);
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

        $entityTypeId = CCrmOwnerType::ResolveID($entityId);
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

