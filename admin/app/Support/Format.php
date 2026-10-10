<?php
declare(strict_types=1);

namespace TaskBuddy\Admin\Support;

final class Format
{
    public static function e(mixed $v): string
    {
        return htmlspecialchars((string)($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /** UTC database timestamp -> "2030-01-31 14:05 UTC". */
    public static function dt(?string $sql): string
    {
        return $sql === null || $sql === '' ? '—' : substr($sql, 0, 16) . ' UTC';
    }

    public static function money(mixed $v): string
    {
        return number_format((float)$v, 2, '.', ',');
    }

    public static function badge(string $status): string
    {
        $cls = match ($status) {
            'COMPLETED', 'ACCEPTED', 'active', 'RESOLVED', 'REVIEWED' => 'ok',
            'OPEN', 'PENDING', 'PENDING_ACCEPTANCE', 'IN_PROGRESS', 'SUBMITTED', 'UNDER_REVIEW', 'REVISION_REQUESTED' => 'info',
            'DISPUTED', 'ON_HOLD', 'suspended', 'OVER_BUDGET' => 'warn',
            'CANCELLED', 'EXPIRED', 'DECLINED', 'WITHDRAWN', 'DISMISSED' => 'muted',
            default => 'muted',
        };
        return '<span class="badge ' . $cls . '">' . self::e(str_replace('_', ' ', $status)) . '</span>';
    }
}
