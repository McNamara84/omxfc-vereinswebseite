<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\RpgExperienceEntry;
use App\Services\RpgCharacterAdvancementService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Support\RpgProgressionFixtures;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class, RpgProgressionFixtures::class);

beforeEach(function (): void {
    $this->progressionFixtures();
    $this->credit();
});

test('only approval changes character and spends points once', function (): void {
    $service = app(RpgCharacterAdvancementService::class);
    $input = $this->advancementInput();
    $original = $this->character->payload;
    $request = $service->submit($this->player, $this->character->id, $input);
    expect($this->character->experienceBalance())->toBe(60)
        ->and($this->character->fresh()->payload)->toBe($original);
    $service->decide($this->leader, $request->id, 'approved');
    $service->decide($this->leader, $request->id, 'approved');
    expect($service->submit($this->player, $this->character->id, $input)->id)->toBe($request->id)
        ->and($this->character->experienceBalance())->toBe(54)
        ->and($this->character->fresh()->revision)->toBe(1)
        ->and(RpgExperienceEntry::count())->toBe(2)
        ->and($this->character->fresh()->initial_payload)->toBe($original)
        ->and(collect($this->character->fresh()->payload['skills'])->firstWhere('name', 'Nahkampf')['value'])->toBe(3);
});

test('rejection and withdrawal do not spend points', function (): void {
    $service = app(RpgCharacterAdvancementService::class);
    $first = $service->submit($this->player, $this->character->id, $this->advancementInput());
    $service->decide($this->leader, $first->id, 'rejected', 'Im Abenteuer nicht begründet.');
    $second = $service->submit($this->player, $this->character->id, $this->advancementInput());
    $service->decide($this->player, $second->id, 'withdrawn');
    expect($this->character->experienceBalance())->toBe(60)
        ->and($this->character->fresh()->revision)->toBe(0)
        ->and(fn () => $service->decide($this->leader, $second->id, 'approved'))->toThrow(ValidationException::class);
});

test('only one pending request is allowed', function (): void {
    $service = app(RpgCharacterAdvancementService::class);
    $service->submit($this->player, $this->character->id, $this->advancementInput());
    expect(fn () => $service->submit($this->player, $this->character->id, $this->advancementInput()))->toThrow(ValidationException::class);
});

test('owner cannot approve', function (): void {
    $service = app(RpgCharacterAdvancementService::class);
    $request = $service->submit($this->player, $this->character->id, $this->advancementInput());
    expect(fn () => $service->decide($this->player, $request->id, 'approved'))->toThrow(AuthorizationException::class);
});

test('foreign character cannot be improved', function (): void {
    expect(fn () => app(RpgCharacterAdvancementService::class)->submit($this->leader, $this->character->id, $this->advancementInput()))->toThrow(AuthorizationException::class);
});

test('insufficient points block entire request', function (): void {
    expect(fn () => app(RpgCharacterAdvancementService::class)->submit($this->player, $this->character->id, $this->advancementInput([$this->operation(steps: 10)])))->toThrow(ValidationException::class);
});

test('changed state blocks approval without partial write', function (): void {
    $service = app(RpgCharacterAdvancementService::class);
    $request = $service->submit($this->player, $this->character->id, $this->advancementInput());
    $this->character->forceFill(['revision' => 1])->save();
    try {
        $service->decide($this->leader, $request->id, 'approved');
        $this->fail('Expected stale state rejection');
    } catch (ValidationException) {
        expect($request->fresh()->status)->toBe('pending')
            ->and($this->character->experienceBalance())->toBe(60);
    }
});
