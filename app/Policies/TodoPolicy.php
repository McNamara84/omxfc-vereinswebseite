<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Todo;
use App\Models\User;

class TodoPolicy
{
    private function role(User $user): ?Role
    {
        return $user->role();
    }

    public function create(User $user): bool
    {
        $role = $this->role($user);
        return $role && in_array($role, [Role::Kassenwart, Role::Vorstand, Role::Admin], true);
    }

    public function update(User $user, Todo $todo): bool
    {
        return $todo->created_by === $user->id || $this->create($user);
    }

    public function delete(User $user, Todo $todo): bool
    {
        return $this->update($user, $todo);
    }

    public function assign(User $user, Todo $todo): bool
    {
        $role = $this->role($user);
        return $role && in_array($role, [Role::Mitglied, Role::Ehrenmitglied, Role::Kassenwart, Role::Vorstand, Role::Admin], true);
    }

    public function verify(User $user): bool
    {
        $role = $this->role($user);
        return $role && in_array($role, [Role::Kassenwart, Role::Vorstand, Role::Admin], true);
    }
}
