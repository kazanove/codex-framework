<?php
declare(strict_types=1);

namespace CodeX\Debug;

use CodeX\Http\Response;
use JsonException;

class Bar
{
    private array $data = [];

    public function __construct(private bool $enabled = false)
    {
    }

    public function addData(string $key, mixed $value): void
    {
        if (!$this->enabled) {
            return;
        }
        $this->data[$key] = $value;
    }

    public function injectToResponse(Response $response): void
    {
        if (!$this->enabled || PHP_SAPI === 'cli') {
            return;
        }

        $content = $response->getContent();
        $debugHtml = $this->render();
        if (str_contains($content, '</body>')) {
            $content = str_replace('</body>', $debugHtml . '</body>', $content);
        } else {
            $content .= $debugHtml;
        }
        $response->setContent($content);
    }

    private function render(): string
    {
        $requestPanel = $this->renderRequestPanel();
        $performancePanel = $this->renderPerformancePanel();
        $memoryPanel = $this->renderMemoryPanel();

        $panels = ['запрос' => $requestPanel, 'производительность' => $performancePanel, 'память' => $memoryPanel,];

        $html = '<style>';
        $html .= '#codex-debug-bar { position: fixed; bottom: 0; left: 0; right: 0; background: #2c3e50; color: #ecf0f1; font-family: -apple-system, BlinkMacSystemFont, sans-serif; font-size: 12px; z-index: 99999; box-shadow: 0 -2px 10px rgba(0,0,0,0.3); }';
        $html .= '.debug-bar-tabs { display: flex; border-bottom: 1px solid #34495e; }';
        $html .= '.debug-bar-tab { padding: 8px 16px; cursor: pointer; border-right: 1px solid #34495e; background: #34495e; }';
        $html .= '.debug-bar-tab.active { background: #e74c3c; }';
        $html .= '.debug-bar-panels { padding: 10px; background: #34495e; max-height: 300px; overflow-y: auto; }';
        $html .= '.debug-bar-panel { display: none; }';
        $html .= '.debug-bar-panel.active { display: block; }';
        $html .= '.debug-bar-panel pre { background: #2c3e50; padding: 8px; border-radius: 4px; overflow-x: auto; margin: 0; white-space: pre-wrap; font-size: 12px; }';
        $html .= '</style>';

        $html .= '<div id="codex-debug-bar">';
        $html .= '<div class="debug-bar-tabs">';

        // 👇 Цикл по ключам — здесь ошибка?
        foreach (array_keys($panels) as $panelName) {
            $panelNameStr = is_string($panelName) ? $panelName : 'неизвестный';
            $html .= '<div class="debug-bar-tab" data-panel="' . htmlspecialchars($panelNameStr, ENT_QUOTES, 'UTF-8') . '">' . ucfirst($panelNameStr) . '</div>';
        }
        $html .= '</div>';
        $html .= '<div class="debug-bar-panels">';
        foreach ($panels as $panelName => $panelContent) {
            $panelNameStr = is_string($panelName) ? $panelName : 'неизвестный';
            $panelContentStr = is_string($panelContent) ? $panelContent : $this->toString($panelContent);
            $html .= '<div class="debug-bar-panel" id="debug-panel-' . htmlspecialchars($panelNameStr, ENT_QUOTES, 'UTF-8') . '">' . $panelContentStr . '</div>';
        }

        $html .= '</div>';
        $html .= '</div>';

        $html .= '<script>';
        $html .= 'document.addEventListener(\'DOMContentLoaded\', function() {';
        $html .= '  let bar = document.getElementById(\'codex-debug-bar\');
                    let toggleBtn = document.createElement(\'div\');
                    toggleBtn.innerHTML = \'▲\';
                    toggleBtn.style.position = \'absolute\';
                    toggleBtn.style.top = \'-20px\';
                    toggleBtn.style.right = \'10px\';
                    toggleBtn.style.cursor = \'pointer\';
                    toggleBtn.style.background = \'#e74c3c\';
                    toggleBtn.style.padding = \'2px 6px\';
                    toggleBtn.style.borderRadius = \'3px\';
                    toggleBtn.onclick = function() {
                        bar.style.bottom = bar.style.bottom === \'0px\' ? \'-300px\' : \'0px\';
                        toggleBtn.innerHTML = bar.style.bottom === \'0px\' ? \'▲\' : \'▼\';
                    };
                    bar.appendChild(toggleBtn);';
        $html .= '    let tabs = document.querySelectorAll(\'.debug-bar-tab\');';
        $html .= '    let panels = document.querySelectorAll(\'.debug-bar-panel\');';
        $html .= '    if (tabs[0]) tabs[0].classList.add(\'active\');';
        $html .= '    if (panels[0]) panels[0].classList.add(\'active\');';
        $html .= '    tabs.forEach(tab => {';
        $html .= '        tab.addEventListener(\'click\', function() {';
        $html .= '            let panelName = this.getAttribute(\'data-panel\');';
        $html .= '            tabs.forEach(t => t.classList.remove(\'active\'));';
        $html .= '            panels.forEach(p => p.classList.remove(\'active\'));';
        $html .= '            this.classList.add(\'active\');';
        $html .= '            let targetPanel = document.getElementById(\'debug-panel-\' + panelName);';
        $html .= '            if (targetPanel) {targetPanel.classList.add(\'active\');}';
        $html .= '        });';
        $html .= '    });';
        $html .= '});';

        $html .= '</script>';

        return $html;
    }

    /**
     * @throws JsonException
     */
    private function renderRequestPanel(): string
    {
        $request = $this->data['request'] ?? null;
        if (!$request) {
            return '<p>Запрос данных недоступен</p>';
        }

        $info = ['Метод' => $request->getMethod(), 'URI' => $request->getRequestUri(), 'Путь' => $request->getPathInfo(), 'Запрос' => $this->toString($request->getQueryString()), 'IP' => $_SERVER['REMOTE_ADDR'] ?? 'N/A', 'Пользовательский агент' => $_SERVER['HTTP_USER_AGENT'] ?? 'N/A',];

        $output = "<h4>Запросить информацию</h4><pre>";
        foreach ($info as $key => $value) {
            $safeValue = $this->toString($value);
            $output .= sprintf("%-12s: %s\n", $key, $safeValue);
        }
        $output .= "</pre>";

        $getOutput = print_r($_GET, true);
        $postOutput = print_r($_POST, true);
        $sessionOutput = print_r($_SESSION, true);
        $cookieOutput = print_r($_COOKIE, true);

        $output .= "<h4>Параметры GET</h4><pre>" . htmlspecialchars($getOutput, ENT_QUOTES, 'UTF-8') . "</pre>";
        $output .= "<h4>Параметры POST</h4><pre>" . htmlspecialchars($postOutput, ENT_QUOTES, 'UTF-8') . "</pre>";
        $output .= "<h4>Параметры SESSION</h4><pre>" . htmlspecialchars($sessionOutput, ENT_QUOTES, 'UTF-8') . "</pre>";
        $output .= "<h4>Параметры COOKIE</h4><pre>" . htmlspecialchars($cookieOutput, ENT_QUOTES, 'UTF-8') . "</pre>";


        return $output;
    }

    /**
     * @throws JsonException
     */
    private function toString(mixed $value): string
    {
        return match (true) {
            is_string($value) => $value,
            is_numeric($value), is_object($value) && method_exists($value, '__toString') => (string)$value,
            is_bool($value) => $value ? 'true' : 'false',
            is_array($value) => json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[JSON Error]',
            is_object($value) => get_class($value) . ' Object',
            is_null($value) => 'NULL',
            default => 'Unknown',
        };
    }

    private function renderPerformancePanel(): string
    {
        $startTime = $this->data['start_time'] ?? microtime(true);
        $endTime = microtime(true);
        $executionTime = number_format(($endTime - $startTime) * 1000, 2);

        $includedFiles = count(get_included_files());
        $declaredClasses = count(get_declared_classes());

        $output = "<h4>Производительность</h4><pre>";
        $output .= sprintf("Время выполнения: %s ms\n", $executionTime);
        $output .= sprintf("Включенные файлы: %d\n", $includedFiles);
        $output .= sprintf("Объявленные классы: %d\n", $declaredClasses);
        $output .= "</pre>";

        return $output;
    }

    private function renderMemoryPanel(): string
    {
        $memory = memory_get_usage();
        $memoryPeak = memory_get_peak_usage();
        $memoryFormatted = $this->formatBytes($memory);
        $memoryPeakFormatted = $this->formatBytes($memoryPeak);

        $output = "<h4>Использование памяти</h4><pre>";
        $output .= sprintf("Текущий: %s\n", $memoryFormatted);
        $output .= sprintf("Пик:    %s\n", $memoryPeakFormatted);
        $output .= "</pre>";

        return $output;
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= 1024 ** $pow;
        return round($bytes, 2) . ' ' . $units[$pow];
    }
}