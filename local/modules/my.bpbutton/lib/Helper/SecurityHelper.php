<?php

declare(strict_types=1);

namespace My\BpButton\Helper;

/**
 * Утилиты для безопасного логирования и обработки ошибок
 * 
 * Предотвращает утечку чувствительных данных в логи
 */
final class SecurityHelper
{
    /**
     * Безопасное логирование сообщения об ошибке
     * 
     * Маскирует чувствительные данные из сообщения об ошибке перед логированием
     * 
     * @param string $message Исходное сообщение
     * @param string $context Дополнительный контекст (например, имя метода)
     * @return string Безопасное сообщение для логирования
     */
    public static function sanitizeErrorMessage(string $message, string $context = ''): string
    {
        // Убираем потенциально чувствительные данные
        $message = preg_replace('~([?&](?:auth|token|access_token|refresh_token|password|secret|key|api_key)=)[^&\s]+~iu', '$1***', $message) ?: $message;
        
        // Убираем полные пути к файлам (оставляем только имя файла)
        $message = preg_replace('~(/[^\s:]+/)([^/\s]+\.php)~u', '$2', $message);
        
        // Убираем полные URL с параметрами авторизации
        $message = preg_replace('~https?://[^\s]+(?:token|password|secret|key)=[^\s]+~iu', '[URL_WITH_CREDENTIALS]', $message);
        
        // Ограничиваем длину сообщения
        $message = mb_substr($message, 0, 500);
        
        if ($context !== '') {
            return sprintf('[%s] %s', $context, $message);
        }
        
        return $message;
    }

    /**
     * Безопасное логирование исключения
     * 
     * Извлекает только безопасную информацию из исключения
     * 
     * @param \Throwable $e Исключение
     * @param string $context Контекст (имя метода/класса)
     * @return string Безопасное сообщение для логирования
     */
    public static function sanitizeException(\Throwable $e, string $context = ''): string
    {
        $className = get_class($e);
        $message = self::sanitizeErrorMessage($e->getMessage(), $context);
        
        // Для внутренних ошибок не логируем stack trace в бизнес-логи
        // Stack trace должен быть только в технических логах с высоким уровнем детализации
        
        return sprintf('%s: %s', $className, $message);
    }

    /**
     * Безопасное логирование через AddMessage2Log
     * 
     * @param string|\Throwable $data Сообщение или исключение
     * @param string $moduleId ID модуля
     * @param string $context Контекст (опционально)
     */
    public static function safeLog($data, string $moduleId, string $context = ''): void
    {
        if (!function_exists('AddMessage2Log')) {
            return;
        }

        if ($data instanceof \Throwable) {
            $message = self::sanitizeException($data, $context);
        } else {
            $message = self::sanitizeErrorMessage((string)$data, $context);
        }

        AddMessage2Log($message, $moduleId);
    }

    /**
     * Безопасное логирование через Debug::writeToFile
     * 
     * @param string|\Throwable $data Сообщение или исключение
     * @param string $title Заголовок
     * @param string $fileName Имя файла лога
     * @param string $context Контекст (опционально)
     */
    public static function safeDebugLog($data, string $title, string $fileName, string $context = ''): void
    {
        if (!class_exists(\Bitrix\Main\Diag\Debug::class)) {
            return;
        }

        if ($data instanceof \Throwable) {
            $message = self::sanitizeException($data, $context);
        } else {
            $message = self::sanitizeErrorMessage((string)$data, $context);
        }

        \Bitrix\Main\Diag\Debug::writeToFile($message, $title, $fileName);
    }
}
