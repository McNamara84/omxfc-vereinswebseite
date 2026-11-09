<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Enums\BookType;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesUserWithRole;

trait RomantauschPhotoCommitTestFixtures
{
    use CreatesUserWithRole;

    private function data(User $owner, array $extra = []): array
    {
        return $extra + ['user_id' => $owner->id, 'series' => BookType::MaddraxDieDunkleZukunftDerErde->value, 'book_number' => 1, 'book_title' => 'Roman1', 'condition' => 'Z0'];
    }

    private function finishTransaction(bool $commit): void
    {
        expect(DB::transactionLevel())->toBe(1)
            ->and(DB::connection()->getPdo()->inTransaction())->toBeTrue();
        $commit ? DB::commit() : DB::rollBack();
        expect(DB::transactionLevel())->toBe(0)
            ->and(DB::connection()->getPdo()->inTransaction())->toBeFalse();
    }
}
