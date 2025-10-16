<?php
declare(strict_types=1);

namespace CodeX\Router;

/**
 * Класс для представления определения маршрута
 */
readonly class Definition
{
    public function __construct(
        public string                $uri,
        public string|array|\Closure $action,
        public array                 $middleware = [],
        public array                 $tags = []
    ) {
        $this->validateAction();
    }

    private function validateAction(): void
    {
        if (!is_string($this->action) &&
            !is_array($this->action) &&
            !$this->action instanceof \Closure) {
            throw new \InvalidArgumentException(
                'Действие маршрута должно быть строкой, массивом или Closure'
            );
        }

        if (is_array($this->action) && count($this->action) !== 2) {
            throw new \InvalidArgumentException(
                'Массив действия должен содержать ровно 2 элемента: [Controller, method]'
            );
        }
    }

    /**
     * Проверяет, является ли действие Closure
     */
    public function isClosure(): bool
    {
        return $this->action instanceof \Closure;
    }

    /**
     * Проверяет, является ли действие строкой в формате Controller@method
     */
    public function isControllerString(): bool
    {
        return is_string($this->action) && str_contains($this->action, '@');
    }

    /**
     * Возвращает чистое действие (без оберток)
     */
    public function getRawAction(): string|array|\Closure
    {
        return $this->action;
    }
    /**
     * Добавляет тег к определению
     */
    public function withTag(string $tag): self
    {
        return new self(
            $this->uri,
            $this->action,
            $this->middleware,
            array_merge($this->tags, [$tag])
        );
    }

    /**
     * Проверяет, содержит ли определение тег
     */
    public function hasTag(string $tag): bool
    {
        return in_array($tag, $this->tags, true);
    }

    /**
     * Возвращает все теги
     */
    public function getTags(): array
    {
        return $this->tags;
    }
}