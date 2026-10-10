<?php
declare(strict_types=1);

namespace TaskBuddy\Admin\Controllers;

use TaskBuddy\Admin\Http\Response;
use TaskBuddy\Services\AuditLogger;
use TaskBuddy\Support\ApiException;
use TaskBuddy\Support\Database;

final class CatalogController extends Controller
{
    public function index(): Response
    {
        $cats = Database::all('SELECT c.id, c.name, c.description, c.is_active, (SELECT COUNT(*) FROM service_requests r WHERE r.category_id = c.id) AS requests, (SELECT COUNT(*) FROM provider_categories pc WHERE pc.category_id = c.id) AS providers FROM service_categories c ORDER BY c.name');
        $links = [];
        foreach (Database::all('SELECT cs.category_id, s.id, s.name FROM category_skills cs JOIN skills s ON s.id = cs.skill_id ORDER BY s.name') as $r) {
            $links[(int)$r['category_id']][] = $r;
        }
        $skills = Database::all('SELECT s.id, s.name, s.is_active, (SELECT COUNT(*) FROM provider_skills ps WHERE ps.skill_id = s.id) AS providers FROM skills s ORDER BY s.name');
        return $this->view('catalog', 'Categories and skills', ['cats' => $cats, 'links' => $links, 'skills' => $skills]);
    }

    public function createCategory(): Response
    {
        $r = $this->ctx->request;
        [$name, $desc] = [$this->name('name', 100), mb_substr($r->postStr('description'), 0, 255)];
        $id = $this->guardDuplicate(fn() => Database::insert('INSERT INTO service_categories (name, description) VALUES (?,?)', [$name, $desc !== '' ? $desc : null]), 'A category with this name already exists.');
        AuditLogger::log($this->adminId(), 'admin', 'CATEGORY_CREATED', 'service_category', $id, ['name' => $name]);
        return $this->redirect('/catalog', 'Category created.');
    }

    /** @param array<string,string> $p */
    public function updateCategory(array $p): Response
    {
        $id = (int)$p['id'];
        $r = $this->ctx->request;
        $name = $this->name('name', 100);
        $active = $r->postStr('is_active') === '1' ? 1 : 0;
        if (Database::one('SELECT id FROM service_categories WHERE id = ?', [$id]) === null) {
            throw ApiException::notFound('Category');
        }
        $this->guardDuplicate(fn() => Database::run('UPDATE service_categories SET name = ?, description = ?, is_active = ? WHERE id = ?', [$name, mb_substr($r->postStr('description'), 0, 255) ?: null, $active, $id]), 'A category with this name already exists.');
        AuditLogger::log($this->adminId(), 'admin', 'CATEGORY_UPDATED', 'service_category', $id, ['name' => $name, 'is_active' => $active]);
        return $this->redirect('/catalog', 'Category saved.');
    }

    public function createSkill(): Response
    {
        $name = $this->name('name', 100);
        $id = $this->guardDuplicate(fn() => Database::insert('INSERT INTO skills (name) VALUES (?)', [$name]), 'A skill with this name already exists.');
        $cat = (int)$this->ctx->request->postStr('category_id');
        if ($cat > 0 && Database::one('SELECT id FROM service_categories WHERE id = ?', [$cat]) !== null) {
            Database::run('INSERT IGNORE INTO category_skills (category_id, skill_id) VALUES (?,?)', [$cat, $id]);
        }
        AuditLogger::log($this->adminId(), 'admin', 'SKILL_CREATED', 'skill', $id, ['name' => $name]);
        return $this->redirect('/catalog', 'Skill created.');
    }

    /** @param array<string,string> $p */
    public function updateSkill(array $p): Response
    {
        $id = (int)$p['id'];
        $name = $this->name('name', 100);
        $active = $this->ctx->request->postStr('is_active') === '1' ? 1 : 0;
        if (Database::one('SELECT id FROM skills WHERE id = ?', [$id]) === null) {
            throw ApiException::notFound('Skill');
        }
        $this->guardDuplicate(fn() => Database::run('UPDATE skills SET name = ?, is_active = ? WHERE id = ?', [$name, $active, $id]), 'A skill with this name already exists.');
        AuditLogger::log($this->adminId(), 'admin', 'SKILL_UPDATED', 'skill', $id, ['name' => $name, 'is_active' => $active]);
        return $this->redirect('/catalog', 'Skill saved.');
    }

    public function link(): Response
    {
        $r = $this->ctx->request;
        $cat = (int)$r->postStr('category_id');
        $skill = (int)$r->postStr('skill_id');
        $op = $r->postStr('op');
        if (!in_array($op, ['add', 'remove'], true) || Database::one('SELECT id FROM service_categories WHERE id = ?', [$cat]) === null || Database::one('SELECT id FROM skills WHERE id = ?', [$skill]) === null) {
            throw ApiException::validation(['link' => 'Choose an existing category and skill.']);
        }
        if ($op === 'add') {
            Database::run('INSERT IGNORE INTO category_skills (category_id, skill_id) VALUES (?,?)', [$cat, $skill]);
        } else {
            Database::run('DELETE FROM category_skills WHERE category_id = ? AND skill_id = ?', [$cat, $skill]);
        }
        AuditLogger::log($this->adminId(), 'admin', $op === 'add' ? 'CATEGORY_SKILL_LINKED' : 'CATEGORY_SKILL_UNLINKED', 'service_category', $cat, ['skill_id' => $skill]);
        return $this->redirect('/catalog', 'Category skills updated.');
    }

    private function name(string $key, int $max): string
    {
        $v = $this->ctx->request->postStr($key);
        if (mb_strlen($v) < 2 || mb_strlen($v) > $max) {
            throw ApiException::validation([$key => "Length must be between 2 and $max characters."]);
        }
        return $v;
    }

    private function guardDuplicate(callable $fn, string $message): int
    {
        try {
            return (int)$fn();
        } catch (\PDOException $e) {
            if ($e->getCode() === '23000') {
                throw ApiException::conflict('DUPLICATE', $message);
            }
            throw $e;
        }
    }
}
