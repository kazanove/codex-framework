<?php

declare(strict_types=1);

namespace CodeX;

use DateTimeImmutable;
use JsonException;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use RuntimeException;

class Logger implements LoggerInterface
{
    private string $name;
    private string $logPath;
    private array $levelMap = [LogLevel::EMERGENCY => 700, LogLevel::ALERT => 600, LogLevel::CRITICAL => 550, LogLevel::ERROR => 500, LogLevel::WARNING => 400, LogLevel::NOTICE => 300, LogLevel::INFO => 200, LogLevel::DEBUG => 100,];

    private int $minLevel;
    private ?array $buffer = null;

    public function __construct(string $name, string $logPath, string $minLevel = LogLevel::DEBUG, bool $buffered = false)
    {
        $this->name = $name;

        // Если $logPath — это файл, извлекаем директорию
        if (is_file($logPath) || (!is_dir($logPath) && pathinfo($logPath, PATHINFO_EXTENSION) !== '')) {
            $this->logPath = dirname($logPath);
        } else {
            $this->logPath = rtrim($logPath, DIRECTORY_SEPARATOR);
        }

        $this->minLevel = $this->levelMap[$minLevel] ?? 100;

        // Создаём директорию только если не буферизуем
        if (!$buffered && !is_dir($this->logPath) && !mkdir($this->logPath, 0755, true) && !is_dir($this->logPath)) {
            throw new RuntimeException(sprintf('Каталог «%s» не был создан', $this->logPath));
        }
    }

    /**
     * Получить накопленные записи (для Debug Bar)
     */
    public function getRecords(): array
    {
        return $this->buffer ?? [];
    }

    /**
     * Очистить буфер
     */
    public function clear(): void
    {
        if ($this->buffer !== null) {
            $this->buffer = [];
        }
    }

    /**
     * Переключиться в режим записи в файл (остановить буферизацию)
     * @throws JsonException
     */
    public function flushTo(?string $logPath = null): void
    {
        if ($this->buffer === null) {
            return;
        }

        $logPath = $logPath ?? $this->logPath;
        if (!is_dir($logPath) && !mkdir($logPath, 0755, true) && !is_dir($logPath)) {
            throw new RuntimeException(sprintf('Каталог «%s» не был создан', $logPath));
        }

        foreach ($this->buffer as $record) {
            $this->writeLogToFile($record['level'], $record['message'], $record['context'], $logPath);
        }

        $this->buffer = null;
        $this->logPath = $logPath;
    }

    /**
     * Запись одной записи в файл
     */
    private function writeLogToFile(string $level, string $message, array $context, string $logPath): void
    {
        $timestamp = new DateTimeImmutable()->format('Y-m-d H:i:s.u');
        $logLine = sprintf("[%s] %s.%s: %s%s\n", $timestamp, $this->name, strtoupper($level), $message, !empty($context) ? ' ' . json_encode($context, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) : '');

        $logFile = $logPath . $this->name . '-' . new DateTimeImmutable()->format('Y-m-d') . '.log';
        @file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX);
    }

    /**
     * @throws JsonException
     */
    public function emergency($message, array $context = []): void
    {
        $this->log(LogLevel::EMERGENCY, $message, $context);
    }

    /**
     * @throws JsonException
     */
    public function log($level, $message, array $context = []): void
    {
        $levelName = strtolower($level);
        $levelValue = $this->levelMap[$levelName] ?? 0;

        if ($levelValue < $this->minLevel) {
            return;
        }

        $formattedMessage = $this->interpolate((string)$message, $context);

        if ($this->buffer !== null) {
            // Режим буферизации
            $this->buffer[] = ['level' => $levelName, 'message' => $formattedMessage, 'context' => $context, 'time' => new DateTimeImmutable()->format('H:i:s.u'),];
        } else {
            // Режим записи в файл
            $this->writeLogToFile($levelName, $formattedMessage, $context, $this->logPath);
        }
    }

    // Реализация остальных методов PSR-3 (можно оставить как есть)

    private function interpolate(string $message, array $context): string
    {
        $replace = [];
        foreach ($context as $key => $val) {
            if (is_string($val) || is_numeric($val) || (is_object($val) && method_exists($val, '__toString'))) {
                $replace['{' . $key . '}'] = $val;
            }
        }
        return strtr($message, $replace);
    }

    /**
     * @throws JsonException
     */
    public function alert($message, array $context = []): void
    {
        $this->log(LogLevel::ALERT, $message, $context);
    }

    /**
     * @throws JsonException
     */
    public function critical($message, array $context = []): void
    {
        $this->log(LogLevel::CRITICAL, $message, $context);
    }

    /**
     * @throws JsonException
     */
    public function error($message, array $context = []): void
    {
        $this->log(LogLevel::ERROR, $message, $context);
    }

    /**
     * @throws JsonException
     */
    public function warning($message, array $context = []): void
    {
        $this->log(LogLevel::WARNING, $message, $context);
    }

    /**
     * @throws JsonException
     */
    public function notice($message, array $context = []): void
    {
        $this->log(LogLevel::NOTICE, $message, $context);
    }

    /**
     * @throws JsonException
     */
    public function info($message, array $context = []): void
    {
        $this->log(LogLevel::INFO, $message, $context);
    }

    /**
     * @throws JsonException
     */
    public function debug($message, array $context = []): void
    {
        $this->log(LogLevel::DEBUG, $message, $context);
    }

    public function getName(): string
    {
        return $this->name;
    }
    private function getLogFile(): string
    {
        $date = new DateTimeImmutable()->format('Y-m-d');
        return $this->logPath . DIRECTORY_SEPARATOR . $this->name . '-' . $date . '.log';
    }
}