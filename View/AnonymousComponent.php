<?php
declare(strict_types=1);
// CodeX/View/AnonymousComponent.php
trait AnonymousComponent
{
    public function __construct(public array $data = [])
    {
    }

    public function render(): string
    {
        return $this->viewInstance->makePartial($this->view, $this->data);
    }
}