<?php

declare(strict_types=1);

namespace Ancc\Support;

/** Field validators shared by controllers. Each returns the cleaned value or an error message. */
final class Validate
{
    private const PHONE = '/^\+?[0-9 ]{10,15}$/';
    private const HANDLE = '/^[A-Za-z0-9_.\-]{2,50}$/';

    /**
     * Validates the member-editable profile fields that are present in $input.
     * Empty strings mean "clear this field".
     * @param array<string, mixed> $input
     * @return array{0: array<string, ?string>, 1: array<string, string>} [clean values, errors]
     */
    public static function profile(array $input): array
    {
        $clean = [];
        $errors = [];
        foreach (['phone', 'alt_email', 'hostel', 'codeforces', 'codechef'] as $field) {
            if (!array_key_exists($field, $input)) {
                continue;
            }
            $v = $input[$field];
            if ($v === null || $v === '') {
                $clean[$field] = null;
                continue;
            }
            if (!is_string($v)) {
                $errors[$field] = 'Must be text';
                continue;
            }
            $v = trim($v);
            $error = match ($field) {
                'phone' => preg_match(self::PHONE, $v) ? null : 'Enter a valid phone number (10–15 digits)',
                'alt_email' => filter_var($v, FILTER_VALIDATE_EMAIL) ? null : 'Enter a valid email',
                'hostel' => in_array($v, Lists::HOSTELS, true) ? null : 'Pick a hostel from the list',
                default => preg_match(self::HANDLE, $v) ? null : 'Handles can contain letters, digits, _ . and - only',
            };
            if ($error !== null) {
                $errors[$field] = $error;
            } else {
                $clean[$field] = $v;
            }
        }
        return [$clean, $errors];
    }

    /**
     * @param mixed $roles
     * @return array{0: list<string>, 1: ?string} [roles without "member", error]
     */
    public static function roles(mixed $roles): array
    {
        if ($roles === null) {
            return [[], null];
        }
        if (!is_array($roles) || !array_is_list($roles) || array_filter($roles, fn ($r) => !is_string($r))) {
            return [[], 'roles must be a list of role names'];
        }
        $roles = array_values(array_unique(array_diff($roles, [Roles::MEMBER])));
        $bad = array_diff($roles, Roles::GRANTABLE);
        if ($bad) {
            sort($bad);
            return [[], 'Unknown roles: ' . implode(', ', $bad)];
        }
        sort($roles);
        return [$roles, null];
    }
}
