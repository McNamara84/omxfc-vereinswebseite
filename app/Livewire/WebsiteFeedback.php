<?php

namespace App\Livewire;

use App\Data\WebsiteFeedback\WebsiteFeedbackData;
use App\Enums\WebsiteFeedbackCategory;
use App\Models\User;
use App\Services\WebsiteFeedback\FeedbackDeliveryService;
use App\Services\WebsiteFeedback\FeedbackRecipientResolver;
use App\Services\WebsiteFeedback\FeedbackSessionService;
use App\Services\WebsiteFeedback\FeedbackSubmissionThrottle;
use App\Support\UriSupport;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Throwable;

class WebsiteFeedback extends Component
{
    public bool $showModal = false;

    public string $category = '';

    public string $message = '';

    public bool $anonymous = false;

    public string $pageUrl = '';

    public string $pageTitle = '';

    public bool $sent = false;

    public function mount(FeedbackSessionService $sessions): void
    {
        $user = Auth::user();

        if ($user instanceof User) {
            $sessions->register($user, $this->session());
        }
    }

    public function openFeedback(
        string $pageUrl,
        string $pageTitle,
        FeedbackSessionService $sessions,
    ): void {
        $this->authorizedUser($sessions);

        $normalizedPageUrl = $this->normalizePageUrl($pageUrl);
        $normalizedPageTitle = $this->normalizePageTitle($pageTitle, $normalizedPageUrl);

        $this->reset(['category', 'message', 'anonymous']);
        $this->pageUrl = $normalizedPageUrl;
        $this->pageTitle = $normalizedPageTitle;
        $this->showModal = true;
        $this->sent = false;
        $this->resetValidation();
    }

    public function closeFeedback(): void
    {
        $this->showModal = false;
        $this->resetValidation();
    }

    public function submit(
        FeedbackSessionService $sessions,
        FeedbackRecipientResolver $recipients,
        FeedbackSubmissionThrottle $throttle,
        FeedbackDeliveryService $delivery,
    ): void {
        $user = $this->authorizedUser($sessions);

        $validated = $this->validate([
            'category' => ['required', Rule::enum(WebsiteFeedbackCategory::class)],
            'message' => [
                'required',
                'string',
                'min:'.max(1, (int) config('feedback.message_min_length', 10)),
                'max:'.max(1, (int) config('feedback.message_max_length', 5000)),
            ],
            'anonymous' => ['boolean'],
            'pageUrl' => ['required', 'string', 'max:2048'],
            'pageTitle' => ['required', 'string', 'max:200'],
        ], [
            'category.required' => 'Bitte wähle eine Kategorie aus.',
            'category.enum' => 'Bitte wähle eine gültige Kategorie aus.',
            'message.required' => 'Bitte beschreibe dein Feedback.',
            'message.min' => 'Bitte beschreibe dein Feedback mit mindestens :min Zeichen.',
            'message.max' => 'Dein Feedback darf höchstens :max Zeichen lang sein.',
        ]);

        // Re-normalize browser-controlled context immediately before queuing.
        $pageUrl = $this->normalizePageUrl($validated['pageUrl']);
        $pageTitle = $this->normalizePageTitle($validated['pageTitle'], $pageUrl);
        $recipientEmails = $recipients->resolve();

        if ($recipientEmails === []) {
            Log::warning('Website-Feedback kann nicht versendet werden: Kein aktiver Admin- oder Vorstand-Empfänger gefunden.', [
                'user_id' => $user->getKey(),
            ]);
            $this->addError('delivery', 'Das Feedback kann gerade nicht versendet werden. Bitte versuche es später erneut.');

            return;
        }

        if ($throttle->tooManyAttempts($user)) {
            $this->addError('delivery', 'Du hast in kurzer Zeit bereits mehrere Rückmeldungen gesendet. Bitte versuche es später erneut.');

            return;
        }

        $anonymous = (bool) $validated['anonymous'];
        $feedback = new WebsiteFeedbackData(
            category: WebsiteFeedbackCategory::from($validated['category']),
            message: trim($validated['message']),
            pageTitle: $pageTitle,
            pageUrl: $pageUrl,
            submittedAt: CarbonImmutable::now(),
            reporterName: $anonymous ? null : $user->nicknameOrName(),
            reporterEmail: $anonymous ? null : trim((string) $user->email),
        );

        try {
            $delivery->queue($feedback, $recipientEmails);
        } catch (Throwable $exception) {
            report($exception);

            Log::warning('Website-Feedback konnte nicht vollständig in die Mail-Queue übernommen werden.', [
                'user_id' => $user->getKey(),
                'exception_class' => $exception::class,
            ]);
            $this->addError('delivery', 'Das Feedback kann gerade nicht versendet werden. Bitte versuche es später erneut.');

            return;
        }

        $throttle->record($user);
        $sessions->markSubmitted($user, $this->session());

        $this->reset(['category', 'message', 'anonymous', 'pageUrl', 'pageTitle']);
        $this->showModal = false;
        $this->sent = true;
        $this->resetValidation();
        $this->dispatch('toast',
            type: 'success',
            title: 'Vielen Dank für dein Feedback!',
            description: 'Deine Rückmeldung wurde an Admins und Vorstand weitergeleitet.',
        );
    }

