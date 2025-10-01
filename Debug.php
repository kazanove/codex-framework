<?php

declare(strict_types=1);

namespace CodeX;

use JsonException;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use RuntimeException;

class Debug
{
    // ANSI Colors for CLI
    public const string CLI_COLOR_RESET = "\033[0m";
    public const string CLI_COLOR_RED = "\033[0;31m";
    public const string CLI_COLOR_GREEN = "\033[0;32m";
    public const string CLI_COLOR_YELLOW = "\033[0;33m";
    public const string CLI_COLOR_BLUE = "\033[0;34m";
    public const string CLI_COLOR_PURPLE = "\033[0;35m";
    public const string CLI_COLOR_CYAN = "\033[0;36m";
    public const string CLI_COLOR_WHITE = "\033[0;37m";
    public const string CLI_COLOR_GRAY = "\033[1;30m";

    public function __construct(
        private bool $enabled = false,
        private ?string $logPath = null,
        private ?LoggerInterface $logger = null
    ) {
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
            echo "<pre style='background:#f4f4f4; padding:10px; border-left:4px solid #4CAF50; font-family:monospace; white-space:pre-wrap;'>";
            echo $output;
            echo "</pre>";
        }
    }

    /**
     * Логирует дамп: либо через PSR-логгер, либо в файл.
     *
     * @throws JsonException
     */
    public function log(mixed $var, string $label = ''): void
    {
        $message = sprintf(
            "Debug dump: %s\n%s",
            $label ?: 'No label',
            $this->formatForLog($var)
        );

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
}