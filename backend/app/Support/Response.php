<?php
declare(strict_types=1);

namespace TaskBuddy\Support;

/** Consistent JSON envelope: {success, data, meta} or {success:false, error:{code,message,details}}. */
final class Response
{
    /** @param array<string,mixed> $headers */
    public function __construct(
        public readonly int $status,
        public readonly mixed $payload,
        public readonly array $headers = [],
        public readonly ?string $rawBody = null,
        public readonly ?string $filePath = null
    ) {
    }

    /** @param array<string,mixed> $meta */
    public static function ok(mixed $data, array $meta = [], int $status = 200): self
    {
        $payload = ['success' => true, 'data' => $data];
        if ($meta) {
            $payload['meta'] = $meta;
        }
        return new self($status, $payload);
    }

    public static function created(mixed $data): self
    {
        return self::ok($data, [], 201);
    }

    public static function error(int $status, string $code, string $message, array $details = []): self
    {
        $err = ['code' => $code, 'message' => $message];
        if ($details) {
            $err['details'] = $details;
        }
        return new self($status, ['success' => false, 'error' => $err]);
    }

    /** Stream a file from protected storage. */
    public static function file(string $path, string $mime, string $downloadName): self
    {
        $safe = preg_replace('/[^A-Za-z0-9._ -]/', '_', $downloadName) ?: 'download';
        return new self(200, null, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'attachment; filename="' . $safe . '"',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'Cache-Control' => 'private, no-store',
        ], null, $path);
    }

    public function send(): void
    {
        http_response_code($this->status);
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-store');
        foreach ($this->headers as $k => $v) {
            header("$k: $v");
        }
        if ($this->filePath !== null) {
            header('Content-Length: ' . (string)filesize($this->filePath));
            readfile($this->filePath);
            return;
        }
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($this->payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
    }
}