    public function render(FeedbackSessionService $sessions)
    {
        $user = Auth::user();
        $available = $user instanceof User
            && $sessions->isAvailable($user, $this->session());

        return view('livewire.website-feedback', [
            'available' => $available,
            'categories' => WebsiteFeedbackCategory::options(),
            'messageMinLength' => max(1, (int) config('feedback.message_min_length', 10)),
            'messageMaxLength' => max(1, (int) config('feedback.message_max_length', 5000)),
        ]);
    }

    private function authorizedUser(FeedbackSessionService $sessions): User
    {
        $user = Auth::user();

        abort_unless(
            $user instanceof User
                && $sessions->isAvailable($user, $this->session()),
            403,
        );

        return $user;
    }

    private function session(): Session
    {
        $session = request()->hasSession()
            ? request()->session()
            : app('session.store');

        if (! $session instanceof Session) {
            throw new \LogicException('Website feedback requires a Laravel session store.');
        }

        return $session;
    }

    private function normalizePageUrl(string $pageUrl): string
    {
        $pageUrl = trim($pageUrl);
        $normalized = UriSupport::normalizeAbsoluteHttpUrl($pageUrl);

        if ($normalized === null || mb_strlen($normalized) > 2048) {
            abort(422, 'Ungültige Quellseite für das Feedback.');
        }

        $parts = parse_url($normalized);
        $configuredHost = parse_url((string) config('app.url'), PHP_URL_HOST);
        $allowedHosts = array_values(array_unique(array_filter([
            strtolower(request()->getHost()),
            is_string($configuredHost) ? strtolower($configuredHost) : null,
        ])));
        $host = is_array($parts) && isset($parts['host']) ? strtolower((string) $parts['host']) : '';

        if (
            ! is_array($parts)
            || isset($parts['user'])
            || isset($parts['pass'])
            || ! in_array($host, $allowedHosts, true)
        ) {
            abort(422, 'Ungültige Quellseite für das Feedback.');
        }

        $fragmentPosition = strpos($normalized, '#');

        return $fragmentPosition === false
            ? $normalized
            : substr($normalized, 0, $fragmentPosition);
    }

    private function normalizePageTitle(string $pageTitle, string $pageUrl): string
    {
        $title = Str::limit(
            Str::squish(strip_tags($pageTitle)),
            200,
            '',
        );

        if ($title !== '') {
            return $title;
        }

        $path = parse_url($pageUrl, PHP_URL_PATH);

        return is_string($path) && $path !== '' ? $path : 'Vereinswebsite';
    }
}
