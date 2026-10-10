<?php
declare(strict_types=1);

namespace TaskBuddy\Support;

use DateTimeImmutable;
use DateTimeZone;

/** Collects field errors; call fail() to throw a 422 when any were recorded. */
final class Validator
{
    /** @var array<string,string> */
    private array $errors = [];

    /** @param array<string,mixed> $data */
    public function __construct(private readonly array $data)
    {
    }

    public function string(string $key, bool $required = true, int $min = 0, int $max = 255): ?string
    {
        $v = $this->data[$key] ?? null;
        if ($v === null || (is_string($v) && trim($v) === '')) {
            if ($required) {
                $this->errors[$key] = 'This field is required.';
            }
            return null;
        }
        if (!is_string($v)) {
            $this->errors[$key] = 'Must be a string.';
            return null;
        }
        $v = trim($v);
        $len = mb_strlen($v);
        if ($len < $min || $len > $max) {
            $this->errors[$key] = "Length must be between $min and $max characters.";
            return null;
        }
        return $v;
    }

    public function email(string $key): ?string
    {
        $v = $this->string($key, true, 3, 190);
        if ($v !== null && filter_var($v, FILTER_VALIDATE_EMAIL) === false) {
            $this->errors[$key] = 'Must be a valid email address.';
            return null;
        }
        return $v === null ? null : strtolower($v);
    }

    public function enum(string $key, array $allowed, bool $required = true): ?string
    {
        $v = $this->data[$key] ?? null;
        if ($v === null || $v === '') {
            if ($required) {
                $this->errors[$key] = 'This field is required.';
            }
            return null;
        }
        if (!is_string($v) || !in_array($v, $allowed, true)) {
            $this->errors[$key] = 'Must be one of: ' . implode(', ', $allowed) . '.';
            return null;
        }
        return $v;
    }

    public function int(string $key, bool $required = true, int $min = 0, int $max = PHP_INT_MAX): ?int
    {
        $v = $this->data[$key] ?? null;
        if ($v === null || $v === '') {
            if ($required) {
                $this->errors[$key] = 'This field is required.';
            }
            return null;
        }
        if (is_string($v) && preg_match('/^-?\d+$/', $v)) {
            $v = (int)$v;
        }
        if (!is_int($v) || $v < $min || $v > $max) {
            $this->errors[$key] = "Must be an integer between $min and $max.";
            return null;
        }
        return $v;
    }

    /** Positive money amount with at most 2 decimals; returned as a normalized string. */
    public function money(string $key, bool $required = true, float $min = 0.01, float $max = 99999999.99): ?string
    {
        $v = $this->data[$key] ?? null;
        if ($v === null || $v === '') {
            if ($required) {
                $this->errors[$key] = 'This field is required.';
            }
            return null;
        }
        if ((!is_int($v) && !is_float($v) && !is_string($v)) || !preg_match('/^\d{1,8}(\.\d{1,2})?$/', (string)$v)) {
            $this->errors[$key] = 'Must be a valid amount with at most 2 decimal places.';
            return null;
        }
        $f = (float)$v;
        if ($f < $min || $f > $max) {
            $this->errors[$key] = "Amount must be between $min and $max.";
            return null;
        }
        return number_format($f, 2, '.', '');
    }

    public function decimal(string $key, bool $required, float $min, float $max): ?string
    {
        $v = $this->data[$key] ?? null;
        if ($v === null || $v === '') {
            if ($required) {
                $this->errors[$key] = 'This field is required.';
            }
            return null;
        }
        if (!is_numeric($v) || (float)$v < $min || (float)$v > $max) {
            $this->errors[$key] = "Must be a number between $min and $max.";
            return null;
        }
        return number_format((float)$v, 2, '.', '');
    }

    public function bool(string $key, bool $default = false): bool
    {
        $v = $this->data[$key] ?? null;
        if ($v === null) {
            return $default;
        }
        if (is_bool($v)) {
            return $v;
        }
        if (in_array($v, [0, 1, '0', '1', 'true', 'false'], true)) {
            return $v === 1 || $v === '1' || $v === 'true';
        }
        $this->errors[$key] = 'Must be a boolean.';
        return $default;
    }

    /** ISO-8601 datetime (offset required or assumed UTC) that must be in the future. Returns UTC. */
    public function futureDateTime(string $key, ?DateTimeImmutable $now = null): ?DateTimeImmutable
    {
        $v = $this->string($key, true, 10, 40);
        if ($v === null) {
            return null;
        }
        try {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}(:\d{2}(\.\d{1,6})?)?(Z|[+-]\d{2}:?\d{2})?$/', $v)) {
                throw new \InvalidArgumentException('format');
            }
            $d = new DateTimeImmutable($v, new DateTimeZone('UTC'));
        } catch (\Exception) {
            $this->errors[$key] = 'Must be a valid ISO-8601 date and time.';
            return null;
        }
        $d = $d->setTimezone(new DateTimeZone('UTC'));
        if ($d <= ($now ?? Clock::now())) {
            $this->errors[$key] = 'Must be in the future.';
            return null;
        }
        return $d;
    }

    /** @return int[]|null list of unique positive ints */
    public function intList(string $key, bool $required = false, int $maxItems = 50): ?array
    {
        $v = $this->data[$key] ?? null;
        if ($v === null) {
            if ($required) {
                $this->errors[$key] = 'This field is required.';
            }
            return $required ? null : [];
        }
        if (!is_array($v) || count($v) > $maxItems) {
            $this->errors[$key] = "Must be a list of at most $maxItems integers.";
            return null;
        }
        $out = [];
        foreach ($v as $item) {
            if (!is_int($item) && !(is_string($item) && ctype_digit($item))) {
                $this->errors[$key] = 'Must contain only integer identifiers.';
                return null;
            }
            $out[(int)$item] = (int)$item;
        }
        return array_values($out);
    }

    public function error(string $key, string $message): void
    {
        $this->errors[$key] = $message;
    }

    public function hasErrors(): bool
    {
        return $this->errors !== [];
    }

    public function fail(): void
    {
        if ($this->errors) {
            throw ApiException::validation($this->errors);
        }
    }
}
