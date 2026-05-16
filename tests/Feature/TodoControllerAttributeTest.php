<?php

namespace Tests\Feature;

use App\Http\Controllers\TodoController;
use App\Models\Todo;
use Illuminate\Auth\Middleware\Authorize as AuthorizeMiddleware;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Attributes\Controllers\Authorize as AuthorizeAttribute;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Tests\TestCase;

#[CoversClass(TodoController::class)]
class TodoControllerAttributeTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function create_und_store_verwenden_laravel_13_authorize_attribute_fuer_create(): void
    {
        $createAttributes = (new ReflectionMethod(TodoController::class, 'create'))
            ->getAttributes(AuthorizeAttribute::class);

        $storeAttributes = (new ReflectionMethod(TodoController::class, 'store'))
            ->getAttributes(AuthorizeAttribute::class);

        $this->assertCount(1, $createAttributes);
        $this->assertCount(1, $storeAttributes);

        $create = $createAttributes[0]->newInstance();
        $store = $storeAttributes[0]->newInstance();

        $this->assertSame(AuthorizeMiddleware::using('create', Todo::class), $create->middleware);
        $this->assertSame(AuthorizeMiddleware::using('create', Todo::class), $store->middleware);
    }

    #[Test]
    public function verify_verwendet_laravel_13_authorize_attribute_fuer_verify(): void
    {
        $verifyAttributes = (new ReflectionMethod(TodoController::class, 'verify'))
            ->getAttributes(AuthorizeAttribute::class);

        $this->assertCount(1, $verifyAttributes);

        $verify = $verifyAttributes[0]->newInstance();

        $this->assertSame(AuthorizeMiddleware::using('verify', Todo::class), $verify->middleware);
    }
}
