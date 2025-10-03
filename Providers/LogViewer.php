<?php

declare(strict_types=1);

namespace CodeX\Providers;

use CodeX\Provider;
use CodeX\Http\Request;
use CodeX\Http\Response;
use CodeX\Support\Facade\Route;

class LogViewer extends Provider
{
    public function register(): void
    {
        // Ничего не регистрируем в контейнере
    }

    public function boot(): void
    {
        // Добавляем маршрут для просмотра логов (только в debug-режиме)
        if (!($this->application->config['app']['debug'] ?? false)) {
            return;
        }

        // Маршрут: GET /_debug/logs
        Route::get('/_debug/logs', [$this, 'showLogs']);
        Route::get('/_debug/logs/{date}', [$this, 'showLogs']);
    }

    public function showLogs(Request $request, ?string $date = null): Response
    {
        $logDir = $this->application->config['app']['log_path'] ?? dirname(__DIR__, 2) . '/storage/logs';
        $logDir = is_file($logDir) ? dirname($logDir) : rtrim($logDir, DIRECTORY_SEPARATOR);

        // Список доступных файлов логов
        $logFiles = [];
        if (is_dir($logDir)) {
            $files = glob($logDir . '/error-*.log');
            foreach ($files as $file) {
                $name = basename($file);
                if (preg_match('/error-(\d{4}-\d{2}-\d{2})\.log/', $name, $matches)) {
                    $logFiles[] = $matches[1];
                }
            }
            rsort($logFiles); // Сначала новые
        }

        $selectedDate = $date ?: (date('Y-m-d'));
        $currentFile = $logDir . DIRECTORY_SEPARATOR . 'error-' . $selectedDate . '.log';
        $logs = file_exists($currentFile) ? file_get_contents($currentFile) : '';

        $html = '<!DOCTYPE html>
        <html>
        <head>
            <title>CodeX Log Viewer</title>
            <style>
                body { font-family: monospace; margin: 20px; background: #1e1e1e; color: #d4d4d4; }
                .header { margin-bottom: 20px; }
                .date-select { margin-bottom: 15px; }
                select { background: #2d2d2d; color: #d4d4d4; padding: 5px; border: 1px solid #555; }
                pre { background: #2d2d2d; padding: 15px; border-radius: 5px; overflow-x: auto; }
                .log-entry { margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid #3a3a3a; }
                .log-header { color: #ff6b6b; font-weight: bold; }
            </style>
        </head>
        <body>
            <div class="header">
                <h1>CodeX Log Viewer</h1>
                <div class="date-select">
                    <form method="GET">
                        <label>Дата: 
                            <select name="date" onchange="this.form.submit()">
                                ' . $this->renderDateOptions($logFiles, $selectedDate) . '
                            </select>
                        </label>
                    </form>
                </div>
            </div>';

        if ($logs) {
            // Разделяем лог на записи
            $entries = explode("\n---\n", trim($logs));
            $html .= '<div class="logs">';
            foreach (array_reverse($entries) as $entry) {
                if (trim($entry)) {
                    $html .= '<div class="log-entry"><pre>' . htmlspecialchars($entry, ENT_QUOTES, 'UTF-8') . '</pre></div>';
                }
            }
            $html .= '</div>';
        } else {
            $html .= '<p>Нет записей для ' . htmlspecialchars($selectedDate, ENT_QUOTES, 'UTF-8') . '</p>';
        }

        $html .= '</body></html>';

        $response = $this->application->container->make(Response::class);
        $response->setContent($html);
        return $response;
    }

    private function renderDateOptions(array $dates, string $selected): string
    {
        $options = '';
        foreach ($dates as $date) {
            $selectedAttr = ($date === $selected) ? 'selected' : '';
            $options .= '<option value="' . $date . '" ' . $selectedAttr . '>' . $date . '</option>';
        }
        return $options;
    }
}