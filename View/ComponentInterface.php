<?php
declare(strict_types=1);

namespace CodeX\View;

use CodeX\View;

interface ComponentInterface
{
    /**
     * Рендеринг компонента
     */
    public function render(): string;

    /**
     * Установка содержимого слота
     */
    public function setSlot(string $name, string $content): void;

    /**
     * Получение содержимого слота
     */
    public function getSlot(string $name, string $default = ''): string;

    /**
     * Проверка существования слота
     */
    public function hasSlot(string $name): bool;

    /**
     * Установка экземпляра View
     */
    public function setViewInstance(View $view): void;

    /**
     * Получение экземпляра View
     */
    public function getViewInstance(): ?View;
}