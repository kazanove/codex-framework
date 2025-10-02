<?php

declare(strict_types=1);

namespace CodeX\Debug;

use CodeX\Http\Response;

class Bar
{


    private array $data = [];

    public function __construct(private readonly bool $enabled = false)
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

        // Проверяем Content-Type: внедряем только в HTML
        $isHtml = false;
        foreach ($response->getHeaders() as $name => $value) {
            if (strtolower($name) === 'content-type' && str_contains(strtolower($value), 'text/html')) {
                $isHtml = true;
                break;
            }
        }

        if (!$isHtml) {
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
        $panels = ['request' => $this->renderRequestPanel(), 'performance' => $this->renderPerformancePanel(), 'memory' => $this->renderMemoryPanel(), 'logs' => $this->renderLogsPanel(), 'container' => $this->renderContainerPanel(),];

        $panels = array_filter($panels, static fn($v) => $v !== '');

        if (empty($panels)) {
            return '';
        }

        // Единый стиль — компактный и читаемый
        $style = '
            #codex-debug-bar {
                position: fixed;
                bottom: 0;
                left: 0;
                right: 0;
                background: #2c3e50;
                color: #ecf0f1;
                font: 12px -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
                z-index: 99999;
                box-shadow: 0 -2px 10px rgba(0,0,0,0.3);
                max-height: 300px;
                display: none;
            }
            #codex-debug-bar.visible { display: block; }
            .debug-bar-header {
                padding: 8px 16px;
                background: #e74c3c;
                font-weight: bold;
                cursor: pointer;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            .debug-bar-tabs {
                display: flex;
                background: #34495e;
                border-bottom: 1px solid #2c3e50;
            }
            .debug-bar-tab {
                padding: 10px 16px;
                cursor: pointer;
                border-right: 1px solid #2c3e50;
                background: #34495e;
            }
            .debug-bar-tab:hover { background: #3d566e; }
            .debug-bar-tab.active { background: #e74c3c; }
            .debug-bar-panels {
                padding: 12px;
                background: #34495e;
                max-height: 250px;
                overflow-y: auto;
            }
            .debug-bar-panel { display: none; }
            .debug-bar-panel.active { display: block; }
            .debug-bar-panel pre {
                background: #2c3e50;
                padding: 10px;
                border-radius: 4px;
                overflow-x: auto;
                margin: 8px 0;
                white-space: pre-wrap;
                font-size: 12px;
                line-height: 1.4;
            }
            .debug-bar-panel table {
                width: 100%;
                border-collapse: collapse;
                margin: 8px 0;
            }
            .debug-bar-panel th,
            .debug-bar-panel td {
                padding: 8px;
                text-align: left;
                border-bottom: 1px solid #555;
            }
            .debug-bar-panel th { background: #2c3e50; }
        ';

        // Маппинг имён панелей
        $panelLabels = ['request' => 'Запрос', 'performance' => 'Производительность', 'memory' => 'Память', 'logs' => 'Логи', 'container' => 'Контейнер',];

        $html = '<div id="codex-debug-bar">';
        $html .= '<div class="debug-bar-header" onclick="toggleCodexDebugBar()">';
        $html .= '<span>CodeX Debug Bar</span>';
        $html .= '<small>Ctrl+Shift+D</small>';
        $html .= '</div>';

        // Вкладки
        $html .= '<div class="debug-bar-tabs">';
        foreach (array_keys($panels) as $name) {
            $label = $panelLabels[$name] ?? ucfirst($name);
            $html .= '<div class="debug-bar-tab" data-panel="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '">' . $label . '</div>';
        }
        $html .= '</div>';

        // Панели контента
        $html .= '<div class="debug-bar-panels">';
        foreach ($panels as $name => $content) {
            $html .= '<div class="debug-bar-panel" id="debug-panel-' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '">' . $content . '</div>';
        }
        $html .= '</div>';
        $html .= '</div>';

        // JS — один блок, без дублирования
        $js = '
            <script>
            (function() {
                function toggleCodexDebugBar() {
                    var bar = document.getElementById("codex-debug-bar");
                    bar.classList.toggle("visible");
                }

                document.addEventListener("keydown", function(e) {
                    if (e.ctrlKey && e.shiftKey && e.key === "D") {
                        toggleCodexDebugBar();
                        e.preventDefault();
                    }
                });

                document.addEventListener("DOMContentLoaded", function() {
                    var tabs = document.querySelectorAll(".debug-bar-tab");
                    var panels = document.querySelectorAll(".debug-bar-panel");
                    if (tabs[0]) {
                        tabs[0].classList.add("active");
                        panels[0].classList.add("active");
                    }
                    tabs.forEach(function(tab) {
                        tab.addEventListener("click", function() {
                            tabs.forEach(t => t.classList.remove("active"));
                            panels.forEach(p => p.classList.remove("active"));
                            this.classList.add("active");
                            var panelName = this.getAttribute("data-panel");
                            var targetPanel = document.getElementById("debug-panel-" + panelName);
                            if (targetPanel) targetPanel.classList.add("active");
                        });
                    });
                });
            })();
            </script>
        ';

        return "<style>{$style}</style>{$html}{$js}";
    }

    private function renderRequestPanel(): string
    {
        $data = $this->data['request_data'] ?? null;
        if (!$data) {
            return '<p>Данные запроса недоступны</p>';
        }

        $output = "<h4>Основное</h4><pre>";
        $output .= sprintf("%-15s: %s\n", 'Метод', $data['method'] ?? 'N/A');
        $output .= sprintf("%-15s: %s\n", 'URI', $data['uri'] ?? 'N/A');
        $output .= sprintf("%-15s: %s\n", 'Путь', $data['path'] ?? 'N/A');
        $output .= sprintf("%-15s: %s\n", 'IP', $data['ip'] ?? 'N/A');
        $output .= sprintf("%-15s: %s\n", 'User-Agent', $data['user_agent'] ?? 'N/A');
        $output .= "</pre>";

        if (!empty($data['get'])) {
            $output .= "<h4>GET</h4><pre>" . htmlspecialchars(print_r($data['get'], true), ENT_QUOTES, 'UTF-8') . "</pre>";
        }
        if (!empty($data['post'])) {
            $output .= "<h4>POST</h4><pre>" . htmlspecialchars(print_r($data['post'], true), ENT_QUOTES, 'UTF-8') . "</pre>";
        }
        if (!empty($data['cookies'])) {
            $output .= "<h4>COOKIES</h4><pre>" . htmlspecialchars(print_r($data['cookies'], true), ENT_QUOTES, 'UTF-8') . "</pre>";
        }
        if (!empty($data['session'])) {
            $output .= "<h4>SESSION</h4><pre>" . htmlspecialchars(print_r($data['session'], true), ENT_QUOTES, 'UTF-8') . "</pre>";
        }

        return $output;
    }

    private function renderPerformancePanel(): string
    {
        $startTime = $this->data['start_time'] ?? null;
        if ($startTime === null) {
            return '';
        }

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

        $output = "<h4>Память</h4><pre>";
        $output .= sprintf("Текущая:  %s\n", $this->formatBytes($memory));
        $output .= sprintf("Пик:      %s\n", $this->formatBytes($memoryPeak));
        $output .= "</pre>";

        return $output;
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        if ($bytes === 0) {
            return '0 B';
        }
        $pow = floor(log($bytes) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1024 ** $pow);
        return round($bytes, 2) . ' ' . $units[$pow];
    }

    private function renderLogsPanel(): string
    {
        $logs = $this->data['logs'] ?? [];
        if (empty($logs)) {
            return '<p>Нет записей лога</p>';
        }

        $output = '<h4>Логи (' . count($logs) . ')</h4>';
        $output .= '<table><thead><tr><th>Уровень</th><th>Сообщение</th><th>Время</th></tr></thead><tbody>';

        foreach ($logs as $log) {
            $levelColor = match ($log['level']) {
                'error', 'critical', 'alert', 'emergency' => '#e74c3c',
                'warning' => '#f39c12',
                'notice' => '#3498db',
                'info' => '#2ecc71',
                'debug' => '#95a5a6',
                default => '#ecf0f1',
            };

            $output .= '<tr>';
            $output .= '<td style="color:' . $levelColor . '; font-weight:bold;">' . strtoupper($log['level']) . '</td>';
            $output .= '<td>' . htmlspecialchars($log['message'], ENT_QUOTES, 'UTF-8') . '</td>';
            $output .= '<td>' . ($log['time'] ?? '') . '</td>';
            $output .= '</tr>';
        }

        $output .= '</tbody></table>';
        return $output;
    }

    private function renderContainerPanel(): string
    {
        $bindings = $this->data['container_bindings'] ?? [];
        $instances = $this->data['container_instances'] ?? [];

        if (empty($bindings) && empty($instances)) {
            return '<p>Контейнер пуст</p>';
        }

        $output = '<h4>Контейнер</h4>';
        if (!empty($bindings)) {
            $output .= '<h5>Привязки (' . count($bindings) . ')</h5><pre>' . htmlspecialchars(print_r($bindings, true), ENT_QUOTES, 'UTF-8') . '</pre>';
        }
        if (!empty($instances)) {
            $output .= '<h5>Экземпляры (' . count($instances) . ')</h5><pre>' . htmlspecialchars(print_r($instances, true), ENT_QUOTES, 'UTF-8') . '</pre>';
        }

        return $output;
    }
}