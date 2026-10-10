<?php
declare(strict_types=1);

namespace TaskBuddy\Admin\Support;

final class Csv
{
    /** Neutralises spreadsheet formula injection (cells that start with = + - @ tab or CR). */
    public static function cell(mixed $v): string
    {
        $s = $v === null ? '' : (string)$v;
        if ($s !== '' && strpbrk($s[0], "=+-@\t\r") !== false) {
            $s = "'" . $s;
        }
        return '"' . str_replace('"', '""', $s) . '"';
    }

    /** @param string[] $headers @param array<int,array<int|string,mixed>> $rows */
    public static function build(array $headers, array $rows): string
    {
        $out = "\xEF\xBB\xBF" . implode(',', array_map([self::class, 'cell'], $headers)) . "\r\n";
        foreach ($rows as $r) {
            $out .= implode(',', array_map([self::class, 'cell'], array_values($r))) . "\r\n";
        }
        return $out;
    }
}
