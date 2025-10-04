<?php
namespace CodeX\View\Components;

use CodeX\View\Component;

class Alert extends Component
{
    public string $type;
    public string $title;

    protected function initialize(): void
    {
        $this->type = $this->get('type', 'info');
        $this->title = $this->get('title', 'Уведомление');
        $this->setView('components.alert'); // шаблон: views/components/alert.php
    }
}