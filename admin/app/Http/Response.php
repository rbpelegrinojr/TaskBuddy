<?php
declare(strict_types=1);

namespace TaskBuddy\Admin\Http;

final class Response
{
    /** @param array<string,string> $headers */
    public function __construct(public int $status, public string $body, public array $headers = [])
    {
    }

    public static function redirect(string $url, int $status = 303): self
    {
        return new self($status, '', ['Location' => $url]);
    }

    public function withHeader(string $k, string $v): self
    {
        $this->headers[$k] = $v;
        return $this;
    }

    public function send(): void
    {
        header_remove('X-Powered-By');
        http_response_code($this->status);
        foreach ($this->headers as $k => $v) {
            header($k . ': ' . $v);
        }
        echo $this->body;
    }
}
