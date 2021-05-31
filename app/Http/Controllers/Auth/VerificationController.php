<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserPermission;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Events\User\UserActivated;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * Email verification controller.
 */
class VerificationController extends Controller
{
    /**
     * Show the email verification notice.
     *
     * @param Request $request the incoming request
     *
     * @return mixed the view for verification or invitation notice (based on user status)
     *               or a redirect if user is already verified
     */
    public function show(Request $request)
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return $this->alreadyVerifiedUser($request, $user);
        }

        return view('auth.verify')->with('user', $user);
    }

    /**
     * Mark the authenticated user's email address as verified.
     *
     * @param Request $request the incoming request
     * @param string $uuid the uuid of the user to be verified
     * @param string $hash the hash of the user email address
     *
     * @throws \Illuminate\Contracts\Container\BindingResolutionException if unable to bind to SPID service
     * @throws \Illuminate\Auth\Access\AuthorizationException if verification link is invalid
     *
     * @return RedirectResponse the server redirect response
     */
    public function verify(Request $request, string $uuid, string $hash): RedirectResponse
    {
        $user = User::where('uuid', $uuid)->first();
        $authUser = $request->user();

        if (!$authUser->is($user) || !Hash::check($authUser->email, base64_decode($hash, true))) {
            throw new AuthorizationException("L'utente corrente non corrisponde alla richiesta di verifica.");
        }

        if ($user->hasVerifiedEmail()) {
            return $this->alreadyVerifiedUser($request, $user);
        }

        if ($this->verifyUser($user)) {
            event(new Verified($user));
        }

        $redirectTo = $user->can(UserPermission::ACCESS_ADMIN_AREA) ? route('admin.dashboard') : route('analytics');

        return redirect($redirectTo)->withModal([
            'title' => __('Indirizzo email confermato'),
            'icon' => 'it-check-circle',
            'message' => implode("\n", [
                __('Hai appena confermato il tuo indirizzo email :email.', ['email' => '<strong>' . e($user->email) . '</strong>']),
                __('Da adesso puoi iniziare a usare :app.', ['app' => config('app.name')]),
            ]),
        ]);
    }

    /**
     * Resend the email verification notification.
     *
     * @param Request $request the incoming request
     * @param User|null $user the user to resend the notification to
     *
     * @return RedirectResponse|\Illuminate\Http\JsonResponse the response
     */
    public function resend(Request $request, ?User $user = null)
    {
        $user = $user ?? $request->user();

        if ($user->hasVerifiedEmail()) {
            return $this->alreadyVerifiedUser($request, $user);
        }

        $user->sendEmailVerificationNotification($user->status->is(UserStatus::INVITED) ? $user->publicAdministrations()->first() : null);

        return $request->expectsJson()
            ? response()->json([
                'result' => 'ok',
                'id' => $user->uuid,
                'email' => $user->email,
            ])
            : back()->withNotification([
                'title' => __('verifica indirizzo email'),
                'message' => __("Una nuova email di verifica è stata inviata all'indirizzo :email.", ['email' => '<strong>' . e($user->email) . '</strong>']),
                'status' => 'success',
                'icon' => 'it-check-circle',
            ]);
    }

    /**
     * Redirect an already verified user.
     *
     * @param User $user the user
     * @param Request $request the current request
     *
     * @return RedirectResponse|\Illuminate\Http\JsonResponse the response
     */
    protected function alreadyVerifiedUser(Request $request, User $user)
    {
        return $request->expectsJson()
            ? response()->json(null, 303)
            : redirect()->home()->withNotification([
                'title' => __('verifica indirizzo email'),
                'message' => __("L'indirizzo email :email è già stato verificato dall'utente.", ['email' => '<strong>' . e($user->email) . '</strong>']),
                'status' => 'warning',
                'icon' => 'it-warning-circle',
            ]);
    }

    /**
     * Update a verified user.
     *
     * @param User $user the user
     *
     * @throws \Illuminate\Contracts\Container\BindingResolutionException if unable to bind to SPID service
     *
     * @return bool true if user is successfully updated, false otherwise
     */
    protected function verifyUser(User $user): bool
    {
        if ($user->status->is(UserStatus::INACTIVE)) {
            $newStatus = UserStatus::PENDING;
            $user->assign(UserRole::REGISTERED);
        }

        if ($user->status->is(UserStatus::INVITED)) {
            $newStatus = UserStatus::ACTIVE;

            if ($user->isNotAn(UserRole::SUPER_ADMIN)) {
                $SPIDUser = session()->get('spid_user');

                if ($user->fiscal_number !== $SPIDUser->fiscalNumber) {
                    session()->flash('notification', [
                        'title' => __('accesso negato'),
                        'message' => __("L'utente non corrisponde all'invito."),
                        'status' => 'error',
                        'icon' => 'it-close-circle',
                    ]);

                    return app()->make('SPIDAuth')->logout();
                }
                $user->fill([
                    'spid_code' => $SPIDUser->spidCode,
                    'name' => $SPIDUser->name,
                    'family_name' => $SPIDUser->familyName,
                ]);
                $newStatus = UserStatus::ACTIVE;

                event(new UserActivated($user, $user->publicAdministrations()->first()));
            }
        }

        $user->status = $newStatus ?? $user->status->value;
        $user->email_verified_at = $user->freshTimestamp();

        return $user->save();
    }
}
