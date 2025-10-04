<?php
declare(strict_types=1);

namespace CodeX;

use CodeX\View\Compiler;
use CodeX\View\ComponentInterface;
use JsonException;
use ReflectionClass;
use ReflectionException;
use RuntimeException;
use Throwable;

class View
{
    public bool $debugMode;
    private string $appViewPath;
    private string $frameworkViewPath;
    private string $cachePath;
    private ?string $layout = null;
    private array $sections = [];
    private string $currentSection = '';
    private array $componentStack = [];
    private array $pushes = [];
    private string $currentPushStack = '';
    private string $currentSlot = '';
    private array $loops = [];
    private array $loopStack = [];

    public function __construct(string $appViewPath, string $frameworkViewPath = '', string $cachePath = '')
    {
        $this->appViewPath = rtrim($appViewPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $this->frameworkViewPath = $frameworkViewPath ? rtrim($frameworkViewPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR : '';
        $this->cachePath = $cachePath ? rtrim($cachePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR : $this->appViewPath . 'cache' . DIRECTORY_SEPARATOR;

        $this->debugMode = Application::getInstance()->config['app']['debug'] ?? false;

        $this->validatePaths();
        $this->ensureCacheDirectory();
    }

    private function validatePaths(): void
    {
        if (!is_dir($this->appViewPath)) {
            throw new RuntimeException("Директория шаблонов не найдена: {$this->appViewPath}");
        }

        if ($this->frameworkViewPath && !is_dir($this->frameworkViewPath)) {
            throw new RuntimeException("Директория фреймворка не найдена: {$this->frameworkViewPath}");
        }

        // Проверка на directory traversal
        $realAppPath = realpath($this->appViewPath);
        $realFrameworkPath = $this->frameworkViewPath ? realpath($this->frameworkViewPath) : '';

        if (!$realAppPath || ($this->frameworkViewPath && !$realFrameworkPath)) {
            throw new RuntimeException("Некорректный путь к шаблонам");
        }
    }

    private function ensureCacheDirectory(): void
    {
        if (!is_dir($this->cachePath) && !mkdir($concurrentDirectory = $this->cachePath, 0755, true) && !is_dir($concurrentDirectory)) {
            throw new RuntimeException("Не удалось создать директорию кэша: {$this->cachePath}");
        }

        if (!is_writable($this->cachePath)) {
            throw new RuntimeException("Директория кэша недоступна для записи: {$this->cachePath}");
        }
    }

    public static function checkAuth(): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        return isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0;
    }

    public static function registerFilters(): void
    {
        Compiler::filter('upper', static function ($value) {
            return "strtoupper({$value})";
        });

        Compiler::filter('lower', static function ($value) {
            return "strtolower({$value})";
        });

        Compiler::filter('date', static function ($value, $format = "'Y-m-d'") {
            return "date({$format}, is_numeric({$value}) ? {$value} : strtotime({$value}))";
        });

        Compiler::filter('format', static function ($value, $args) {
            return "sprintf({$args}, {$value})";
        });

        Compiler::filter('number', static function ($value, $decimals = "2") {
            return "number_format({$value}, {$decimals}, ',', ' ')";
        });

        Compiler::filter('escape', static function ($value) {
            return "htmlspecialchars((string){$value}, ENT_QUOTES, 'UTF-8')";
        });

        Compiler::filter('attr', static function ($value) {
            return "htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8')";
        });

        Compiler::filter('url', static function ($value) {
            return "urlencode((string)$value)";
        });

        Compiler::filter('nl2br', static function ($value) {
            return "nl2br(htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'))";
        });
    }

    public static function e(mixed $value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }

    public static function checked(bool $condition): string
    {
        return $condition ? 'checked' : '';
    }

    public static function selected(bool $condition): string
    {
        return $condition ? 'selected' : '';
    }

    public static function disabled(bool $condition): string
    {
        return $condition ? 'disabled' : '';
    }

    public static function asset(string $path): string
    {
        return '/assets/' . ltrim($path, '/');
    }

    public static function toJson(mixed $data, int $flags = JSON_THROW_ON_ERROR): string
    {
        try {
            return json_encode($data, $flags | JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        } catch (JsonException $e) {
            return 'null';
        }
    }

    public static function csrfField(): string
    {
        return '<input type="hidden" name="_token" value="' . self::csrfToken() . '">';
    }

    public static function csrfToken(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION['_token'])) {
            $_SESSION['_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['_token'];
    }

    public static function csrfMetaTag(): string
    {
        return '<meta name="csrf-token" content="' . self::csrfToken() . '">';
    }

    public static function verifyCsrfToken(?string $token = null): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $token = $token ?? ($_POST['_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');

        return isset($_SESSION['_token']) && hash_equals($_SESSION['_token'], $token);
    }

    public function extendsLayout(string $layout): void
    {
        $this->layout = $layout;
    }

    public function startSection(string $name): void
    {
        if ($this->currentSection !== '') {
            throw new RuntimeException("Нельзя начинать секцию '{$name}' пока открыта секция '{$this->currentSection}'");
        }

        $this->currentSection = $name;
        ob_start();
    }

    public function yieldSection(string $name, string $default = ''): string
    {
        return $this->sections[$name] ?? $default;
    }

    public function sectionExists(string $name): bool
    {
        return isset($this->sections[$name]);
    }

    public function makePartial(string $template, array $data = []): string
    {
        $templatePath = str_replace('.', '/', $template);
        return $this->extracted($templatePath, $data);
    }

    /**
     * @throws JsonException
     */
    private function extracted(array|string $templatePath, array $data): string
    {
        $cacheFile = $this->getCachePath($templatePath);
        $sourceFile = $this->findSourcePath($templatePath);
        if (!$this->isCacheFresh($templatePath, $sourceFile)) {
            $this->compileTemplate($templatePath, $sourceFile, $cacheFile);
        }

        return $this->evaluate($cacheFile, $data);
    }

    private function getCachePath(string $template): string
    {
        return $this->cachePath . md5($template) . '.php';
    }

    private function findSourcePath(string $template): string
    {
        $templatePath = ltrim(str_replace('.', DIRECTORY_SEPARATOR, $template), DIRECTORY_SEPARATOR) . '.php';

        $appPath = $this->appViewPath . $templatePath;
        if (file_exists($appPath)) {
            return $appPath;
        }

        $frameworkPath = $this->frameworkViewPath . $templatePath;
        if (file_exists($frameworkPath)) {
            return $frameworkPath;
        }

        throw new RuntimeException("Шаблон не найден: {$template} (искал в: {$appPath}" . ($this->frameworkViewPath ? " и {$frameworkPath}" : "") . ")");
    }

    /**
     * @throws JsonException
     */
    private function isCacheFresh(string $template, string $sourceFile): bool
    {
        $cacheFile = $this->getCachePath($template);
        if (!file_exists($cacheFile) || !file_exists($sourceFile)) {
            return false;
        }

        if ($this->debugMode) {
            return false;
        }

        $manifest = $this->loadManifest();
        if (!isset($manifest[$template])) {
            return false;
        }

        $lastModified = filemtime($sourceFile);

        foreach ($manifest[$template]['dependencies'] as $dependency) {
            try {
                $dependencyPath = $this->findSourcePath($dependency);
                if (file_exists($dependencyPath) && filemtime($dependencyPath) > $lastModified) {
                    $lastModified = filemtime($dependencyPath);
                }
            } catch (RuntimeException $e) {
// Зависимость не найдена, перекомпилируем
                return false;
            }
        }

        return filemtime($cacheFile) >= $lastModified;
    }

    private function loadManifest(): array
    {
        $manifestPath = $this->getManifestPath();
        if (!file_exists($manifestPath)) {
            return [];
        }

        $content = file_get_contents($manifestPath);
        if ($content === false) {
            return [];
        }

        try {
            return json_decode($content, true, 512, JSON_THROW_ON_ERROR) ?? [];
        } catch (JsonException $e) {
            return [];
        }
    }

    private function getManifestPath(): string
    {
        return $this->cachePath . 'manifest.json';
    }

    /**
     * @throws JsonException
     */
    private function compileTemplate(string $template, string $sourceFile, string $cacheFile): void
    {
        $source = file_get_contents($sourceFile);
        if ($source === false) {
            throw new RuntimeException("Не удалось прочитать файл шаблона: {$sourceFile}");
        }

        $compiled = Compiler::compile($source, $template, $this->appViewPath);
        if ($compiled === false || $compiled === '') {
            throw new RuntimeException("Ошибка компиляции шаблона: {$template}");
        }

        $result = file_put_contents($cacheFile, $compiled, LOCK_EX);
        if ($result === false) {
            throw new RuntimeException("Не удалось записать кэш шаблона: {$cacheFile}");
        }
        $this->saveManifest($template, Compiler::getCurrentDependencies());
    }

    /**
     * @throws JsonException
     */
    private function saveManifest(string $template, array $dependencies): void
    {
        $manifestPath = $this->getManifestPath();
        $manifest = [];

        if (file_exists($manifestPath)) {
            $content = file_get_contents($manifestPath);
            if ($content !== false) {
                try {
                    $manifest = json_decode($content, true, 512, JSON_THROW_ON_ERROR) ?? [];
                } catch (JsonException $e) {
                    $manifest = [];
                }
            }
        }

        $manifest[$template] = [
            'dependencies' => array_values(array_unique($dependencies)),
            'compiled_at' => time()
        ];

        file_put_contents($manifestPath, json_encode($manifest, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    private function evaluate(string $cacheFile, array $data): string
    {
        if (!file_exists($cacheFile)) {
            throw new RuntimeException("Файл кэша не найден: {$cacheFile}");
        }

        $view = $this;
        $__env = $this;
        extract($data, EXTR_SKIP);

        ob_start();
        try {
            include $cacheFile;
        } catch (Throwable $e) {
            ob_end_clean();
            throw new RuntimeException("Ошибка выполнения шаблона: " . $e->getMessage(), 0, $e);
        }
        return ob_get_clean();
    }

    public function startComponent(string $name, array $data = []): void
    {
        if (!$this->validateComponentName($name)) {
            throw new RuntimeException("Недопустимое имя компонента: {$name}");
        }

        $componentClass = $this->resolveComponentClass($name);
        $component = $this->createComponentInstance($componentClass, $data);

// Передаем текущий экземпляр View в компонент
        $component->setViewInstance($this);

        if (!$component instanceof ComponentInterface) {
            throw new RuntimeException("Компонент должен реализовывать ComponentInterface: {$componentClass}");
        }

        $this->componentStack[] = $component;
        ob_start();
    }

    private function validateComponentName(string $name): bool
    {
        return preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $name) === 1;
    }

    private function resolveComponentClass(string $name): string
    {
        var_dump($this->appViewPath, $this->frameworkViewPath);
        $componentClass = '\\Application\\View\\Components\\' . ucfirst($name);

        if (!class_exists($componentClass)) {
            $frameworkClass = '\\CodeX\\View\\Components\\' . ucfirst($name);
            if (class_exists($frameworkClass)) {
                $componentClass = $frameworkClass;
            } else {
                throw new RuntimeException("Компонент не найден: {$name}");
            }
        }

        return $componentClass;
    }

    private function createComponentInstance(string $componentClass, array $data): object
    {
        try {
// Проверка, что класс реализует ComponentInterface
            if (!in_array(ComponentInterface::class, class_implements($componentClass), true)) {
                throw new RuntimeException("Класс компонента должен реализовывать ComponentInterface: {$componentClass}");
            }

            $reflection = new ReflectionClass($componentClass);
            $constructor = $reflection->getConstructor();

            if (!$constructor) {
                return new $componentClass();
            }

            $args = [];
            foreach ($constructor->getParameters() as $param) {
                $paramName = $param->getName();
                if (array_key_exists($paramName, $data)) {
                    $args[] = $data[$paramName];
                } elseif ($param->isDefaultValueAvailable()) {
                    $args[] = $param->getDefaultValue();
                } else {
                    throw new RuntimeException("Отсутствует обязательный параметр {$paramName} для компонента {$componentClass}");
                }
            }

            return $reflection->newInstanceArgs($args);
        } catch (ReflectionException $e) {
            throw new RuntimeException("Ошибка создания компонента {$componentClass}: " . $e->getMessage());
        }
    }

    public function renderComponent(): string
    {
        if (empty($this->componentStack)) {
            throw new RuntimeException("Нет активных компонентов для рендеринга");
        }

        $component = array_pop($this->componentStack);
        $content = ob_get_clean();

// Если компонент не имеет viewInstance, устанавливаем текущий
        if (!$component->getViewInstance()) {
            $component->setViewInstance($this);
        }

// Передаем содержимое в слот "default"
        $component->setSlot('default', $content);

        return $component->render();
    }

    /**
     * @throws Throwable
     */
    public function render(string $template, array $data = []): string
    {
        try {
            $this->resetState();
            return $this->renderTemplate($template, $data);
        } catch (Throwable $e) {
            $this->safeEndAllSections();
            $this->resetState();

            if ($this->debugMode) {
                throw $e;
            }

            return '<div class="error">Ошибка отображения страницы</div>';
        }
    }

    private function resetState(): void
    {
        $this->layout = null;
        $this->sections = [];
        $this->currentSection = '';
        $this->pushes = [];
        $this->currentPushStack = '';
    }

    /**
     * @throws JsonException
     */
    private function renderTemplate(string $template, array $data): string
    {
        return $this->extracted($template, $data);
    }

    public function safeEndAllSections(): void
    {
        if ($this->currentSection !== '') {
            $this->endSection();
        }

// Очищаем все возможные буферы вывода
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
    }

    public function endSection(): void
    {
        if ($this->currentSection === '') {
// Вместо ошибки просто игнорируем и очищаем буфер
            if (ob_get_level() > 0) {
                ob_get_clean();
            }
            return;
        }

        $content = ob_get_clean();
        $this->sections[$this->currentSection] = $content;
        $this->currentSection = '';
    }

    public function slot(string $name): void
    {
        if (empty($this->componentStack)) {
            return;
        }
        $this->currentSlot = $name;
        ob_start();
    }

    public function endSlot(): void
    {
        if (empty($this->componentStack)) {
            ob_get_clean();
            return;
        }

        $component = end($this->componentStack);
        $component->slots[$this->currentSlot] = ob_get_clean();
        $this->currentSlot = '';
    }

    public function renderFromCache(string $cacheFile, array $data = [], ?self $viewInstance = null): string
    {
        if (!file_exists($cacheFile)) {
            throw new RuntimeException("Кэш шаблона не найден: {$cacheFile}");
        }

        $this->resetState();
        return ($viewInstance ?? $this)->evaluate($cacheFile, $data);
    }

    public function getAppViewPath(): string
    {
        return $this->appViewPath;
    }

    public function getFrameworkViewPath(): string
    {
        return $this->frameworkViewPath;
    }

    public function startPush(string $stack): void
    {
        $this->currentPushStack = $stack;
        ob_start();
    }

    public function endPush(): void
    {
        if (empty($this->currentPushStack)) {
            ob_get_clean();
            return;
        }

        $content = ob_get_clean();
        if (!isset($this->pushes[$this->currentPushStack])) {
            $this->pushes[$this->currentPushStack] = [];
        }
        $this->pushes[$this->currentPushStack][] = $content;
        $this->currentPushStack = '';
    }

    public function yieldPush(string $stack, string $default = ''): string
    {
        return implode('', $this->pushes[$stack] ?? []) ?: $default;
    }

    public function clearCache(): void
    {
        $files = glob($this->cachePath . '*.php') ?: [];
        $manifestPath = $this->getManifestPath();

        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        if (file_exists($manifestPath)) {
            unlink($manifestPath);
        }

        Compiler::clearCache();
    }

    /**
     * @throws JsonException
     */
    public function clearTemplateCache(string $template): void
    {
        $cacheFile = $this->getCachePath($template);
        $manifestPath = $this->getManifestPath();

        if (file_exists($cacheFile)) {
            unlink($cacheFile);
        }

        if (file_exists($manifestPath)) {
            $manifest = $this->loadManifest();
            unset($manifest[$template]);
            file_put_contents($manifestPath, json_encode($manifest, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }
    }

    public function clearExpiredCache(int $maxAge = 3600): void
    {
        $files = glob($this->cachePath . '*.php') ?: [];
        $now = time();

        foreach ($files as $file) {
            if (is_file($file) && ($now - filemtime($file)) > $maxAge) {
                unlink($file);
            }
        }

        $this->cleanupManifest($maxAge);
    }

    private function cleanupManifest(int $maxAge): void
    {
        try {
            $manifest = $this->loadManifest();
            $now = time();
            $changed = false;

            foreach ($manifest as $template => $info) {
                if (($now - $info['compiled_at']) > $maxAge) {
                    unset($manifest[$template]);
                    $changed = true;
                }
            }

            if ($changed) {
                file_put_contents($this->getManifestPath(), json_encode($manifest, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            }
        } catch (JsonException $e) {
            error_log("Manifest cleanup error: " . $e->getMessage());
        }
    }

    public function getCacheSize(): int
    {
        $files = glob($this->cachePath . '*.php') ?: [];
        $size = 0;

        foreach ($files as $file) {
            if (is_file($file)) {
                $size += filesize($file);
            }
        }

        return $size;
    }

    public function addLoop($data): void
    {
        $this->loopStack[] = ['iteration' => 0, 'index' => 0, 'remaining' => is_countable($data) ? count($data) : 0, 'count' => is_countable($data) ? count($data) : 0, 'first' => true, 'last' => false,];
    }

    public function incrementLoopIndices(): void
    {
        $loop = &$this->loopStack[count($this->loopStack) - 1];
        $loop['iteration']++;
        $loop['index'] = $loop['iteration'] - 1;
        $loop['first'] = $loop['iteration'] === 1;
        $loop['last'] = $loop['iteration'] === $loop['count'];
        $loop['remaining'] = $loop['count'] - $loop['iteration'];
    }

    public function popLoop(): void
    {
        array_pop($this->loopStack);
    }

    public function inLoop(): bool
    {
        return !empty($this->loopStack);
    }

    public function getLoopDepth(): int
    {
        return count($this->loopStack);
    }

    public function getLoopIndex(): int
    {
        $loop = $this->getLastLoop();
        return $loop ? $loop['index'] : 0;
    }

    public function getLastLoop(): ?array
    {
        return end($this->loopStack) ?: null;
    }

    public function getLoopIteration(): int
    {
        $loop = $this->getLastLoop();
        return $loop ? $loop['iteration'] : 0;
    }
}

// Инициализация фильтров
View::registerFilters();