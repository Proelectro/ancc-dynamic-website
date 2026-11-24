<?php

declare(strict_types=1);

namespace Ancc\Support;

final class Roles
{
    public const MEMBER = 'member';
    public const COORDINATOR = 'coordinator';
    public const ICPC_ADMIN = 'icpc_admin';
    public const FINANCE = 'finance';
    public const SUPER_ADMIN = 'super_admin';

    /** Roles that can be granted. Every user implicitly has MEMBER. */
    public const GRANTABLE = [self::COORDINATOR, self::ICPC_ADMIN, self::FINANCE, self::SUPER_ADMIN];

    /** Roles that may open the admin area. */
    public const ANY_ADMIN = [self::COORDINATOR, self::ICPC_ADMIN, self::FINANCE];

    /**
     * super_admin passes every check; member is implied for everyone.
     * @param list<string> $userRoles
     * @param list<string> $required
     */
    public static function hasAny(array $userRoles, array $required): bool
    {
        if (in_array(self::SUPER_ADMIN, $userRoles, true)) {
            return true;
        }
        return (bool) array_intersect([...$userRoles, self::MEMBER], $required);
    }
}
