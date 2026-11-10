<?php

namespace App\Http\Controllers\Settings;

use App\Actions\Accounts\ScheduleUserDeletion;
use App\Exceptions\DeveloperProfileBlocksDeletionException;
use App\Exceptions\LastWorkspaceOwnerException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileDeleteRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Notifications\EmailChangedNotification;
use App\Support\EndUserSessions;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/profile', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'hasPassword' => $request->user()->hasPassword(),
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request, EndUserSessions $sessions): RedirectResponse
    {
        $user = $request->user();
        $previousEmail = $user->email;
        $previousEmailWasVerified = $user->hasVerifiedEmail();

        $user->fill($request->safe()->only(['name', 'email']));

        $emailChanged = $user->isDirty('email');

        if ($emailChanged) {
            $user->email_verified_at = null;
        }

        $user->save();

        if ($emailChanged) {
            $sessions->others($user, $request->session()->getId());
            $request->session()->regenerate(true);
            $user->sendEmailVerificationNotification();

            if ($previousEmailWasVerified) {
                Notification::route('mail', $previousEmail)->notify(new EmailChangedNotification);
            }
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Profile updated.')]);

        return to_route('profile.edit');
    }

    /**
     * Delete the user's profile.
     */
    public function destroy(ProfileDeleteRequest $request, ScheduleUserDeletion $scheduleDeletion): RedirectResponse
    {
        $user = $request->user();

        try {
            $graceDays = $scheduleDeletion->handle($user);
        } catch (LastWorkspaceOwnerException) {
            return back()->withErrors([
                'account' => __('Transfer ownership or remove the other workspace members before deleting your account.'),
            ]);
        } catch (DeveloperProfileBlocksDeletionException $exception) {
            return back()->withErrors(['account' => $exception->getMessage()]);
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/')->with('status', __('Your account is scheduled for deletion in :days days.', [
            'days' => $graceDays,
        ]));
    }
}
