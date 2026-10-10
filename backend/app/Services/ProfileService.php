<?php
declare(strict_types=1);

namespace TaskBuddy\Services;

use TaskBuddy\Support\ApiException;
use TaskBuddy\Support\Database;
use TaskBuddy\Support\Validator;

final class ProfileService
{
    /** @return array<string,mixed> */
    public function get(int $userId): array
    {
        $u = Database::one('SELECT id, email, full_name, role, status, phone, created_at FROM users WHERE id = ?', [$userId]);
        if ($u === null) {
            throw ApiException::notFound('User');
        }
        $out = ['user' => AuthService::formatUser($u), 'profile' => $this->profileRow($userId)];
        if ($u['role'] === 'provider') {
            $out['provider'] = $this->providerDetails($userId);
        }
        return $out;
    }

    /** @param array<string,mixed> $in @return array<string,mixed> */
    public function update(int $userId, string $role, array $in): array
    {
        $v = new Validator($in);
        $name = $v->string('full_name', false, 2, 150);
        $phone = $v->string('phone', false, 5, 30);
        $bio = $v->string('bio', false, 0, 2000);
        $location = $v->string('location', false, 0, 150);
        $headline = $v->string('headline', false, 0, 150);
        $exp = $v->string('experience_summary', false, 0, 3000);
        $years = $v->int('years_experience', false, 0, 80);
        $rate = $v->money('min_rate', false, 0.0);
        $catIds = array_key_exists('category_ids', $in) ? $v->intList('category_ids') : null;
        if ($role !== 'provider' && ($headline !== null || $exp !== null || $years !== null || $rate !== null || $catIds !== null)) {
            $v->error('provider_fields', 'Provider-only fields cannot be set on a client account.');
        }
        if ($catIds !== null) {
            $this->assertIds('service_categories', $catIds, 'category_ids', $v);
        }
        $v->fail();

        Database::transaction(function () use ($userId, $name, $phone, $in, $bio, $location, $headline, $exp, $years, $rate, $catIds): void {
            if ($name !== null || $phone !== null) {
                Database::run('UPDATE users SET full_name = COALESCE(?, full_name), phone = COALESCE(?, phone) WHERE id = ?', [$name, $phone, $userId]);
            }
            $map = ['bio' => $bio, 'location' => $location, 'headline' => $headline, 'experience_summary' => $exp, 'years_experience' => $years, 'min_rate' => $rate];
            foreach ($map as $col => $val) {
                if (array_key_exists($col, $in)) {
                    Database::run("UPDATE user_profiles SET $col = ? WHERE user_id = ?", [$val, $userId]);
                }
            }
            if ($catIds !== null) {
                $this->replaceLinks('provider_categories', 'category_id', $userId, $catIds);
            }
            AuditLogger::log($userId, null, 'PROFILE_UPDATED', 'user', $userId);
        });
        return $this->get($userId);
    }

    /** @param int[] $skillIds @return array<string,mixed> */
    public function setSkills(int $providerId, array $skillIds): array
    {
        $v = new Validator([]);
        $this->assertIds('skills', $skillIds, 'skill_ids', $v);
        $v->fail();
        Database::transaction(function () use ($providerId, $skillIds): void {
            $this->replaceLinks('provider_skills', 'skill_id', $providerId, $skillIds);
            AuditLogger::log($providerId, 'provider', 'PROVIDER_SKILLS_UPDATED', 'user', $providerId, ['count' => count($skillIds)]);
        });
        return $this->providerDetails($providerId);
    }

    /** @param int[] $categoryIds @return array<string,mixed> */
    public function setCategories(int $providerId, array $categoryIds): array
    {
        $v = new Validator([]);
        $this->assertIds('service_categories', $categoryIds, 'category_ids', $v);
        $v->fail();
        Database::transaction(function () use ($providerId, $categoryIds): void {
            $this->replaceLinks('provider_categories', 'category_id', $providerId, $categoryIds);
            AuditLogger::log($providerId, 'provider', 'PROVIDER_CATEGORIES_UPDATED', 'user', $providerId, ['count' => count($categoryIds)]);
        });
        return $this->providerDetails($providerId);
    }

