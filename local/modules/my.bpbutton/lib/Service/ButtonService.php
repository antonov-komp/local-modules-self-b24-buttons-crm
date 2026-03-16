<?php

declare(strict_types=1);

namespace My\BpButton\Service;

use Bitrix\Main\Localization\Loc;
use My\BpButton\Internals\SettingsTable;

Loc::loadMessages(__FILE__);

final class ButtonService
{
    /**
     * @return array{
     *   url: string,
     *   title: string,
     *   width: string|int,
     *   context: array{entityId: string, elementId: int, fieldId: int, userId: int}
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
     * @param array{entityId:string, elementId:int, fieldId:int, userId:int} $context
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
}

