<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\TwoFactorAuthenticationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Features;
use Rushing\LaravelDataSchemasScribe\Attributes\RequestFromData;
use Splicewire\Beam\Accounts\Data\Pages\SecurityPageData;
use Splicewire\Beam\Accounts\Data\PasswordUpdateInputData;
use Splicewire\Beam\Accounts\Http\Controllers\SecurityController as AccountSecurityController;

class SecurityController extends Controller
{
    /**
     * Show the user's security settings page.
     */
    public function edit(TwoFactorAuthenticationRequest $request): Response
    {
        $props = [
            'canManageTwoFactor' => false,
            'canManagePasskeys' => false,
            'passkeys' => [],
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ];

        $props['canManageTwoFactor'] = Features::canManageTwoFactorAuthentication();
        $props['canManagePasskeys'] = Features::canManagePasskeys();
        $props['passkeys'] = Features::canManagePasskeys()
                ? $request->user()
                    ->passkeys()
                    ->select(['id', 'name', 'credential', 'created_at', 'last_used_at'])
                    ->latest()
                    ->get()
                    ->map(fn ($passkey) => [
                        'id' => $passkey->id,
                        'name' => $passkey->name,
                        'authenticator' => $passkey->authenticator,
                        'createdAtDiff' => $passkey->created_at->diffForHumans(),
                        'lastUsedAtDiff' => $passkey->last_used_at?->diffForHumans(),
                    ])
                    ->values()
                    ->all()
                : [];

        if (Features::canManageTwoFactorAuthentication()) {
            $request->ensureStateIsValid();

            $props['twoFactorEnabled'] = $request->user()->hasEnabledTwoFactorAuthentication();
            $props['requiresConfirmation'] = Features::optionEnabled(Features::twoFactorAuthentication(), 'confirm');
        }

        return Inertia::render('settings/security', SecurityPageData::from($props));
    }

    /**
     * Update the user's password.
     */
    #[RequestFromData(PasswordUpdateInputData::class)]
    public function update(Request $request, PasswordUpdateInputData $input): RedirectResponse
    {
        $response = app(AccountSecurityController::class)->update($request, $input);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Password updated.')]);

        return $response;
    }
}
