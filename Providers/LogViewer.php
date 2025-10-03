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
        Route::get('/_debug/logs', [self::class, 'showLogs']);
        Route::get('/_debug/logs/{date}', [self::class, 'showLogs']);
    }

    public function showLogs(Request $request, ?string $date = null): Response
    {
        $logDir = $this->application->config['app']['log_path'] ?? dirname(__DIR__, 2) . '/storage/logs';
        $logDir = is_file($logDir) ? dirname($logDir) : rtrim($logDir, DIRECTORY_SEPARATOR);

        // Находим все файлы логов
        $logFiles = $this->findLogFiles($logDir);

        $selectedDate = $date ?: date('Y-m-d');

        // Получаем логи за выбранную дату
        $logs = $this->getLogsForDate($logDir, $logFiles, $selectedDate);

        // Парсим записи
        $logEntries = $this->parseLogEntries($logs);

        // Рендерим страницу
        $html = $this->renderLogsPage($selectedDate, $logEntries, array_keys($logFiles));

        $response = $this->application->container->make(Response::class);
        $response->setContent($html);
        return $response;
    }

    /**
     * Рекурсивно находит все файлы логов error-*.log
     */
    private function findLogFiles(string $logDir): array
    {
        $logFiles = [];
        if (!is_dir($logDir)) {
            return $logFiles;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($logDir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && preg_match('/error-(\d{4}-\d{2}-\d{2})\.log$/', $file->getFilename(), $matches)) {
                $logFiles[$matches[1]][] = $file->getPathname();
            }
        }

        krsort($logFiles); // Сортируем даты по убыванию
        return $logFiles;
    }

    /**
     * Получает содержимое всех логов за указанную дату
     */
    private function getLogsForDate(string $logDir, array $logFiles, string $date): string
    {
        $logs = '';
        if (isset($logFiles[$date])) {
            foreach ($logFiles[$date] as $filePath) {
                if (file_exists($filePath)) {
                    $logs .= file_get_contents($filePath) . "\n---\n";
                }
            }
        }
        return $logs;
    }

    /**
     * Извлекает уровень лога из записи
     */
    private function getLogLevelFromEntry(string $entry): string
    {
        // Формат от Debug::logException(): [дата] Error: сообщение
        if (preg_match('/^\[.*?\]\s*(\w+):/', $entry, $matches)) {
            $level = strtolower($matches[1]);
            return match($level) {
                'error', 'warning', 'notice', 'info', 'debug' => $level,
                default => 'error'
            };
        }

        // Резервный парсинг по ключевым словам
        if (preg_match('/(exception|fatal|stack trace|error)/i', $entry)) {
            return 'error';
        }
        if (preg_match('/warning/i', $entry)) {
            return 'warning';
        }
        if (preg_match('/notice/i', $entry)) {
            return 'notice';
        }
        if (preg_match('/debug/i', $entry)) {
            return 'debug';
        }

        return 'info';
    }

    /**
     * Извлекает время из записи
     */
    private function extractTime(string $entry): string
    {
        if (preg_match('/\[([\d\-:\.]+)\]/', $entry, $matches)) {
            return $matches[1];
        }
        return 'Unknown time';
    }

    /**
     * Извлекает основное сообщение из записи
     */
    private function extractMessage(string $entry): string
    {
        // Ищем сообщение после уровня лога
        if (preg_match('/\]:\s*(.*?)(?:\nStack trace:|$)/s', $entry, $matches)) {
            return trim($matches[1]);
        }
        // Если не найдено, возвращаем первые 200 символов
        return substr(trim($entry), 0, 200) . (strlen($entry) > 200 ? '...' : '');
    }

    /**
     * Парсит записи лога и сортирует в обратном порядке
     */
    private function parseLogEntries(string $logs): array
    {
        if (empty($logs)) {
            return [];
        }

        $entries = explode("\n---\n", trim($logs));
        // Удаляем пустые записи и сортируем новые сверху
        return array_filter(array_reverse($entries), fn($e) => !empty(trim($e)));
    }

    /**
     * Генерирует HTML-опции для выпадающего списка дат
     */
    private function renderDateOptions(array $dates, string $selected): string
    {
        $options = '';
        foreach ($dates as $date) {
            $selectedAttr = ($date === $selected) ? 'selected' : '';
            $options .= '<option value="' . htmlspecialchars($date, ENT_QUOTES, 'UTF-8') . '" ' . $selectedAttr . '>' . $date . '</option>';
        }
        return $options;
    }

    private function renderLogsPage(string $selectedDate, array $logEntries, array $availableDates): string
    {
        // Собираем статистику по уровням для чекбоксов
        $levelStats = ['error' => 0, 'warning' => 0, 'notice' => 0, 'info' => 0, 'debug' => 0];
        $entriesHtml = '';

        foreach ($logEntries as $entry) {
            $level = $this->getLogLevelFromEntry($entry);
            if (isset($levelStats[$level])) {
                $levelStats[$level]++;
            }

            $levelClass = match ($level) {
                'error', 'critical', 'alert', 'emergency' => 'log-error',
                'warning' => 'log-warning',
                'notice' => 'log-notice',
                'info' => 'log-info',
                'debug' => 'log-debug',
                default => 'log-default',
            };

            $entriesHtml .= '
            <div class="log-entry ' . $levelClass . '" data-level="' . $level . '">
                <div class="log-header">
                    <span class="log-level">' . strtoupper($level) . '</span>
                    <span class="log-time">' . $this->extractTime($entry) . '</span>
                    <button class="toggle-details">Подробности</button>
                </div>
                <div class="log-message" data-search-text="' . htmlspecialchars($this->extractMessage($entry), ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($this->extractMessage($entry), ENT_QUOTES, 'UTF-8') . '</div>
                <div class="log-details" style="display:none;">
                    <pre>' . htmlspecialchars($entry, ENT_QUOTES, 'UTF-8') . '</pre>
                </div>
            </div>';
        }

        if (empty($entriesHtml)) {
            $entriesHtml = '<div class="no-logs">Нет записей для ' . htmlspecialchars($selectedDate, ENT_QUOTES, 'UTF-8') . '</div>';
        }

        return '<!DOCTYPE html>
        <html lang="ru">
        <head>
            <meta charset="utf-8">
            <title>CodeX Log Viewer</title>
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <style>
                :root {
                    --bg-dark: #1e1e1e;
                    --bg-card: #2d2d2d;
                    --text-primary: #d4d4d4;
                    --text-secondary: #a9a9a9;
                    --border: #3a3a3a;
                    --error: #f44336;
                    --warning: #ff9800;
                    --info: #2196f3;
                    --debug: #9c27b0;
                    --success: #4caf50;
                }
                
                * { box-sizing: border-box; }
                
                body {
                    font-family: "Segoe UI", system-ui, -apple-system, sans-serif;
                    background: var(--bg-dark);
                    color: var(--text-primary);
                    margin: 0;
                    padding: 20px;
                    line-height: 1.6;
                }
                
                .container { max-width: 1200px; margin: 0 auto; }
                
                header {
                    display: flex;
                    justify-content: space-between;
                    align-items: flex-start;
                    margin-bottom: 24px;
                    padding-bottom: 16px;
                    border-bottom: 1px solid var(--border);
                    flex-wrap: wrap;
                    gap: 16px;
                }
                
                h1 {
                    margin: 0;
                    font-size: 24px;
                    font-weight: 600;
                    color: var(--success);
                }
                
                .controls { display: flex; gap: 12px; flex-wrap: wrap; align-items: center; }
                
                .filter-group, .search-group {
                    display: flex;
                    align-items: center;
                    gap: 8px;
                    flex-wrap: wrap;
                }
                
                label { display: flex; align-items: center; gap: 4px; cursor: pointer; }
                
                input[type="checkbox"] {
                    width: 14px;
                    height: 14px;
                    accent-color: var(--success);
                }
                
                input[type="text"] {
                    background: var(--bg-card);
                    color: var(--text-primary);
                    border: 1px solid var(--border);
                    padding: 8px 12px;
                    border-radius: 4px;
                    font-size: 14px;
                    min-width: 200px;
                }
                
                select {
                    background: var(--bg-card);
                    color: var(--text-primary);
                    border: 1px solid var(--border);
                    padding: 8px 12px;
                    border-radius: 4px;
                    font-size: 14px;
                    cursor: pointer;
                }
                
                .logs-container {
                    display: flex;
                    flex-direction: column;
                    gap: 12px;
                    max-height: calc(100vh - 200px);
                    overflow-y: auto;
                    padding-right: 8px;
                }
                
                .log-entry {
                    background: var(--bg-card);
                    border-radius: 6px;
                    padding: 16px;
                    transition: all 0.2s ease;
                    border-left: 4px solid var(--border);
                    opacity: 1;
                    transform: translateX(0);
                }
                
                .log-entry.hidden {
                    display: none;
                }
                
                .log-entry.fade-out {
                    opacity: 0;
                    transform: translateX(-20px);
                    transition: opacity 0.3s, transform 0.3s;
                }
                
                .log-error { border-left-color: var(--error); }
                .log-warning { border-left-color: var(--warning); }
                .log-info { border-left-color: var(--info); }
                .log-debug { border-left-color: var(--debug); }
                .log-notice { border-left-color: var(--success); }
                
                .log-level {
                    font-size: 12px;
                    font-weight: bold;
                    padding: 2px 8px;
                    border-radius: 4px;
                    text-transform: uppercase;
                }
                
                .log-error .log-level { background: rgba(244, 67, 54, 0.2); color: var(--error); }
                .log-warning .log-level { background: rgba(255, 152, 0, 0.2); color: var(--warning); }
                .log-info .log-level { background: rgba(33, 150, 243, 0.2); color: var(--info); }
                .log-debug .log-level { background: rgba(156, 39, 176, 0.2); color: var(--debug); }
                .log-notice .log-level { background: rgba(76, 175, 80, 0.2); color: var(--success); }
                
                .log-time { font-size: 12px; color: var(--text-secondary); }
                
                .toggle-details {
                    background: transparent;
                    border: 1px solid var(--border);
                    color: var(--text-secondary);
                    padding: 4px 8px;
                    border-radius: 3px;
                    cursor: pointer;
                    font-size: 12px;
                    transition: all 0.2s;
                }
                
                .toggle-details:hover {
                    background: var(--border);
                    color: var(--text-primary);
                }
                
                .log-message {
                    font-family: monospace;
                    white-space: pre-wrap;
                    margin-bottom: 12px;
                    color: var(--text-primary);
                }
                
                .log-details pre {
                    background: rgba(0,0,0,0.3);
                    padding: 12px;
                    border-radius: 4px;
                    overflow-x: auto;
                    font-size: 12px;
                    line-height: 1.4;
                }
                
                .no-logs {
                    text-align: center;
                    padding: 40px 20px;
                    color: var(--text-secondary);
                    font-size: 18px;
                }
                
                .level-count {
                    background: var(--border);
                    color: var(--text-primary);
                    font-size: 10px;
                    padding: 0 4px;
                    border-radius: 3px;
                    margin-left: 4px;
                }
                
                @media (max-width: 768px) {
                    body { padding: 12px; }
                    header { flex-direction: column; align-items: stretch; }
                    .controls { width: 100%; }
                    input[type="text"] { min-width: auto; width: 100%; }
                }
            </style>
        </head>
        <body>
            <div class="container">
                <header>
                    <h1>CodeX Log Viewer</h1>
                    <div class="controls">
                        <div class="filter-group">
                            <label><input type="checkbox" id="filter-error" checked> Error <span class="level-count">' . $levelStats['error'] . '</span></label>
                            <label><input type="checkbox" id="filter-warning" checked> Warning <span class="level-count">' . $levelStats['warning'] . '</span></label>
                            <label><input type="checkbox" id="filter-notice" checked> Notice <span class="level-count">' . $levelStats['notice'] . '</span></label>
                            <label><input type="checkbox" id="filter-info" checked> Info <span class="level-count">' . $levelStats['info'] . '</span></label>
                            <label><input type="checkbox" id="filter-debug"> Debug <span class="level-count">' . $levelStats['debug'] . '</span></label>
                        </div>
                        <div class="search-group">
                            <input type="text" id="search-input" placeholder="Поиск по логам...">
                        </div>
                        <select id="date-select">
                            ' . $this->renderDateOptions($availableDates, $selectedDate) . '
                        </select>
                    </div>
                </header>
                
                <div class="logs-container" id="logs-container">
                    ' . $entriesHtml . '
                </div>
            </div>
            
            <script>
                // === Фильтрация по уровням ===
                function applyFilters() {
                    const filters = {
                        error: document.getElementById("filter-error").checked,
                        warning: document.getElementById("filter-warning").checked,
                        notice: document.getElementById("filter-notice").checked,
                        info: document.getElementById("filter-info").checked,
                        debug: document.getElementById("filter-debug").checked
                    };
                    
                    const searchTerm = document.getElementById("search-input").value.toLowerCase();
                    const entries = document.querySelectorAll(".log-entry");
                    
                    entries.forEach(entry => {
                        const level = entry.dataset.level;
                        const message = entry.querySelector(".log-message").dataset.searchText.toLowerCase();
                        const matchesLevel = filters[level];
                        const matchesSearch = !searchTerm || message.includes(searchTerm);
                        
                        if (matchesLevel && matchesSearch) {
                            entry.classList.remove("hidden");
                        } else {
                            entry.classList.add("hidden");
                        }
                    });
                }
                
                // === Обработчики событий ===
                document.querySelectorAll("input[type=\'checkbox\']").forEach(checkbox => {
                    checkbox.addEventListener("change", applyFilters);
                });
                
                document.getElementById("search-input").addEventListener("input", applyFilters);
                
                document.getElementById("date-select").addEventListener("change", function() {
                    const date = this.value;
                    if (date) {
                        window.location.href = "/_debug/logs/" + date;
                    }
                });
                
                // === Переключение подробностей ===
                document.querySelectorAll(".toggle-details").forEach(button => {
                    button.addEventListener("click", function() {
                        const details = this.closest(".log-entry").querySelector(".log-details");
                        const isVisible = details.style.display === "block";
                        
                        details.style.display = isVisible ? "none" : "block";
                        this.textContent = isVisible ? "Подробности" : "Скрыть";
                    });
                });
                
                // === Автоматическая прокрутка к новым записям ===
                const logsContainer = document.getElementById("logs-container");
                let isAtBottom = true;
                
                logsContainer.addEventListener("scroll", () => {
                    isAtBottom = logsContainer.scrollHeight - logsContainer.scrollTop <= logsContainer.clientHeight + 5;
                });
                
                setTimeout(() => {
                    if (isAtBottom) {
                        logsContainer.scrollTop = logsContainer.scrollHeight;
                    }
                }, 100);
            </script>
        </body>
        </html>';
    }
}