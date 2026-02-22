<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\ThreeDModel;
use App\Models\User;

class ThreeDModelPolicy
{
    /**
     * Alle eingeloggten Mitglieder dürfen die Übersicht sehen.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Alle eingeloggten Mitglieder dürfen ein einzelnes Modell sehen.
     */
    public function view(User $user, ThreeDModel $threeDModel): bool
    {
        return true;
    }

    /**
     * Nur Admin oder Vorstand dürfen 3D-Modelle erstellen.
     */
    public function create(User $user): bool
    {
        return $user->hasAnyRole(Role::Admin, Role::Vorstand);
    }

    /**
     * Nur Admin oder Vorstand dürfen 3D-Modelle bearbeiten.
     */
    public function update(User $user, ThreeDModel $threeDModel): bool
    {
        return $user->hasAnyRole(Role::Admin, Role::Vorstand);
    }

    /**
     * Nur Admin oder Vorstand dürfen 3D-Modelle löschen.
     */
    public function delete(User $user, ThreeDModel $threeDModel): bool
    {
        return $user->hasAnyRole(Role::Admin, Role::Vorstand);
    }
}
