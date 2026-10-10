<?php
declare(strict_types=1);

namespace TaskBuddy\Support;

use DateTimeImmutable;
use DateTimeZone;

/** All timestamps are UTC. Tests may freeze the clock. */
final class Clock
{
    private static ?DateTimeImmutable $frozen = null;

    public static function now(): DateTimeImmutable
    {
        return self::$frozen ?? new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }

    public static function freeze(?DateTimeImmutable $at): void
    {
        self::$frozen = $at;
    }

    public static function sql(?DateTimeImmutable $d = null): string
    {
        return ($d ?? self::now())->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    public static function iso(?string $sqlUtc): ?string
    {
        if ($sqlUtc === null || $sqlUtc === '') {
            return null;
        }
        return (new DateTimeImmutable($sqlUtc, new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s\Z');
    }
}
