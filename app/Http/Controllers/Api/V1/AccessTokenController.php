<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IssueAccessTokenRequest;
use App\Http\Resources\Api\V1\AccessTokenResource;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Fortify;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * @group Authentication
 *
 * Sign in by exchanging an email and password for a bearer token, then send it on every request
 * as `Authorization: Bearer {token}`. Each device gets its own token, which can be listed and
 * revoked. Accounts with two-factor authentication also need a `code` (or `recovery_code`).
 */
class AccessTokenController extends Controller
{
    /**
     * Sign in
     *
     * Returns a new token for this device. Store it securely; it is only shown once. If the
     * account has two-factor authentication and no code is sent, the response is a 422 with
     * `code: two_factor_required` — ask for the code and send the request again.
     *
     * @unauthenticated
     *
     * @response 201 {"token": "1|Z3f...", "token_type": "Bearer", "user": {"id": 5, "name": "Rita Resident", "email": "resident@propertyflow.test"}}
     * @response 422 scenario="Wrong email or password" {"message": "These credentials do not match our records.", "code": "validation_failed", "errors": {"email": ["These credentials do not match our records."]}}
     * @response 422 scenario="Two-factor code needed" {"message": "Enter the code from your authenticator app.", "code": "two_factor_required", "errors": {"code": ["Enter the code from your authenticator app."]}}
     */
    public function store(IssueAccessTokenRequest $request, TwoFactorAuthenticationProvider $twoFactor): JsonResponse
    {
        $user = User::query()->where('email', $request->string('email')->toString())->first();

        if ($user === null || ! Hash::check($request->string('password')->toString(), $user->password)) {
            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        if ($user->isDeactivated()) {
            throw ValidationException::withMessages(['email' => __('Your account has been deactivated. Contact your company admin.')]);
        }

        if ($user->two_factor_secret !== null && $user->two_factor_confirmed_at !== null && ! $this->passesTwoFactor($request, $user, $twoFactor)) {
            $message = $request->filled('code') || $request->filled('recovery_code')
                ? __('That code is not valid.')
                : __('Enter the code from your authenticator app.');

            return response()->json([
                'message' => $message,
                'code' => $request->filled('code') || $request->filled('recovery_code') ? 'validation_failed' : 'two_factor_required',
                'errors' => ['code' => [$message]],
            ], 422);
        }

        $token = $user->createToken($request->string('device_name')->toString());

        return response()->json([
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'user' => new UserResource($user),
        ], 201);
    }

    /**
     * List your tokens
     *
     * Every device signed in to your account, most recently used first. `current` marks the
     * token making this request.
     *
     * @response {"data": [{"id": 12, "device_name": "Rita's iPhone", "current": true, "last_used_at": "2026-10-02T14:05:00+00:00", "created_at": "2026-09-01T09:00:00+00:00"}, {"id": 7, "device_name": "Old iPad", "current": false, "last_used_at": "2026-06-11T18:30:00+00:00", "created_at": "2026-02-14T10:00:00+00:00"}]}
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();

        return AccessTokenResource::collection(
            $user->tokens()->orderByDesc('last_used_at')->orderByDesc('id')->get(),
        );
    }

    /**
     * Revoke a token
     *
     * Signs a device out. Use `current` as the id to sign out the device making the request.
     *
     * @urlParam token string required A token id from the list, or `current`. Example: current
     *
     * @response 204 scenario="Revoked"
     */
    public function destroy(Request $request, string $token): Response
    {
        /** @var User $user */
        $user = $request->user();

        $query = $user->tokens();

        if ($token === 'current') {
            $query->whereKey(PersonalAccessToken::findToken((string) $request->bearerToken())?->getKey());
        } else {
            $query->whereKey((int) $token);
        }

        $query->firstOrFail()->delete();

        return response()->noContent();
    }

    private function passesTwoFactor(Request $request, User $user, TwoFactorAuthenticationProvider $twoFactor): bool
    {
        $code = $request->string('code')->trim()->toString();

        if ($code !== '') {
            return $twoFactor->verify(Fortify::currentEncrypter()->decrypt((string) $user->two_factor_secret), $code);
        }

        $recoveryCode = $request->string('recovery_code')->trim()->toString();

        if ($recoveryCode !== '' && in_array($recoveryCode, $user->recoveryCodes(), true)) {
            $user->replaceRecoveryCode($recoveryCode);

            return true;
        }

        return false;
    }
}
