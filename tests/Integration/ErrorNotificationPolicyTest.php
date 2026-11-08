<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Services\ErrorReporting\ErrorNotificationPolicy;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Context;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Tests\TestCase;

class ExpectedException extends RuntimeException implements ShouldntReport {}

pest()->extend(TestCase::class)->use(ErrorNotificationPolicyTestFixtures::class);

beforeEach(function (): void {
    $this->policy = app(ErrorNotificationPolicy::class);
    config(['error-reporting.enabled' => true]);
    app()->instance('env', 'production');
});

test('it accepts unexpected server errors in production', function (): void {
    expect($this->policy->shouldNotify(new RuntimeException('Fehler')))->toBeTrue()
        ->and($this->policy->shouldNotify(new ServiceUnavailableHttpException(null, 'Fehler')))->toBeTrue()
        ->and($this->policy->shouldNotify(new HttpResponseException(new Response(status: 500))))->toBeTrue();
});

test('it is disabled by configuration or environment', function (): void {
    config(['error-reporting.enabled' => false]);
    expect($this->policy->shouldNotify(new RuntimeException('Fehler')))->toBeFalse();
    config(['error-reporting.enabled' => true]);
    app()->instance('env', 'testing');
    expect($this->policy->shouldNotify(new RuntimeException('Fehler')))->toBeFalse();
});

test('it rejects expected client and application errors', function (): void {
    $exceptions = [new ValidationException(validator([], ['name' => 'required'])), new AuthenticationException, new AuthorizationException, new TokenMismatchException, (new ModelNotFoundException)->setModel('TestModel'), new NotFoundHttpException, new AccessDeniedHttpException, new HttpResponseException(new Response(status: 419)), new ExpectedException];
    foreach ($exceptions as $exception) {
        expect($this->policy->shouldNotify($exception))->toBeFalse();
    }
});

test('it rejects errors from the delivery job', function (): void {
    Context::add('error_notification_delivery', true);
    expect($this->policy->shouldNotify(new RuntimeException('SMTP nicht erreichbar')))->toBeFalse();
});
