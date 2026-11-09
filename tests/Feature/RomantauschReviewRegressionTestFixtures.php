<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\BookType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesUserWithRole;

trait RomantauschReviewRegressionTestFixtures
{
    use CreatesUserWithRole;
    use RefreshDatabase;

    private function data(User $owner, array $extra = []): array
    {
        return $extra + ['user_id' => $owner->id, 'series' => BookType::MaddraxDieDunkleZukunftDerErde->value, 'book_number' => 1, 'book_title' => 'Roman1', 'condition' => 'Z0'];
    }
}
