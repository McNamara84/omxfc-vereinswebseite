<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\BookType;
use App\Enums\Role;
use App\Models\BookOffer;
use App\Models\BookRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesUserWithRole;

trait RomantauschWorkflowTestFixtures
{
    use CreatesUserWithRole;
    use RefreshDatabase;

    private function data(array $extra = []): array
    {
        return $extra + ['series' => BookType::MaddraxDieDunkleZukunftDerErde->value, 'book_number' => 1, 'condition' => 'Z0'];
    }

    private function pair(?User $owner = null): array
    {
        $owner ??= $this->createUserWithRole(Role::Mitglied);
        $other = $this->createUserWithRole(Role::Mitglied);
        $data = $this->data() + ['book_title' => 'Roman 1'];

        return [BookOffer::create($data + ['user_id' => $owner->id]), BookRequest::create($data + ['user_id' => $other->id])];
    }
}
