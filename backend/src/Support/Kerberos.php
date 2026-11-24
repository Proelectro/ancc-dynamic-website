<?php

declare(strict_types=1);

namespace Ancc\Support;

/**
 * Derive department and entry year from an IITD kerberos ID such as `cs1220123`.
 * Format: department code, 1 programme digit, 2-digit entry year, 4-digit roll.
 * Best-effort only: IITD's OAuth response is the source of truth when it has these fields.
 */
final class Kerberos
{
    public const HELP = 'Use your IITD kerberos ID, e.g. cs1220123';
    private const PATTERN = '/^([a-z]{2,3})(\d)(\d{2})(\d{4})$/';

    private const DEPARTMENTS = [
        'am' => 'Applied Mechanics',
        'bb' => 'Biochemical Engineering and Biotechnology',
        'ce' => 'Civil Engineering',
        'ch' => 'Chemical Engineering',
        'cs' => 'Computer Science and Engineering',
        'cy' => 'Chemistry',
        'ee' => 'Electrical Engineering',
        'es' => 'Energy Science and Engineering',
        'hu' => 'Humanities and Social Sciences',
        'me' => 'Mechanical Engineering',
        'mt' => 'Mathematics',
        'ph' => 'Physics',
        'tt' => 'Textile and Fibre Engineering',
        'ms' => 'Materials Science and Engineering',
        'dd' => 'Design',
    ];

    public static function normalize(string $id): string
    {
        return strtolower(trim($id));
    }

    public static function isValid(string $id): bool
    {
        return (bool) preg_match(self::PATTERN, self::normalize($id));
    }

    /** @return array{department: ?string, entry_year: ?int} */
    public static function parse(string $id): array
    {
        if (!preg_match(self::PATTERN, self::normalize($id), $m)) {
            return ['department' => null, 'entry_year' => null];
        }
        return [
            'department' => self::DEPARTMENTS[substr($m[1], 0, 2)] ?? null,
            'entry_year' => 2000 + (int) $m[3],
        ];
    }

    /** The academic year starts in July: a 2022 entrant is in year 1 from Jul 2022 to Jun 2023. */
    public static function yearOfStudy(?int $entryYear, ?\DateTimeImmutable $today = null): ?int
    {
        if ($entryYear === null) {
            return null;
        }
        $today ??= new \DateTimeImmutable('now', new \DateTimeZone('Asia/Kolkata'));
        $year = (int) $today->format('Y');
        $academicStart = (int) $today->format('n') >= 7 ? $year : $year - 1;
        return max(1, $academicStart - $entryYear + 1);
    }
}
