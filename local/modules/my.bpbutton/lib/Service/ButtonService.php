<?php

declare(strict_types=1);

namespace My\BpButton\Service;

use Bitrix\Main\Diag\Debug;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Type\DateTime;
use My\BpButton\Internals\LogsTable;
use My\BpButton\Internals\SettingsTable;

Loc::loadMessages(__FILE__);

final class ButtonService
{
    /**
     * @return array{
     *   url: string,
     *   title: string,
     *   width: string|int,
     *   context: array{settingsId:int, entityId: string, elementId: int, fieldId: int, userId: int}
     * }
     */
    public function getSidePanelConfig(string $entityId, int $elementId, int $fieldId, int $userId): array
    {
        $settings = SettingsTable::getList([
            'select' => ['ID', 'FIELD_ID', 'ENTITY_ID', 'HANDLER_URL', 'TITLE', 'WIDTH', 'ACTIVE', 'UPDATED_AT'],
            'filter' => ['=FIELD_ID' => $fieldId],
            'limit' => 1,
        ])->fetch();

        if (!$settings) {
            return $this->error('SETTINGS_NOT_FOUND', Loc::getMessage('MY_BPBUTTON_SERVICE_SETTINGS_NOT_FOUND') ?: 'Кнопка не настроена.');
        }

        if (($settings['ACTIVE'] ?? 'N') !== 'Y') {
            return $this->error('BUTTON_INACTIVE', Loc::getMessage('MY_BPBUTTON_SERVICE_BUTTON_INACTIVE') ?: 'Действие недоступно. Кнопка отключена администратором.');
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
                'url' => $finalUrl,
                'title' => $title,
                'width' => $width,
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
                $row = SettingsTable::getList([
                    'select' => ['ID'],
                    'filter' => ['=FIELD_ID' => $fieldId],
                    'limit' => 1,
                ])->fetch();
                $settingsId = (int)($row['ID'] ?? 0);
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
            if (function_exists('AddMessage2Log')) {
                AddMessage2Log(
                    'my.bpbutton logClick failed: ' . $e->getMessage(),
                    'my.bpbutton'
                );
            } else {
                Debug::writeToFile($e->getMessage(), 'my.bpbutton logClick failed', 'my_bpbutton.log');
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

    private function sanitizeLogMessage(?string $message): ?string
    {
        $message = trim((string)($message ?? ''));
        if ($message === '') {
            return null;
        }

        // Убираем потенциально чувствительные query-параметры (минимальный безопасный фильтр).
        $message = preg_replace('~([?&](?:auth|token|access_token|refresh_token|password|secret)=)[^&\s]+~iu', '$1***', $message) ?: $message;

        // Ограничиваем размер, чтобы не раздувать логи.
        $message = mb_substr($message, 0, 1000);

        return $message;
    }
}

