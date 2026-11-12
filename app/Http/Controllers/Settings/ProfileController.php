<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Rushing\LaravelDataSchemasScribe\Attributes\RequestFromData;
use Splicewire\Beam\Accounts\Data\Pages\ProfilePageData;
use Splicewire\Beam\Accounts\Data\ProfileDeleteInputData;
use Splicewire\Beam\Accounts\Data\ProfileUpdateInputData;
use Splicewire\Beam\Accounts\Http\Controllers\ProfileController as AccountProfileController;
use Splicewire\Beam\Ux\Support\PageEntryRef;

class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/profile', ProfilePageData::from([
            // The page's beam-ux row, by ID (ADR-0214 §2). Its slug is `settings-profile`, which
            // happens to equal the slash-swapped component name — that coincidence used to be the
            // whole binding, via `componentSlugFallback`. It is written down now (beam-docs-satellite
            // ticket 40): a coincidence is not a binding, and a guess cannot carry a uuid.
            'entry' => PageEntryRef::for('settings-profile'),
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
        ]));
    }

    /**
     * Update the user's profile information.
     */
    #[RequestFromData(ProfileUpdateInputData::class)]
    public function update(Request $request, ProfileUpdateInputData $input): RedirectResponse
    {
        $response = app(AccountProfileController::class)->update($request, $input);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Profile updated.')]);

        return $response;
    }

    /**
     * Delete the user's profile.
     */
    #[RequestFromData(ProfileDeleteInputData::class)]
    public function destroy(Request $request, ProfileDeleteInputData $input): RedirectResponse
    {
        return app(AccountProfileController::class)->destroy($request, $input);
    }
}
