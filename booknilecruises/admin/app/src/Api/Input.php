<?php
declare(strict_types=1);

namespace Bnc\Api;

final class Input
{
    public static function text(array $d, string $key, int $max, bool $required = false): string
    {
        $v = $d[$key] ?? '';
        if (!is_string($v) || mb_strlen($v) > $max || ($required && trim($v) === '')) self::invalid($key);
        return trim($v);
    }

    public static function invalid(string $field, string $message = 'Invalid value'): never
    {
        throw new ApiException(422, 'Validation failed', [$field => $message]);
    }

    public static function datetime(string $value, string $field): \DateTimeImmutable
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(?::\d{2})?(?:Z|[+-]\d{2}:\d{2})?$/D', $value)) self::invalid($field);
        try { $date = new \DateTimeImmutable($value); }
        catch (\Exception) { self::invalid($field); }
        $errors = \DateTimeImmutable::getLastErrors();
        if (($errors && ($errors['warning_count'] || $errors['error_count'])) || (int) $date->format('Y') < 1000) self::invalid($field);
        return $date->setTimezone(new \DateTimeZone(date_default_timezone_get()));
    }
}
