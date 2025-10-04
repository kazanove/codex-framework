<?php
declare(strict_types=1);

namespace CodeX\View;

use RuntimeException;
use Throwable;

abstract class Component implements ComponentInterface
{
    /**
     * @var array Содержимое слотов компонента
     */
    protected array $slots = [];

    /**
     * @var array Данные компонента
     */
    protected array $data = [];

    /**
     * @var string Путь к шаблону компонента
     */
    protected string $view = '';

    /**
     * @var \CodeX\View|null Экземпляр View для рендеринга
     */
    protected ?\CodeX\View $viewInstance = null;

    /**
     * @var bool Флаг, указывающий что слоты должны быть экранированы по умолчанию
     */
    protected bool $escapeSlotsByDefault = true;

    public function __construct(array $data = [])
    {
        $this->data = $data;
        $this->initialize();
    }

    /**
     * Инициализация компонента - может быть переопределена в дочерних классах
     */
    protected function initialize(): void
    {
        // Базовая реализация - может быть пустой
    }

    public function render(): string
    {
        try {
            if (!$this->view) {
                return $this->renderComponent();
            }

            return $this->renderView();
        } catch (Throwable $e) {
            error_log("Component rendering error in " . static::class . ": " . $e->getMessage());

            if ($this->getViewInstance()?->debugMode ?? false) {
                return '<div class="component-error" style="border: 2px solid red; padding: 10px; margin: 10px 0;">' .
                    '<strong>Component Error:</strong> ' . htmlspecialchars($e->getMessage()) .
                    '<br><small>File: ' . $e->getFile() . ':' . $e->getLine() . '</small>' .
                    '</div>';
            }

            return '<div class="component-error">Component rendering failed</div>';
        }
    }

    public function setSlot(string $name, string $content): void
    {
        $this->slots[$name] = trim($content);
    }

    public function getSlot(string $name, string $default = ''): string
    {
        return $this->slots[$name] ?? $default;
    }

    public function hasSlot(string $name): bool
    {
        return isset($this->slots[$name]) && $this->slots[$name] !== '';
    }

    public function slot(string $name, string $default = ''): string
    {
        return $this->getSlot($name, $default);
    }

    /**
     * Возвращает содержимое слота без экранирования
     * ВНИМАНИЕ: Используйте только для доверенного содержимого
     */
    public function unescapedSlot(string $name, string $default = ''): string
    {
        $content = $this->getSlot($name, $default);

        // В режиме отладки добавляем предупреждение для неэкранированного контента
        if ($content !== $default && ($this->getViewInstance()?->debugMode ?? false)) {
            return '<!-- WARNING: UNSAFE UNESCAPED SLOT CONTENT -->' . $content;
        }

        return $content;
    }

    public function setData(array $data): void
    {
        $this->data = array_merge($this->data, $data);
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function getView(): string
    {
        return $this->view;
    }

    public function setViewInstance(\CodeX\View $view): void
    {
        $this->viewInstance = $view;
    }

    public function getViewInstance(): ?\CodeX\View
    {
        return $this->viewInstance;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }

    /**
     * Проверяет существование данных по ключу
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    /**
     * Удаляет данные по ключу
     */
    public function remove(string $key): void
    {
        unset($this->data[$key]);
    }

    /**
     * Очищает все данные компонента
     */
    public function clearData(): void
    {
        $this->data = [];
    }

    protected function renderView(): string
    {
        $viewInstance = $this->getViewInstance();
        if (!$viewInstance) {
            $this->resolveViewInstance();
            $viewInstance = $this->getViewInstance();
        }

        if (!$viewInstance) {
            throw new RuntimeException(
                "Не удалось найти View instance для рендеринга компонента: " . static::class
            );
        }

        $data = $this->getViewData();

        return $viewInstance->makePartial($this->view, $data);
    }

    protected function getViewData(): array
    {
        $component = $this;
        return array_merge($this->data, [
            'slots' => $this->slots,
            'component' => $this,
            'slot' => function(string $name, string $default = '') use ($component) {
                return $component->escapeSlotsByDefault
                    ? \CodeX\View::e($component->getSlot($name, $default))
                    : $component->getSlot($name, $default);
            },
            'hasSlot' => fn(string $name) => $this->hasSlot($name),
            'unescapedSlot' => fn(string $name, string $default = '') => $this->getSlot($name, $default),
        ]);
    }

    protected function renderComponent(): string
    {
        throw new RuntimeException(
            "Метод renderComponent() должен быть реализован в классе " . static::class .
            " или должно быть установлено свойство \$view"
        );
    }

    protected function setView(string $view): void
    {
        if (empty($view)) {
            throw new RuntimeException("View path cannot be empty");
        }

        $this->view = $view;
    }

    protected function resolveViewInstance(): void
    {
        if ($this->viewInstance) {
            return;
        }

        // Получаем из контейнера (надёжнее)
        try {
            $this->viewInstance = \CodeX\Application::getInstance()
                ->container->get(\CodeX\View::class);
            return;
        } catch (\Throwable $e) {
            error_log("Не удалось получить представление из контейнера.: " . $e->getMessage());
        }

        throw new RuntimeException("Невозможно разрешить просмотр экземпляра для компонента");
    }

    public function __get(string $name): mixed
    {
        if (array_key_exists($name, $this->data)) {
            return $this->data[$name];
        }

        // Для лучшей отладки выбрасываем исключение при попытке доступа к несуществующему свойству
        if (($this->getViewInstance()?->debugMode ?? false)) {
            throw new RuntimeException(
                "Undefined property '" . $name . "' in component " . static::class
            );
        }

        return null;
    }

    public function __set(string $name, mixed $value): void
    {
        $this->data[$name] = $value;
    }

    public function __isset(string $name): bool
    {
        return isset($this->data[$name]);
    }

    public function __unset(string $name): void
    {
        unset($this->data[$name]);
    }

    public function getSlots(): array
    {
        return $this->slots;
    }

    public function setSlots(array $slots): void
    {
        $this->slots = $slots;
    }

    public function clearSlots(): void
    {
        $this->slots = [];
    }

    /**
     * Устанавливает флаг экранирования слотов по умолчанию
     */
    public function setEscapeSlotsByDefault(bool $escape): void
    {
        $this->escapeSlotsByDefault = $escape;
    }

    /**
     * Возвращает флаг экранирования слотов по умолчанию
     */
    public function getEscapeSlotsByDefault(): bool
    {
        return $this->escapeSlotsByDefault;
    }

    /**
     * Магический метод для строкового представления компонента
     */
    public function __toString(): string
    {
        return $this->render();
    }

    /**
     * Подготавливает данные для сериализации
     */
    public function __serialize(): array
    {
        return [
            'data' => $this->data,
            'view' => $this->view,
            'slots' => $this->slots,
        ];
    }

    /**
     * Восстанавливает состояние после десериализации
     */
    public function __unserialize(array $data): void
    {
        $this->data = $data['data'] ?? [];
        $this->view = $data['view'] ?? '';
        $this->slots = $data['slots'] ?? [];
        $this->viewInstance = null; // View instance не сериализуется
        $this->escapeSlotsByDefault = true;
    }
}