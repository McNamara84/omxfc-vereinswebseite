<?php

namespace Tests\Unit;

use App\Services\ErrorReporting\ErrorNotificationPolicy;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

class ErrorNotificationPolicyTest extends TestCase
{
    use RefreshDatabase;

    private ErrorNotificationPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = app(ErrorNotificationPolicy::class);
        config(['error-reporting.enabled' => true]);
        app()->instance('env', 'production');
    }

    public function test_it_accepts_unexpected_server_errors_in_production(): void
    {
        $this->assertTrue($this->policy->shouldNotify(new RuntimeException('Fehler')));
        $this->assertTrue($this->policy->shouldNotify(new ServiceUnavailableHttpException(null, 'Fehler')));
        $this->assertTrue($this->policy->shouldNotify(new HttpResponseException(new Response(status: 500))));
    }

    public function test_it_is_disabled_by_configuration_or_environment(): void
    {
        config(['error-reporting.enabled' => false]);
        $this->assertFalse($this->policy->shouldNotify(new RuntimeException('Fehler')));

        config(['error-reporting.enabled' => true]);
        app()->instance('env', 'testing');
        $this->assertFalse($this->policy->shouldNotify(new RuntimeException('Fehler')));
    }

    public function test_it_rejects_expected_client_and_application_errors(): void
    {
        $exceptions = [
            new ValidationException(validator([], ['name' => 'required'])),
            new AuthenticationException,
            new AuthorizationException,
            new TokenMismatchException,
            (new ModelNotFoundException)->setModel('TestModel'),
            new NotFoundHttpException,
            new AccessDeniedHttpException,
            new HttpResponseException(new Response(status: 419)),
            new ExpectedException,
        ];

        foreach ($exceptions as $exception) {
            $this->assertFalse($this->policy->shouldNotify($exception), $exception::class);
        }
    }

    public function test_it_rejects_errors_from_the_delivery_job(): void
    {
        Context::add('error_notification_delivery', true);

        $this->assertFalse($this->policy->shouldNotify(new RuntimeException('SMTP nicht erreichbar')));
    }
}

class ExpectedException extends RuntimeException implements ShouldntReport {}
