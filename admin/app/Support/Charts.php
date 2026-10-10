<?php
declare(strict_types=1);

namespace TaskBuddy\Admin\Support;

/** Server-rendered SVG charts built from real query results (no external scripts; CSP friendly, attributes only). */
final class Charts
{
    /** @param array<string,int|float> $data label => value */
    public static function bars(array $data, string $title, string $unit = ''): string
    {
        $max = max(1, ...array_map('floatval', array_values($data ?: [0])));
        $rowH = 28;
        $h = max(1, count($data)) * $rowH + 8;
        $svg = '<svg role="img" class="chart" viewBox="0 0 480 ' . $h . '" aria-label="' . self::e($title) . '"><title>' . self::e($title) . '</title>';
        $i = 0;
        foreach ($data as $label => $v) {
            $y = $i * $rowH + 4;
            $w = (int)round(260 * ((float)$v / $max));
            $svg .= '<text x="0" y="' . ($y + 15) . '" class="chart-label">' . self::e((string)$label) . '</text>'
                . '<rect x="150" y="' . $y . '" width="' . max(0, $w) . '" height="18" rx="3" class="chart-bar"><title>' . self::e($label . ': ' . $v . $unit) . '</title></rect>'
                . '<text x="' . (156 + $w) . '" y="' . ($y + 14) . '" class="chart-value">' . self::e((string)$v . $unit) . '</text>';
            $i++;
        }
        return $svg . '</svg>';
    }

    /** @param array<string,int|float> $series ordered label => value (e.g. date => count) */
    public static function columns(array $series, string $title): string
    {
        $n = max(1, count($series));
        $max = max(1, ...array_map('floatval', array_values($series ?: [0])));
        $bw = (int)floor(440 / $n);
        $svg = '<svg role="img" class="chart" viewBox="0 0 480 170" aria-label="' . self::e($title) . '"><title>' . self::e($title) . '</title>';
        $i = 0;
        foreach ($series as $label => $v) {
            $h = (int)round(110 * ((float)$v / $max));
            $x = 20 + $i * $bw;
            $svg .= '<rect x="' . ($x + 2) . '" y="' . (130 - $h) . '" width="' . max(2, $bw - 6) . '" height="' . $h . '" rx="2" class="chart-bar"><title>' . self::e($label . ': ' . $v) . '</title></rect>';
            if ($n <= 14 || $i % 2 === 0) {
                $svg .= '<text x="' . ($x + (int)($bw / 2)) . '" y="148" text-anchor="middle" class="chart-label small">' . self::e(substr((string)$label, 5)) . '</text>';
            }
            $svg .= '<text x="' . ($x + (int)($bw / 2)) . '" y="' . (126 - $h) . '" text-anchor="middle" class="chart-value small">' . ((float)$v > 0 ? self::e((string)$v) : '') . '</text>';
            $i++;
        }
        return $svg . '<line x1="16" y1="130" x2="470" y2="130" class="chart-axis"/></svg>';
    }

    private static function e(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
