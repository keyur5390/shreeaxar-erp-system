<?php

namespace App\Http\Controllers\API;

use App\Jobs\CreateAuditLog;
use App\Jobs\SendOtpJob;
use App\Models\OtpRecord;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rules\Password;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    private const DEFAULT_TOKEN_MINUTES = 480;
    private const REMEMBER_TOKEN_MINUTES = 43200;

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
            'remember_me' => ['sometimes', 'boolean'],
        ]);

        $user = User::query()->where('email', $credentials['email'])->first();

        if (! $user) {
            return response()->json(['message' => 'Invalid email or password'], 401);
        }

        if (! $user->is_active) {
            return response()->json(['message' => 'Account deactivated. Contact your administrator.'], 401);
        }

        if (! Hash::check($credentials['password'], $user->password)) {
            usleep(100000);

            return response()->json(['message' => 'Invalid email or password'], 401);
        }

        $minutes = $request->boolean('remember_me') ? self::REMEMBER_TOKEN_MINUTES : self::DEFAULT_TOKEN_MINUTES;
        $token = $this->createToken($user, $minutes);

        $this->audit($request, $user, 'LOGIN');

        return response()->json([
            'success' => true,
            'data' => [
                'token' => $token,
                'expires_in' => $minutes * 60,
                'user' => $this->userPayload($user),
            ],
        ]);
    }

    public function refresh(Request $request): JsonResponse
    {
        $user = $request->user();
        $currentToken = $user->currentAccessToken();
        $expiresAt = $currentToken?->expires_at;
        $minutes = $expiresAt ? max(1, now()->diffInMinutes($expiresAt, false)) : self::DEFAULT_TOKEN_MINUTES;

        $currentToken?->delete();

        return response()->json([
            'token' => $this->createToken($user, $minutes),
            'expires_in' => $minutes * 60,
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load('roles');

        return response()->json([
            'user' => $this->userPayload($user),
            'permissions' => $this->permissionMap($user),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($request->boolean('all_devices')) {
            $user->tokens()->delete();
            $user->increment('token_version');
        } else {
            $user->currentAccessToken()?->delete();
        }

        $this->audit($request, $user, 'LOGOUT');

        return response()->json(['message' => 'Logged out successfully']);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => ['required'],
            'new_password' => ['required', 'min:8', 'confirmed'],
            'new_password_confirmation' => ['required'],
        ]);

        $user = $request->user();

        if (! Hash::check($validated['current_password'], $user->password)) {
            return response()->json(['message' => 'Current password is incorrect.'], 400);
        }

        if ($validated['current_password'] === $validated['new_password']) {
            return response()->json(['message' => 'New password must be different.'], 400);
        }

        $user->forceFill(['password' => Hash::make($validated['new_password'])])->save();
        $user->tokens()->delete();
        $user->increment('token_version');
        $this->audit($request, $user, 'CHANGE_PASSWORD');

        return response()->json(['message' => 'Password changed. You have been logged out of all devices.']);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $validated = $request->validate(['email' => ['required', 'email']]);
        $user = User::query()->where('email', $validated['email'])->first();

        if ($user?->is_active) {
            OtpRecord::query()->where('email', $validated['email'])->where('is_used', false)->delete();
            $otp = (string) random_int(100000, 999999);
            OtpRecord::query()->create([
                'email' => $validated['email'],
                'otp_hash' => Hash::make($otp),
                'expires_at' => now()->addMinutes((int) config('quotation.otp_expiry_minutes', 15)),
                'is_used' => false,
            ]);
            SendOtpJob::dispatch($validated['email'], $otp);
        }

        return response()->json(['message' => 'If the email exists, an OTP has been sent.']);
    }

    public function verifyOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'otp' => ['required', 'digits:6'],
        ]);

        $record = OtpRecord::query()
            ->where('email', $validated['email'])
            ->where('is_used', false)
            ->where('expires_at', '>', now())
            ->latest('created_at')
            ->first();

        if (! $record || ! Hash::check($validated['otp'], $record->otp_hash)) {
            return response()->json(['message' => 'OTP is invalid or has expired.'], 400);
        }

        $record->forceFill(['is_used' => true])->save();

        return response()->json([
            'reset_token' => URL::temporarySignedRoute('password.reset.validate', now()->addMinutes(30), [
                'email' => $validated['email'],
            ]),
        ]);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'reset_token' => ['required', 'url'],
            'new_password' => ['required', Password::min(8), 'confirmed'],
            'new_password_confirmation' => ['required'],
        ]);

        $signatureRequest = Request::create($validated['reset_token']);
        if (! URL::hasValidSignature($signatureRequest)) {
            return response()->json(['message' => 'Reset token is invalid or has expired.'], 400);
        }

        $email = $signatureRequest->query('email');
        $user = is_string($email) ? User::query()->where('email', $email)->first() : null;
        if (! $user) {
            return response()->json(['message' => 'Reset token is invalid or has expired.'], 400);
        }

        $user->forceFill(['password' => Hash::make($validated['new_password'])])->save();
        $user->tokens()->delete();
        $user->increment('token_version');

        return response()->json(['message' => 'Password reset. Please log in.']);
    }

    public function validateResetToken(Request $request): JsonResponse
    {
        abort_unless($request->hasValidSignature(), 403);

        return response()->json(['valid' => true]);
    }

    private function createToken(User $user, int $minutes): string
    {
        $plainTextToken = $user->createToken('auth_token', ['token_version:'.$user->token_version])->plainTextToken;
        [$id] = explode('|', $plainTextToken, 2);
        PersonalAccessToken::query()->whereKey($id)->update(['expires_at' => now()->addMinutes($minutes)]);

        return $plainTextToken;
    }

    private function userPayload(User $user): array
    {
        $user->loadMissing('roles');

        $roles = $user->roles->map(fn ($role): array => [
            'id' => $role->id,
            'name' => $role->name,
        ])->values()->all();

        return [
            'id' => $user->id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'profile_image' => $user->profile_image,
            'is_active' => $user->is_active,
            'roles' => $roles,
            'role' => ['name' => $roles[0]['name'] ?? null],
        ];
    }

    private function permissionMap(User $user): array
    {
        return $user->getAllPermissions()->mapWithKeys(fn ($permission): array => [$permission->name => true])->all();
    }

    private function audit(Request $request, User $user, string $action): void
    {
        CreateAuditLog::dispatch([
            'user_id' => $user->id,
            'user_email' => $user->email,
            'action' => $action,
            'module' => 'auth',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }
}
