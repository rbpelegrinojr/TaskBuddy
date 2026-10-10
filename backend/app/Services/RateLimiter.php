<?php
declare(strict_types=1);

namespace TaskBuddy\Services;

use TaskBuddy\Support\ApiException;
use TaskBuddy\Support\Clock;
use TaskBuddy\Support\Database;

/** Fixed-window rate limiter stored in MySQL (works on shared hosting without extra services). */
final class RateLimiter
{
    public static function hit(string $key, int $max, int $windowSeconds): void
    {
        $now = Clock::now()->getTimestamp();
        $windowStart = $now - ($now % $windowSeconds);
        $bucket = hash('sha256', $key);
        $start = gmdate('Y-m-d H:i:s', $windowStart);
        Database::run(
            'INSERT INTO rate_limits (bucket_key, window_start, hits) VALUES (?, ?, 1)
             ON DUPLICATE KEY UPDATE hits = hits + 1',
            [$bucket, $start]
        );
        $hits = (int)Database::value('SELECT hits FROM rate_limits WHERE bucket_key = ? AND window_start = ?', [$bucket, $start]);
        if ($hits > $max) {
            $retry = $windowStart + $windowSeconds - $now;
            throw new ApiException(429, 'RATE_LIMITED', 'Too many attempts. Please try again later.', ['retry_after_seconds' => $retry]);
        }
        if (random_int(1, 50) === 1) {
            Database::run('DELETE FROM rate_limits WHERE window_start < ?', [gmdate('Y-m-d H:i:s', $now - 86400)]);
        }
    }

    public static function reset(string $key): void
    {
        Database::run('DELETE FROM rate_limits WHERE bucket_key = ?', [hash('sha256', $key)]);
    }
}
