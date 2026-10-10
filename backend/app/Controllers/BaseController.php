<?php
declare(strict_types=1);

namespace TaskBuddy\Controllers;

use TaskBuddy\Support\Request;

abstract class BaseController
{
    /** @return array{0:int,1:int} page, per_page */
    protected function paging(Request $r, int $defaultPer = 20): array
    {
        return [$r->queryInt('page', 1, 1, 100000), $r->queryInt('per_page', $defaultPer, 1, 50)];
    }

    protected function statusFilter(Request $r, array $allowed): ?string
    {
        $s = $r->query['status'] ?? null;
        return is_string($s) && in_array($s, $allowed, true) ? $s : null;
    }

    /**
     * Normalises $_FILES into a flat list of single-file entries (supports file, files, files[]).
     * @return array<int,array<string,mixed>>
     */
    protected function uploadedFiles(Request $r, string $field = 'files'): array
    {
        $f = $r->files[$field] ?? $r->files['file'] ?? null;
        if (!is_array($f) || !isset($f['name'])) {
            return [];
        }
        if (!is_array($f['name'])) {
            return $f['error'] === UPLOAD_ERR_NO_FILE ? [] : [$f];
        }
        $out = [];
        foreach (array_keys($f['name']) as $i) {
            if ($f['error'][$i] === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $out[] = ['name' => $f['name'][$i], 'type' => $f['type'][$i], 'tmp_name' => $f['tmp_name'][$i], 'error' => $f['error'][$i], 'size' => $f['size'][$i]];
        }
        return $out;
    }
}
