<?php
declare(strict_types=1);

namespace TaskBuddy\Support;

final class Paths
{
    public static function backend(): string
    {
        return dirname(__DIR__, 2);
    }

    /** Storage root (outside any public web root). Override with STORAGE_PATH. */
    public static function storage(string $sub = ''): string
    {
        $base = Env::get('STORAGE_PATH');
        if ($base === null || $base === '') {
            $base = self::backend() . '/storage';
        } elseif ($base[0] !== '/') {
            $base = self::backend() . '/public/' . $base;
        }
        $base = rtrim($base, '/');
        return $sub === '' ? $base : $base . '/' . ltrim($sub, '/');
    }
}
