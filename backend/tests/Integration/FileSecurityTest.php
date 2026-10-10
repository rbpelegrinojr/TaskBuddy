<?php
declare(strict_types=1);

namespace TaskBuddy\Tests\Integration;

use TaskBuddy\Support\Database;

final class FileSecurityTest extends ApiTestCase
{
    /** @return array{0:array,1:array,2:int} */
    private function inProgress(): array
    {
        $cat = $this->categoryId();
        $skills = $this->skillIds($cat, 2);
        $client = $this->register('client');
        $prov = $this->provider($cat, $skills);
        $rid = (int)$this->ok($this->api('POST', '/api/requests', $this->requestPayload($cat, $skills), $client['token']), 201)['request']['id'];
        $inv = $this->ok($this->api('POST', "/api/requests/$rid/invite", ['provider_id' => $prov['id']], $client['token']), 201);
        $this->ok($this->api('POST', "/api/invitations/{$inv['id']}/accept", [], $prov['token']));
        return [$client, $prov, $rid];
    }

    /** @return array<string,array{0:string,1:string,2:string}> */
    public function rejectedUploads(): array
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
        return [
            'php script' => ['shell.php', '<?php system($_GET["c"]);', 'application/x-php'],
            'php disguised as png' => ['shell.png', '<?php system($_GET["c"]);', 'image/png'],
            'double extension' => ['image.png.php', $png, 'image/png'],
            'exe' => ['tool.exe', "MZ\x90\x00", 'application/octet-stream'],
            'html' => ['page.html', '<script>alert(1)</script>', 'text/html'],
            'svg' => ['pic.svg', '<svg onload="alert(1)"/>', 'image/svg+xml'],
            'htaccess' => ['.htaccess', 'AddType application/x-httpd-php .png', 'text/plain'],
            'no extension' => ['README', 'hello', 'text/plain'],
            'text posing as pdf' => ['report.pdf', 'just some text', 'application/pdf'],
            'png content as pdf' => ['report.pdf', $png, 'application/pdf'],
            'empty file' => ['empty.txt', '', 'text/plain'],
        ];
    }

    /** @dataProvider rejectedUploads */
    public function testDangerousOrMismatchedUploadsAreRejected(string $name, string $content, string $mime): void
    {
        [, $prov, $rid] = $this->inProgress();
        $r = $this->api('POST', "/api/tasks/$rid/submit", ['note' => 'x'], $prov['token'], [], ['files' => $this->fakeUpload($name, $content, $mime)]);
        $this->assertContains($r[0], [415, 422], json_encode($r[1]));
        $this->assertFalse($r[1]['success']);
        $this->assertSame(0, (int)Database::value('SELECT COUNT(*) FROM submission_files'));
        $this->assertSame([], glob($this->storage . '/uploads/*/*') ?: []);
    }

    public function testValidUploadIsStoredOutsideWebRootWithRandomNameAndServedSafely(): void
    {
        [$client, $prov, $rid] = $this->inProgress();
        $sub = $this->ok($this->api('POST', "/api/tasks/$rid/submit", ['note' => 'ok'], $prov['token'], [], ['files' => $this->fakeUpload('../../evil name.png', $this->pngBytes(), 'image/png')]), 201);
        $f = $sub['task']['submissions'][0]['files'][0];
        $row = Database::one('SELECT * FROM submission_files WHERE id = ?', [$f['id']]);
        $this->assertSame(1, preg_match('/^[a-f0-9]{40}$/', basename($row['stored_name'])));
        $this->assertStringNotContainsString('..', $row['stored_name']);
        $this->assertSame('image/png', $row['mime_type']);
        $this->assertSame(64, strlen($row['sha256']));
        $this->assertStringNotContainsString('/', $f['original_name']);
        [$st, $res] = $this->raw('GET', "/api/submission-files/{$f['id']}/download", $client['token']);
        $this->assertSame(200, $st);
        $this->assertSame('nosniff', $res->headers['X-Content-Type-Options']);
        $this->assertStringContainsString('attachment', $res->headers['Content-Disposition']);
        $this->assertSame([], glob($this->storage . '/uploads/*.php') ?: []);
    }

    public function testRequestAttachmentLifecycleAndAccess(): void
    {
        $cat = $this->categoryId();
        $skills = $this->skillIds($cat, 2);
        $client = $this->register('client');
        $other = $this->register('client');
        $rid = (int)$this->ok($this->api('POST', '/api/requests', $this->requestPayload($cat, $skills), $client['token']), 201)['request']['id'];
        $att = $this->ok($this->api('POST', "/api/requests/$rid/attachments", [], $client['token'], [], ['file' => $this->fakeUpload('plan.png', $this->pngBytes(), 'image/png')]), 201);
        [$st] = $this->raw('GET', "/api/attachments/{$att['id']}/download", $client['token']);
        $this->assertSame(200, $st);
        [$st] = $this->raw('GET', "/api/attachments/{$att['id']}/download", $other['token']);
        $this->assertSame(404, $st);
        [$st] = $this->raw('GET', "/api/attachments/{$att['id']}/download", null);
        $this->assertSame(401, $st);
        $bad = $this->api('POST', "/api/requests/$rid/attachments", [], $client['token'], [], ['file' => $this->fakeUpload('x.php', '<?php', 'text/plain')]);
        $this->assertContains($bad[0], [415, 422]);
    }

    public function testOversizedFileIsRejected(): void
    {
        [, $prov, $rid] = $this->inProgress();
        \TaskBuddy\Support\Env::set('UPLOAD_MAX_BYTES', '1000');
        try {
            $r = $this->api('POST', "/api/tasks/$rid/submit", ['note' => 'big'], $prov['token'], [], ['files' => $this->fakeUpload('big.txt', str_repeat('a', 5000), 'text/plain')]);
            $this->assertError($r, 413, 'FILE_TOO_LARGE');
        } finally {
            \TaskBuddy\Support\Env::set('UPLOAD_MAX_BYTES', '10485760');
        }
    }
}