    /** @param mixed $slots @return array<string,mixed> */
    public function setAvailability(int $providerId, mixed $slots): array
    {
        $errors = [];
        $clean = [];
        if (!is_array($slots) || count($slots) > 28) {
            throw ApiException::validation(['slots' => 'Must be a list of at most 28 weekly slots.']);
        }
        foreach ($slots as $i => $s) {
            $d = $s['day_of_week'] ?? null;
            $st = $s['start_time'] ?? null;
            $en = $s['end_time'] ?? null;
            $ok = is_array($s) && is_int($d) && $d >= 0 && $d <= 6 && is_string($st) && is_string($en)
                && preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $st) && preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $en) && $en > $st;
            if (!$ok) {
                $errors["slots.$i"] = 'Requires day_of_week 0-6 and start_time < end_time in HH:MM.';
                continue;
            }
            $clean[] = ['d' => $d, 's' => $st, 'e' => $en];
        }
        usort($clean, static fn($a, $b) => [$a['d'], $a['s']] <=> [$b['d'], $b['s']]);
        for ($i = 1; $i < count($clean); $i++) {
            if ($clean[$i]['d'] === $clean[$i - 1]['d'] && $clean[$i]['s'] < $clean[$i - 1]['e']) {
                $errors['slots'] = 'Slots on the same day must not overlap.';
            }
        }
        if ($errors) {
            throw ApiException::validation($errors);
        }
        Database::transaction(function () use ($providerId, $clean): void {
            Database::run('DELETE FROM provider_availability WHERE provider_id = ?', [$providerId]);
            foreach ($clean as $c) {
                Database::run('INSERT INTO provider_availability (provider_id, day_of_week, start_time, end_time) VALUES (?,?,?,?)', [$providerId, $c['d'], $c['s'] . ':00', $c['e'] . ':00']);
            }
            AuditLogger::log($providerId, 'provider', 'PROVIDER_AVAILABILITY_UPDATED', 'user', $providerId, ['slots' => count($clean)]);
        });
        return $this->providerDetails($providerId);
    }

    /** Public view of a provider for clients. @return array<string,mixed> */
    public function publicProvider(int $providerId): array
    {
        $u = Database::one("SELECT id, full_name, created_at FROM users WHERE id = ? AND role = 'provider' AND status = 'active'", [$providerId]);
        if ($u === null) {
            throw ApiException::notFound('Provider');
        }
        $p = $this->profileRow($providerId);
        $d = $this->providerDetails($providerId);
        return [
            'id' => (int)$u['id'], 'full_name' => $u['full_name'], 'headline' => $p['headline'], 'bio' => $p['bio'],
            'location' => $p['location'], 'experience_summary' => $p['experience_summary'], 'years_experience' => $p['years_experience'],
            'min_rate' => $p['min_rate'], 'categories' => $d['categories'], 'skills' => $d['skills'], 'availability' => $d['availability'],
            'completed_tasks' => (int)Database::value("SELECT COUNT(*) FROM service_assignments a JOIN service_requests r ON r.id = a.request_id WHERE a.provider_id = ? AND r.status = 'COMPLETED'", [$providerId]),
        ];
    }

    /** @return array<string,mixed> */
    public function providerDetails(int $providerId): array
    {
        $p = $this->profileRow($providerId);
        $cats = Database::all('SELECT c.id, c.name FROM provider_categories pc JOIN service_categories c ON c.id = pc.category_id WHERE pc.provider_id = ? ORDER BY c.name', [$providerId]);
        $skills = Database::all('SELECT s.id, s.name FROM provider_skills ps JOIN skills s ON s.id = ps.skill_id WHERE ps.provider_id = ? ORDER BY s.name', [$providerId]);
        $slots = Database::all('SELECT day_of_week, TIME_FORMAT(start_time, "%H:%i") AS start_time, TIME_FORMAT(end_time, "%H:%i") AS end_time FROM provider_availability WHERE provider_id = ? ORDER BY day_of_week, start_time', [$providerId]);
        $missing = self::missingProfileFields($p, count($cats));
        return [
            'categories' => array_map(static fn($r) => ['id' => (int)$r['id'], 'name' => $r['name']], $cats),
            'skills' => array_map(static fn($r) => ['id' => (int)$r['id'], 'name' => $r['name']], $skills),
            'availability' => array_map(static fn($r) => ['day_of_week' => (int)$r['day_of_week'], 'start_time' => $r['start_time'], 'end_time' => $r['end_time']], $slots),
            'profile_complete' => $missing === [],
            'missing_fields' => $missing,
            'has_availability' => $slots !== [],
        ];
    }

    /** Rule 2 of the matching engine: what makes a provider profile "complete". @param array<string,mixed> $profile @return string[] */
    public static function missingProfileFields(array $profile, int $categoryCount): array
    {
        $missing = [];
        if (trim((string)($profile['headline'] ?? '')) === '') {
            $missing[] = 'headline';
        }
        if (trim((string)($profile['experience_summary'] ?? '')) === '') {
            $missing[] = 'experience_summary';
        }
        if ($profile['min_rate'] === null) {
            $missing[] = 'min_rate';
        }
        if ($categoryCount < 1) {
            $missing[] = 'category_ids';
        }
        return $missing;
    }

    /** @return array<string,mixed> */
    private function profileRow(int $userId): array
    {
        $p = Database::one('SELECT bio, location, headline, experience_summary, years_experience, min_rate FROM user_profiles WHERE user_id = ?', [$userId]) ?? [];
        return [
            'bio' => $p['bio'] ?? null, 'location' => $p['location'] ?? null, 'headline' => $p['headline'] ?? null,
            'experience_summary' => $p['experience_summary'] ?? null,
            'years_experience' => isset($p['years_experience']) ? (int)$p['years_experience'] : null,
            'min_rate' => $p['min_rate'] ?? null,
        ];
    }

    /** @param int[] $ids */
    private function assertIds(string $table, array $ids, string $field, Validator $v): void
    {
        if ($ids === []) {
            return;
        }
        $in = implode(',', array_fill(0, count($ids), '?'));
        $found = (int)Database::value("SELECT COUNT(*) FROM $table WHERE is_active = 1 AND id IN ($in)", $ids);
        if ($found !== count($ids)) {
            $v->error($field, 'Contains unknown or inactive identifiers.');
        }
    }

    /** @param int[] $ids */
    private function replaceLinks(string $table, string $col, int $providerId, array $ids): void
    {
        Database::run("DELETE FROM $table WHERE provider_id = ?", [$providerId]);
        foreach ($ids as $id) {
            Database::run("INSERT INTO $table (provider_id, $col) VALUES (?,?)", [$providerId, $id]);
        }
    }
}
