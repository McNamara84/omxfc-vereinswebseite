<?php

namespace App\Services\Memberships;

use App\Enums\Role;

final readonly class MembershipSnapshot
{
    public ?Role $role;

    public function __construct(
        public int $userId,
        public int $teamId,
        public bool $exists,
        public ?string $rawRole,
        public ?bool $userExists = null,
    ) {
        $this->role = Role::tryFrom($rawRole ?? '');
    }
}
