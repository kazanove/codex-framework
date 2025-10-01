<?php
declare(strict_types=1);

namespace CodeX;

use DateTimeImmutable;
use JsonException;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use RuntimeException;
use Stringable;

class Logger implements LoggerInterface
{
    private string $name;
    private string $logPath;
    private array $levelMap = [
        LogLevel::EMERGENCY => 700,
        LogLevel::ALERT => 600,
        LogLevel::CRITICAL => 550,
        LogLevel::ERROR => 500,
        LogLevel::WARNING => 400,
        LogLevel::NOTICE => 300,
        LogLevel::INFO => 200,
        LogLevel::DEBUG => 100,
    ];

    private int $minLevel;

    public function __construct(string $name, string $logPath, string $minLevel = LogLevel::DEBUG)
    {
        $this->name = $name;
        $this->logPath = rtrim($logPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $this->minLevel = $this->levelMap[$minLevel] ?? 100;

        if (!is_dir($this->logPath) && !mkdir($concurrentDirectory = $this->logPath, 0755, true) && !is_dir($concurrentDirectory)) {
            throw new RuntimeException(sprintf('Каталог «%s» не был создан', $concurrentDirectory));
        }
    }

    /**
     * @throws JsonException
     */
    public function emergency(string|Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::EMERGENCY, $message, $context);
    }

    // Реализация PSR-3 методов

    /**
     * @throws JsonException
     */
    public function log($level, string|Stringable $message, array $context = []): void
    {
        $levelName = strtolower($level);
        $levelValue = $this->levelMap[$levelName] ?? 0;

        if ($levelValue < $this->minLevel) {
            return;
        }

        $this->writeLog($levelName, (string)$message, $context);
    }

    /**
     * @throws JsonException
     */
    private function writeLog(string $level, string $message, array $context): void
    {
        $timestamp = new DateTimeImmutable()->format('Y-m-d H:i:s.u');
        $formattedMessage = $this->interpolate($message, $context);
        $logLine = sprintf("[%s] %s.%s: %s %s\n", $timestamp, $this->name, strtoupper($level), $formattedMessage, !empty($context) ? json_encode($context, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) : '');

        $logFile = $this->getLogFile();
        file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX);
    }

    /**
     * Заменяет {placeholders} в сообщении на значения из $context
     */
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

    private function getLogFile(): string
    {
        $date = new DateTimeImmutable()->format('Y-m-d');
        return $this->logPath . $this->name . '-' . $date . '.log';
    }

    /**
     * @throws JsonException
     */
    public function alert(string|Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::ALERT, $message, $context);
    }

    /**
     * @throws JsonException
     */
    public function critical(string|Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::CRITICAL, $message, $context);
    }

    /**
     * @throws JsonException
     */
    public function error(string|Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::ERROR, $message, $context);
    }

    /**
     * @throws JsonException
     */
    public function warning(string|Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::WARNING, $message, $context);
    }

    /**
     * @throws JsonException
     */
    public function notice(string|Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::NOTICE, $message, $context);
    }

    /**
     * @throws JsonException
     */
    public function info(string|Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::INFO, $message, $context);
    }

    /**
     * @throws JsonException
     */
    public function debug(string|Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::DEBUG, $message, $context);
    }

    /**
     * Возвращает имя канала
     */
    public function getName(): string
    {
        return $this->name;
    }
}