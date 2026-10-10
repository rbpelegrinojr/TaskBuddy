<?php
declare(strict_types=1);

namespace TaskBuddy\Support;

/** Holds the authenticated identity for the current request (server-derived, never client-supplied). */
final class Auth
{
    /** @var array<string,mixed>|null */
    private static ?array $user = null;
    private static ?int $tokenId = null;

    /** @param array<string,mixed>|null $user */
    public static function set(?array $user, ?int $tokenId = null): void
    {
        self::$user = $user;
        self::$tokenId = $tokenId;
    }

    /** @return array<string,mixed> */
    public static function user(): array
    {
        if (self::$user === null) {
            throw ApiException::unauthorized();
        }
        return self::$user;
    }

    public static function id(): int
    {
        return (int)self::user()['id'];
    }

    public static function role(): string
    {
        return (string)self::user()['role'];
    }

    public static function tokenId(): ?int
    {
        return self::$tokenId;
    }

    public static function check(): bool
    {
        return self::$user !== null;
    }
}
