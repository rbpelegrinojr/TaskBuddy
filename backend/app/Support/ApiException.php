<?php
declare(strict_types=1);

namespace TaskBuddy\Support;

use RuntimeException;

/** An expected, client-safe error with an HTTP status and a stable machine-readable code. */
final class ApiException extends RuntimeException
{
    /** @param array<string,mixed> $details */
    public function __construct(
        public readonly int $status,
        public readonly string $errorCode,
        string $message,
        public readonly array $details = []
    ) {
        parent::__construct($message);
    }

    public static function validation(array $errors): self
    {
        return new self(422, 'VALIDATION_FAILED', 'One or more fields are invalid.', ['fields' => $errors]);
    }

    public static function notFound(string $what = 'Resource'): self
    {
        return new self(404, 'NOT_FOUND', "$what not found.");
    }

    public static function forbidden(string $message = 'You are not allowed to perform this action.'): self
    {
        return new self(403, 'FORBIDDEN', $message);
    }

    public static function unauthorized(string $message = 'Authentication required.'): self
    {
        return new self(401, 'UNAUTHENTICATED', $message);
    }

    public static function conflict(string $code, string $message): self
    {
        return new self(409, $code, $message);
    }
}
