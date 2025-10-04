<?php
declare(strict_types=1);

namespace CodeX\Session;

class Flash
{
    private static array $readMessages = [];

    public static function get(string $key, mixed $default = null): mixed
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Возвращаем значение, но не удаляем сразу
        $value = $_SESSION['_flash'][$key] ?? $default;

        // Запоминаем, что сообщение было прочитано
        if ($value !== $default) {
            self::$readMessages[$key] = $value;
        }

        return $value;
    }

    public static function put(string $key, mixed $value): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['_flash'][$key] = $value;
    }

    /**
     * Удаляет прочитанные сообщения в конце запроса
     */
    public static function flush(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        foreach (self::$readMessages as $key => $value) {
            unset($_SESSION['_flash'][$key]);
        }

        self::$readMessages = [];
    }

    public static function has(string $key): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        return isset($_SESSION['_flash'][$key]);
    }
}