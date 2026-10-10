<?php
declare(strict_types=1);

namespace TaskBuddy\Services;

use TaskBuddy\Support\ApiException;
use TaskBuddy\Support\Database;

final class CatalogService
{
    /** @return array<int,array<string,mixed>> */
    public function categories(): array
    {
        return array_map(static fn($r) => ['id' => (int)$r['id'], 'name' => $r['name'], 'description' => $r['description']],
            Database::all('SELECT id, name, description FROM service_categories WHERE is_active = 1 ORDER BY name'));
    }

    /** @return array<int,array<string,mixed>> */
    public function skills(?int $categoryId = null): array
    {
        if ($categoryId !== null) {
            if (Database::value('SELECT id FROM service_categories WHERE id = ? AND is_active = 1', [$categoryId]) === null) {
                throw ApiException::notFound('Category');
            }
            $rows = Database::all('SELECT s.id, s.name FROM skills s JOIN category_skills cs ON cs.skill_id = s.id WHERE cs.category_id = ? AND s.is_active = 1 ORDER BY s.name', [$categoryId]);
        } else {
            $rows = Database::all('SELECT id, name FROM skills WHERE is_active = 1 ORDER BY name');
        }
        return array_map(static fn($r) => ['id' => (int)$r['id'], 'name' => $r['name']], $rows);
    }
}
