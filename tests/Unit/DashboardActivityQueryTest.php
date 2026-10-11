<?php

namespace Tests\Unit;

use App\Services\Dashboard\DashboardActivityQuery;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\TestCase;

class DashboardActivityQueryTest extends TestCase
{
    public function test_empty_ids_return_an_empty_collection_without_accessing_the_database(): void
    {
        $previousResolver = Model::getConnectionResolver();
        $resolver = $this->createMock(ConnectionResolverInterface::class);
        $resolver->expects($this->never())->method('connection');
        Model::setConnectionResolver($resolver);

        try {
            $result = (new DashboardActivityQuery)->findMany([]);

            $this->assertInstanceOf(Collection::class, $result);
            $this->assertSame([], $result->all());
        } finally {
            if ($previousResolver === null) {
                Model::unsetConnectionResolver();
            } else {
                Model::setConnectionResolver($previousResolver);
            }
        }
    }
}
