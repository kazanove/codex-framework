<?php
declare(strict_types=1);

namespace CodeX;

use RuntimeException;

/**
 * Класс Autoload — реализация простого автозагрузчика классов с поддержкой PSR-4.
 *
 * Автоматически регистрирует себя как автозагрузчик через spl_autoload_register,
 * поддерживает несколько директорий для поиска классов и автоматически подключает
 * Composer-автозагрузчик, если он существует.
 *
 * Требует PHP 8.4 или выше.
 */
class Autoload
{
    /**
     * Массив директорий, в которых будет производиться поиск классов.
     *
     * @var array<string>
     */
    private array $dirs = [];
    private array $classMap =[];

    /**
     * Конструктор автозагрузчика.
     *
     * Регистрирует переданную директорию, активирует автозагрузчик и подключает
     * Composer-автозагрузчик (если он существует).
     *
     * @param string $dir Базовая директория для поиска классов
     * @throws RuntimeException Если версия PHP ниже 8.4
     */
    public function __construct(string $dir)
    {
        // Проверяем минимальную версию PHP
        if (PHP_VERSION_ID < 80400) {
            throw new RuntimeException('Требуется PHP 8.4 или выше. У вас установлена версия: ' . PHP_VERSION);
        }

        // Добавляем базовую директорию для поиска классов
        $this->addDir($dir);

        // Регистрируем метод loader как автозагрузчик
        $this->register([$this, 'loader']);

        // Подключаем Composer-автозагрузчик ОДИН РАЗ при инициализации
        $this->loadComposerAutoloader();

        if(file_exists($pathHelper=__DIR__.DIRECTORY_SEPARATOR.'helper'.DIRECTORY_SEPARATOR.'functions.php')){
            include $pathHelper;
        }
    }

    /**
     * Добавляет директорию в список поиска классов.
     *
     * Все пути нормализуются: удаляется завершающий слэш и добавляется DIRECTORY_SEPARATOR.
     *
     * @param string $dir Путь к директории
     * @return void
     */
    public function addDir(string $dir): void
    {
        $this->dirs[] = rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    }

    /**
     * Регистрирует функцию автозагрузки через spl_autoload_register.
     *
     * @param callable $callback Функция обратного вызова для загрузки классов
     * @param bool $throw Выбрасывать ли исключение при ошибке регистрации (по умолчанию true)
     * @param bool $prepend Добавлять ли автозагрузчик в начало стека (по умолчанию true)
     * @return void
     * @throws RuntimeException Если регистрация автозагрузчика не удалась
     */
    public function register(callable $callback, bool $throw = true, bool $prepend = true): void
    {
        if (!spl_autoload_register($callback, $throw, $prepend)) {
            throw new RuntimeException('Ошибка при регистрации функции автозагрузки классов');
        }
    }

    /**
     * Подключает автозагрузчик Composer, если файл vendor/autoload.php существует.
     *
     * Используется для совместимости с зависимостями, установленными через Composer.
     *
     * @return void
     */
    private function loadComposerAutoloader(): void
    {
        $pathComposer = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
        if (is_file($pathComposer)) {
            require_once $pathComposer;
        }
    }

    /**
     * Основной метод автозагрузки — преобразует имя класса в путь к файлу и подключает его.
     *
     * Следует PSR-4: заменяет namespace-разделители '\' на DIRECTORY_SEPARATOR.
     *
     * @param string $className Полное имя класса с пространством имен (например, "CodeX\Utils\Logger")
     * @return void
     */
    public function loader(string $className): void
    {
        if (isset($this->classMap[$className])) {
            require_once $this->classMap[$className];
            return;
        }
        foreach ($this->dirs as $dir) {
            // Преобразуем имя класса в путь к файлу
            $file = $dir . str_replace('\\', DIRECTORY_SEPARATOR, $className) . '.php';

            // Если файл существует — подключаем его и выходим
            if (is_file($file)) {
                $this->classMap[$className] = $file;
                require_once $file;
                return;
            }
        }

        // Если файл не найден — ничего не делаем (другие автозагрузчики могут обработать класс)
        // Это стандартное поведение PSR-4
    }
}

// Создаём и возвращаем экземпляр автозагрузчика, используя директорию на уровень выше от текущей
return new Autoload(dirname(__DIR__));