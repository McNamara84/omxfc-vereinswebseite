<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Dashboard\DashboardActivityQuery;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\TestCase;

pest()->extend(TestCase::class);

test('empty ids return an empty collection without accessing the database', function (): void {
    $previousResolver = Model::getConnectionResolver();
    $resolver = $this->createMock(ConnectionResolverInterface::class);
    $resolver->expects($this->never())->method('connection');
    Model::setConnectionResolver($resolver);
    try {
        $result = (new DashboardActivityQuery)->findMany([]);
        expect($result)->toBeInstanceOf(Collection::class)
            ->and($result->all())->toBe([]);
    } finally {
        if ($previousResolver === null) {
            Model::unsetConnectionResolver();
        } else {
            Model::setConnectionResolver($previousResolver);
        }
    }
});
