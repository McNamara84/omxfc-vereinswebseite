<?php

namespace App\Http\Livewire;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Livewire\Component;
use Throwable;

class ContactEmailReveal extends Component
{
    public bool $revealed = false;

    public ?string $email = null;

    public string $hcaptchaToken = '';

    public function reveal(): void
    {
        if ($this->revealed) {
            return;
        }

        $this->resetErrorBag();

        $this->validate([
            'hcaptchaToken' => $this->captchaIsRequired()
                ? ['required', 'string']
                : ['nullable', 'string'],
        ], [
            'hcaptchaToken.required' => __('Bitte bestätige, dass du kein Roboter bist.'),
        ]);

        if (! $this->verifyHCaptcha($this->hcaptchaToken)) {
            $this->hcaptchaToken = '';
            $this->addError('hcaptchaToken', __('Die Captcha-Prüfung ist fehlgeschlagen. Bitte versuche es erneut.'));
            $this->dispatch('reset-hcaptcha');

            return;
        }

        $email = $this->fetchEmailViaSignedRoute();

        if ($email === null) {
            $this->hcaptchaToken = '';
            $this->addError('reveal', __('Die Kontaktadresse konnte nicht geladen werden. Bitte versuche es später erneut.'));
            $this->dispatch('reset-hcaptcha');

            return;
        }

        $this->email = $email;
        $this->revealed = true;
        $this->hcaptchaToken = '';
        $this->dispatch('email-revealed', email: $email);
    }

    protected function captchaIsRequired(): bool
    {
        if (! (bool) config('services.hcaptcha.enabled', false)) {
            return false;
        }

        $siteKey = config('services.hcaptcha.sitekey');
        $secret = config('services.hcaptcha.secret');
        $bypassToken = config('services.hcaptcha.bypass_token');

        return filled($siteKey) && (filled($secret) || filled($bypassToken));
    }

    protected function verifyHCaptcha(?string $token): bool
    {
        if (! $this->captchaIsRequired()) {
            return true;
        }

        if (blank($token)) {
            return false;
        }

        $bypassToken = config('services.hcaptcha.bypass_token');

        if ($bypassToken && hash_equals($bypassToken, $token)) {
            return true;
        }

        $secret = config('services.hcaptcha.secret');

        if (blank($secret)) {
            return false;
        }

        $endpoint = config('services.hcaptcha.endpoint', 'https://hcaptcha.com/siteverify');
        $timeout = (int) config('services.hcaptcha.timeout', 5);
        $threshold = config('services.hcaptcha.threshold');

        try {
            $response = Http::asForm()
                ->timeout($timeout)
                ->post($endpoint, [
                    'secret' => $secret,
                    'response' => $token,
                    'remoteip' => request()->ip(),
                ]);
        } catch (ConnectionException $exception) {
            report($exception);

            return false;
        }

        if (! $response->successful() || ! $response->json('success')) {
            return false;
        }

        $score = $response->json('score');

        if (is_numeric($threshold) && is_numeric($score) && $score < $threshold) {
            return false;
        }

        return true;
    }

    protected function fetchEmailViaSignedRoute(): ?string
    {
        $ttlSeconds = (int) config('services.hcaptcha.signature_ttl', 300);
        $signedUrl = URL::temporarySignedRoute(
            'contact.email.reveal',
            now()->addSeconds($ttlSeconds > 0 ? $ttlSeconds : 300)
        );

        $host = parse_url($signedUrl, PHP_URL_HOST) ?? request()->getHost();

        $request = HttpRequest::create(
            $signedUrl,
            'GET',
            [],
            request()->cookies->all(),
            [],
            [
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_HOST' => $host,
                'REMOTE_ADDR' => request()->ip(),
            ]
        );

        /** @var \Illuminate\Contracts\Http\Kernel $kernel */
        $kernel = app(Kernel::class);

        try {
            $response = $kernel->handle($request);
        } catch (Throwable $exception) {
            report($exception);

            return null;
        } finally {
            if (isset($response)) {
                $kernel->terminate($request, $response);
            }
        }

        if ($response->getStatusCode() !== 200) {
            return null;
        }

        $payload = json_decode($response->getContent(), true);

        if (! is_array($payload)) {
            return null;
        }

        return $payload['email'] ?? null;
    }

    public function render()
    {
        return view('livewire.contact-email-reveal', [
            'captchaEnabled' => $this->captchaIsRequired(),
            'siteKey' => config('services.hcaptcha.sitekey'),
        ]);
    }
}
