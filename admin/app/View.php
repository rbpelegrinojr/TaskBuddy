<?php
declare(strict_types=1);

namespace TaskBuddy\Admin;

use TaskBuddy\Admin\Support\Format;

/** Plain PHP templates. Every dynamic value must pass through $e() (HTML-escaped). */
final class View
{
    public static function render(Context $ctx, string $template, array $data = [], string $title = ''): string
    {
        $content = self::partial($ctx, $template, $data);
        if ($data['_bare'] ?? false) {
            return $content;
        }
        return self::partial($ctx, 'layout', ['content' => $content, 'title' => $title, 'flash' => $ctx->pullFlash()]);
    }

    private static function partial(Context $ctx, string $template, array $data): string
    {
        $file = __DIR__ . '/Views/' . $template . '.php';
        if (!preg_match('/^[a-z0-9_\/]+$/', $template) || !is_file($file)) {
            throw new \RuntimeException('Unknown view.');
        }
        $e = static fn($v): string => Format::e($v);
        $url = static fn(string $p = '/', array $q = []): string => $ctx->url($p, $q);
        $csrf = static fn(): string => '<input type="hidden" name="_csrf" value="' . Format::e($ctx->csrf()) . '">';
        $badge = static fn(string $s): string => Format::badge($s);
        $dt = static fn(?string $s): string => Format::dt($s);
        $money = static fn($v): string => Format::money($v);
        extract($data, EXTR_SKIP);
        $admin = $ctx->admin;
        ob_start();
        try {
            require $file;
        } catch (\Throwable $t) {
            ob_end_clean();
            throw $t;
        }
        return (string)ob_get_clean();
    }
}
