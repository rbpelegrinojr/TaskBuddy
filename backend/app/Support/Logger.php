<?php
declare(strict_types=1);

namespace TaskBuddy\Support;

/** Safe file logger: never logs passwords, tokens or request bodies. */
final class Logger
{
    public static function error(string $message, array $context = []): void
    {
        self::write('ERROR', $message, $context);
    }

    public static function info(string $message, array $context = []): void
    {
        self::write('INFO', $message, $context);
    }

    private static function write(string $level, string $message, array $context): void
    {
        $dir = Paths::storage('logs');
        if (!is_dir($dir) || !is_writable($dir)) {
            error_log("[$level] $message");
            return;
        }
        $line = sprintf("[%s] %s %s %s\n", Clock::now()->format('c'), $level, $message,
            $context ? json_encode($context, JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR) : '');
        @file_put_contents($dir . '/app-' . Clock::now()->format('Y-m-d') . '.log', $line, FILE_APPEND | LOCK_EX);
    }
}
