<?php

declare(strict_types=1);

namespace Ancc\Auth;

/** Who someone is, according to IITD (or the dev login form). */
final class Identity
{
    public function __construct(
        public readonly string $kerberosId,
        public readonly string $name,
        public readonly string $email,
        public readonly ?string $department = null,
    ) {
    }
}
