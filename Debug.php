<?php

declare(strict_types=1);

namespace CodeX;

use ErrorException;
use JsonException;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;

class Debug
{
    public const string CLI_COLOR_RESET = "\033[0m";
    public const string CLI_COLOR_RED = "\033[0;31m";
    public const string CLI_COLOR_GREEN = "\033[0;32m";
    public const string CLI_COLOR_YELLOW = "\033[0;33m";
    public const string CLI_COLOR_BLUE = "\033[0;34m";
    public const string CLI_COLOR_PURPLE = "\033[0;35m";
    public const string CLI_COLOR_CYAN = "\033[0;36m";
    public const string CLI_COLOR_WHITE = "\033[0;37m";
    public const string CLI_COLOR_GRAY = "\033[1;30m";

    public function __construct(private readonly bool $enabled = false, private ?string $logPath = null, private ?Logger $logger = null)
    {
        if ($this->logPath !== null && is_dir($this->logPath)) {
            $this->logPath = rtrim($this->logPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'debug.log';
        }
    }

    /**
     * @throws JsonException
     */
    public function dumpAndDie(mixed $var, string $label = ''): never
    {
        $this->dump($var, $label);
        exit(PHP_SAPI === 'cli' ? 1 : 0);
    }

    /**
     * @throws JsonException
     */
    public function dump(mixed $var, string $label = ''): void
    {
        if (!$this->enabled) {
            // Если отладка выключена — логируем (если есть куда)
            $this->log($var, $label);
            return;
        }

        $output = $this->formatDump($var, $label);

        if (PHP_SAPI === 'cli') {
            echo $output . PHP_EOL;
        } else {
            echo "<pre style='color:#000000; background:#f4f4f4; padding:10px; border-left:4px solid #4CAF50; font-family:monospace; white-space:pre-wrap;'>";
            echo $output;
            echo "</pre>";
        }
        exit();
    }

    /**
     * Логирует дамп: либо через PSR-логгер, либо в файл.
     *
     * @throws JsonException
     */
    public function log(mixed $var, string $label = ''): void
    {
        $message = sprintf("Debug dump: %s\n%s", $label ?: 'No label', $this->formatForLog($var));

        // Если передан PSR-логгер — используем его
        if ($this->logger instanceof LoggerInterface) {
            $this->logger->debug($message);
            return;
        }

        // Иначе — пишем в файл (если указан путь)
        if ($this->logPath === null) {
            return;
        }

        $logDir = dirname($this->logPath);
        if (!is_dir($logDir) && !mkdir($logDir, 0755, true) && !is_dir($logDir)) {
            throw new RuntimeException(sprintf('Каталог «%s» не был создан', $logDir));
        }

        $fullMessage = sprintf("[%s] DEBUG: %s\n---\n", date('Y-m-d H:i:s'), $message);
        file_put_contents($this->logPath, $fullMessage, FILE_APPEND | LOCK_EX);
    }

    /**
     * @throws JsonException
     */
    private function formatForLog(mixed $var): string
    {
        if (is_string($var)) {
            return "string(" . strlen($var) . ") \"$var\"";
        }

        if (is_array($var)) {
            return "array(" . count($var) . ") " . json_encode($var, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        }

        if (is_object($var)) {
            return "object(" . get_class($var) . ") " . json_encode($var, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        }

        if (is_resource($var)) {
            return "resource(" . get_resource_type($var) . ")";
        }

        return print_r($var, true);
    }

    private function formatDump(mixed $var, string $label): string
    {
        ob_start();
        var_dump($var);
        $dump = ob_get_clean();

        $dump = preg_replace("/\]\=\>\n(\s+)/m", "] => ", $dump);

        if (PHP_SAPI === 'cli') {
            $labelPart = $label ? self::CLI_COLOR_PURPLE . "📌 " . $label . self::CLI_COLOR_RESET . "\n" : '';
            return $labelPart . $this->colorizeVarDump($dump);
        }

        $labelPart = $label ? "<strong style='color:#333;'>" . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . "</strong>\n" : '';
        return $labelPart . htmlspecialchars($dump, ENT_QUOTES, 'UTF-8');
    }

    private function colorizeVarDump(string $dump): string
    {
        $dump = preg_replace('/(string\([^)]+\)) "(.*?)"/', self::CLI_COLOR_GREEN . '$1 "$2"' . self::CLI_COLOR_RESET, $dump);
        $dump = preg_replace('/(int\()(\d+)(\))/', self::CLI_COLOR_CYAN . '$1$2$3' . self::CLI_COLOR_RESET, $dump);
        $dump = preg_replace('/(float\()([\d.]+)(\))/', self::CLI_COLOR_CYAN . '$1$2$3' . self::CLI_COLOR_RESET, $dump);
        $dump = preg_replace('/(bool\()(true|false)(\))/', self::CLI_COLOR_BLUE . '$1$2$3' . self::CLI_COLOR_RESET, $dump);
        $dump = preg_replace('/(NULL)/', self::CLI_COLOR_GRAY . '$1' . self::CLI_COLOR_RESET, $dump);
        $dump = preg_replace('/(array\([^)]+\))/', self::CLI_COLOR_YELLOW . '$1' . self::CLI_COLOR_RESET, $dump);
        return preg_replace('/(object\([^)]+\))/', self::CLI_COLOR_PURPLE . '$1' . self::CLI_COLOR_RESET, $dump);
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Обработка исключения в стиле Whoops
     */
    public function handleException(Throwable $e): void
    {
        // Всегда логируем ошибку (даже в production)
        $this->logException($e);

        if ($this->enabled) {
            // В debug-режиме показываем красивую страницу
            if (PHP_SAPI === 'cli') {
                $this->renderCliException($e);
            } else {
                $this->renderHtmlException($e);
            }
        } else {
            // В production — стандартная ошибка 500
            http_response_code(500);
            echo '<h1>Серверная ошибка</h1>';
        }
    }

    /**
     * CLI-вывод (цветной)
     */
    private function renderCliException(Throwable $e): void
    {
        echo self::CLI_COLOR_RED . "FATAL ERROR\n" . self::CLI_COLOR_RESET;
        echo self::CLI_COLOR_WHITE . "Message: " . self::CLI_COLOR_RESET . $e->getMessage() . "\n";
        echo self::CLI_COLOR_WHITE . "File: " . self::CLI_COLOR_RESET . $e->getFile() . "\n";
        echo self::CLI_COLOR_WHITE . "Line: " . self::CLI_COLOR_RESET . $e->getLine() . "\n\n";

        $this->renderCliTrace($e);
    }

    private function renderCliTrace(Throwable $e): void
    {
        $trace = $e->getTrace();
        foreach ($trace as $i => $frame) {
            $file = $frame['file'] ?? '[internal]';
            $line = $frame['line'] ?? 0;
            $class = $frame['class'] ?? '';
            $type = $frame['type'] ?? '';
            $function = $frame['function'] ?? '';

            echo sprintf("#%d %s%s%s() called at [%s:%d]\n", $i, $class, $type, $function, $file, $line);
        }
    }

    /**
     * HTML-вывод (Whoops-стиль)
     */
    private function renderHtmlException(Throwable $e): void
    {
        $title = htmlspecialchars(get_class($e), ENT_QUOTES, 'UTF-8');
        $message = htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
        $file = htmlspecialchars($e->getFile(), ENT_QUOTES, 'UTF-8');
        $line = $e->getLine();

        // Контекст кода (5 строк до и после)
        $codeContext = $this->getCodeContext($e->getFile(), $e->getLine());

        $html = '<!DOCTYPE html>
        <html lang="ru">
        <head>
            <meta charset="utf-8">
            <title>' . $title . '</title>
            <style>
                body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", monospace; background: #1e1e1e; color: #d4d4d4; margin: 0; padding: 20px; }
                .error-header { background: #611f1f; padding: 20px; border-radius: 6px; margin-bottom: 20px; }
                .error-title { font-size: 24px; margin: 0 0 10px 0; color: #ff6b6b; }
                .error-message { font-size: 16px; margin: 0; color: #f8f8f2; }
                .error-location { margin-top: 15px; font-size: 14px; color: #a9a9a9; }
                .code-block { background: #2d2d2d; border-radius: 6px; overflow: hidden; margin-bottom: 20px; }
                .code-line { display: flex; }
                .line-number { width: 50px; text-align: right; padding: 0 10px; color: #6a6a6a; user-select: none; }
                .line-code { padding: 0 15px; white-space: pre; }
                .line-highlight { background: #442a2a; }
                .tabs { display: flex; border-bottom: 2px solid #3a3a3a; margin-bottom: 20px; }
                .tab { padding: 10px 20px; cursor: pointer; background: #2d2d2d; border: 1px solid #3a3a3a; border-bottom: none; border-radius: 6px 6px 0 0; }
                .tab.active { background: #1e1e1e; border-bottom: 2px solid #ff6b6b; }
                .tab-content { display: none; }
                .tab-content.active { display: block; }
                .data-table { width: 100%; border-collapse: collapse; }
                .data-table th, .data-table td { padding: 10px; text-align: left; border-bottom: 1px solid #3a3a3a; }
                .data-table th { background: #2d2d2d; }
                pre { margin: 0; }
            </style>
        </head>
        <body>
            <div class="error-header">
                <h1 class="error-title">' . $title . '</h1>
                <p class="error-message">' . $message . '</p>
                <div class="error-location">in <strong>' . $file . '</strong> on line <strong>' . $line . '</strong></div>
            </div>

            <div class="code-block">
                ' . $codeContext . '
            </div>

            <div class="tabs">
                <div class="tab active" data-tab="stack">Stack Trace</div>
                <div class="tab" data-tab="request">Request</div>
                <div class="tab" data-tab="server">Server</div>
            </div>

            <div class="tab-content active" id="tab-stack">
                <pre>' . htmlspecialchars($e->getTraceAsString(), ENT_QUOTES, 'UTF-8') . '</pre>
            </div>

            <div class="tab-content" id="tab-request">
                <table class="data-table">
                    <tr><th>Method</th><td>' . ($_SERVER['REQUEST_METHOD'] ?? 'N/A') . '</td></tr>
                    <tr><th>URI</th><td>' . ($_SERVER['REQUEST_URI'] ?? 'N/A') . '</td></tr>
                    <tr><th>GET</th><td><pre>' . htmlspecialchars(print_r($_GET, true), ENT_QUOTES, 'UTF-8') . '</pre></td></tr>
                    <tr><th>POST</th><td><pre>' . htmlspecialchars(print_r($_POST, true), ENT_QUOTES, 'UTF-8') . '</pre></td></tr>
                    <tr><th>COOKIES</th><td><pre>' . htmlspecialchars(print_r($_COOKIE, true), ENT_QUOTES, 'UTF-8') . '</pre></td></tr>
                </table>
            </div>

            <div class="tab-content" id="tab-server">
                <table class="data-table">
                    <tr><th>PHP Version</th><td>' . PHP_VERSION . '</td></tr>
                    <tr><th>Server</th><td>' . ($_SERVER['SERVER_SOFTWARE'] ?? 'N/A') . '</td></tr>
                    <tr><th>Document Root</th><td>' . ($_SERVER['DOCUMENT_ROOT'] ?? 'N/A') . '</td></tr>
                    <tr><th>Remote Addr</th><td>' . ($_SERVER['REMOTE_ADDR'] ?? 'N/A') . '</td></tr>
                </table>
            </div>

            <script>
                document.querySelectorAll(".tab").forEach(tab => {
                    tab.addEventListener("click", () => {
                        document.querySelectorAll(".tab").forEach(t => t.classList.remove("active"));
                        document.querySelectorAll(".tab-content").forEach(c => c.classList.remove("active"));
                        tab.classList.add("active");
                        const target = tab.getAttribute("data-tab");
                        document.getElementById("tab-" + target).classList.add("active");
                    });
                });
            </script>
        </body>
        </html>';

        echo $html;
    }

    /**
     * Получает контекст кода с подсветкой ошибки
     */
    private function getCodeContext(string $file, int $line): string
    {
        if (!is_file($file)) {
            return '<div class="code-line"><div class="line-code">File not found</div></div>';
        }

        $source = file($file);
        $start = max(0, $line - 6);
        $end = min(count($source), $line + 4);

        $output = '';
        for ($i = $start; $i < $end; $i++) {
            $currentLine = $i + 1;
            $isHighlight = ($currentLine === $line);
            $lineClass = $isHighlight ? 'line-highlight' : '';
            $numberClass = $isHighlight ? 'line-number line-highlight' : 'line-number';

            $code = htmlspecialchars($source[$i], ENT_QUOTES, 'UTF-8');
            $output .= '
                <div class="code-line ' . $lineClass . '">
                    <div class="' . $numberClass . '">' . $currentLine . '</div>
                    <div class="line-code">' . rtrim($code) . '</div>
                </div>';
        }

        return $output;
    }

    /**
     * @throws ErrorException
     */
    public function handlerError(int $severity, string $message, string $file, int $line): void
    {
        if (error_reporting() & $severity) {
            throw new ErrorException($message, 0, $severity, $file, $line);
        }
    }
    public function registerHandlers(): void
    {
        if ($this->enabled) {
            restore_error_handler();
            restore_exception_handler();
            set_error_handler([$this, 'handlerError']);
            set_exception_handler([$this, 'handleException']);
        }
    }
    /**
     * Логирует ошибку в файл с ротацией по дате
     */
    private function logException(Throwable $e): void
    {
        if ($this->logPath === null) {
            return;
        }

        // Убеждаемся, что это директория (а не файл)
        $logDir = is_file($this->logPath) ? dirname($this->logPath) : $this->logPath;

        if (!is_dir($logDir) && !mkdir($logDir, 0755, true) && !is_dir($logDir)) {
            return; // Не можем создать директорию — пропускаем логирование
        }

        $date = date('Y-m-d');
        $logFile = $logDir . DIRECTORY_SEPARATOR . 'error-' . $date . '.log';

        $message = sprintf(
            "[%s] %s: %s in %s:%d\nStack trace:\n%s\n---\n",
            date('Y-m-d H:i:s'),
            get_class($e),
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
            $e->getTraceAsString()
        );

        file_put_contents($logFile, $message, FILE_APPEND | LOCK_EX);
    }
}