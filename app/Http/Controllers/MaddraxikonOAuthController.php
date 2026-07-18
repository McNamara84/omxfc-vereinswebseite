<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Maddraxikon\AccountEligibility;
use App\Services\Maddraxikon\AccountLinkService;
use App\Services\Maddraxikon\Exceptions\AccountLinkConflictException;
use App\Services\Maddraxikon\Exceptions\AccountLinkIneligibleException;
use App\Services\Maddraxikon\Exceptions\InvalidOAuthAttemptException;
use App\Services\Maddraxikon\Exceptions\OAuthFlowException;
use App\Services\Maddraxikon\OAuthAttemptStore;
use App\Services\Maddraxikon\OAuthClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class MaddraxikonOAuthController extends Controller
{
    public function start(
        Request $request,
        OAuthAttemptStore $attemptStore,
        OAuthClient $oauthClient,
    ): RedirectResponse {
        if (! config('maddraxikon.features.linking_enabled')) {
            return $this->failure('Neue Maddraxikon-Verknüpfungen sind derzeit deaktiviert.');
        }

        $request->validate([
            'consent' => ['required', 'accepted'],
        ], [
            'consent.accepted' => 'Bitte stimme der beschriebenen Datenverarbeitung zu.',
            'consent.required' => 'Bitte stimme der beschriebenen Datenverarbeitung zu.',
        ]);

        /** @var User $user */
        $user = $request->user();

        try {
            $attempt = $attemptStore->create(
                $user,
                $request->session()->token(),
                (string) config('maddraxikon.consent_version'),
            );
            $authorizationUrl = $oauthClient->authorizationUrl(
                $attempt->state,
                $attempt->codeChallenge,
            );
        } catch (OAuthFlowException) {
            return $this->failure('Die Maddraxikon-Verknüpfung konnte nicht gestartet werden. Bitte versuche es später erneut.');
        }

        return redirect()->away($authorizationUrl);
    }

    public function callback(
        Request $request,
        AccountEligibility $eligibility,
        OAuthAttemptStore $attemptStore,
        OAuthClient $oauthClient,
        AccountLinkService $accountLinkService,
    ): RedirectResponse {
        $user = $request->user();

        /*
         * Do not use Authenticate's intended redirect here: it would retain
         * the callback URL, including code and state, in the session.
         */
        if (! $user instanceof User) {
            return redirect()->route('login');
        }

        $state = $request->query('state');

        if (! is_string($state)) {
            return $this->failure('Der Verknüpfungsversuch ist ungültig oder abgelaufen. Bitte starte ihn erneut.');
        }

        try {
            $attempt = $attemptStore->consume($state, $user, $request->session()->token());
        } catch (InvalidOAuthAttemptException) {
            return $this->failure('Der Verknüpfungsversuch ist ungültig oder abgelaufen. Bitte starte ihn erneut.');
        }

        if (
            ! config('maddraxikon.features.linking_enabled')
            || ! $eligibility->isEligible($user)
        ) {
            return $this->failure('Dein Vereinskonto ist derzeit nicht für die Maddraxikon-Verknüpfung berechtigt.');
        }

        if (is_string($request->query('error')) && $request->query('error') !== '') {
            return $this->failure('Die Verknüpfung wurde im Maddraxikon nicht bestätigt.');
        }

        $code = $request->query('code');

        if (! is_string($code) || $code === '' || strlen($code) > 4096) {
            return $this->failure('Der Maddraxikon-Callback enthielt keinen gültigen Autorisierungscode.');
        }

        try {
            $identity = $oauthClient->identityFromAuthorizationCode($code, $attempt->codeVerifier);
            $link = $accountLinkService->activate(
                $user,
                $identity,
                $attempt->consentVersion,
                $attempt->consentedAt,
            );
        } catch (AccountLinkIneligibleException) {
            return $this->failure('Dein Vereinskonto ist derzeit nicht für die Maddraxikon-Verknüpfung berechtigt.');
        } catch (AccountLinkConflictException|OAuthFlowException) {
            return $this->failure('Das Maddraxikon-Konto konnte nicht verknüpft werden. Bitte versuche es erneut oder kontaktiere den Vorstand.');
        }

        return redirect()
            ->route('profile.show')
            ->with(
                'maddraxikon_status',
                "Dein Maddraxikon-Konto „{$link->wiki_username}“ ist jetzt verknüpft.",
            );
    }

    public function disconnect(
        Request $request,
        AccountLinkService $accountLinkService,
    ): RedirectResponse {
        /** @var User $user */
        $user = $request->user();
        $accountLinkService->disconnect($user);

        return redirect()
            ->route('profile.show')
            ->with(
                'maddraxikon_status',
                'Die Maddraxikon-Verknüpfung wurde getrennt. Bereits gutgeschriebene Baxx bleiben erhalten.',
            );
    }

    private function failure(string $message): RedirectResponse
    {
        return redirect()
            ->route('profile.show')
            ->with('maddraxikon_error', $message);
    }
}
