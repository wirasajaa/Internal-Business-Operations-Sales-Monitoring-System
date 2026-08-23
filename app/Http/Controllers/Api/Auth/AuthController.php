<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    private const REFRESH_COOKIE = 'refresh_token';

    private const REFRESH_TTL_DAYS = 14;

    private const ACCESS_TTL_MINUTES = 60;

    public function login(LoginRequest $request)
    {
        $user = User::where('email', $request->string('email'))->first();

        if (! $user || ! Hash::check($request->string('password'), $user->password)) {
            return response()->json(['message' => 'Kredensial tidak valid.'], 401);
        }

        if (! $user->is_active) {
            return response()->json(['message' => 'Akun tidak aktif.'], 403);
        }

        return $this->issueTokens($request, $user);
    }

    public function refresh(Request $request)
    {
        $plain = $request->cookie(self::REFRESH_COOKIE);

        if (! $plain) {
            return response()->json(['message' => 'Refresh token tidak ditemukan.'], 401);
        }

        $hash = hash('sha256', $plain);
        $record = RefreshToken::where('token_hash', $hash)->first();

        if (! $record || ! $record->isValid()) {
            return response()->json(['message' => 'Refresh token tidak valid.'], 401);
        }

        $record->update(['revoked_at' => now()]);

        return $this->issueTokens($request, $record->user);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        $plain = $request->cookie(self::REFRESH_COOKIE);
        if ($plain) {
            RefreshToken::where('token_hash', hash('sha256', $plain))->update(['revoked_at' => now()]);
        }

        return response()->json(['message' => 'Berhasil logout.'])
            ->withCookie(Cookie::forget(self::REFRESH_COOKIE));
    }

    public function me(Request $request)
    {
        $user = $request->user()->load('roles', 'permissions');

        return response()->json([
            'user' => $user->only('id', 'name', 'email', 'is_active'),
            'roles' => $user->getRoleNames(),
            'permissions' => $user->getAllPermissions()->pluck('name'),
        ]);
    }

    private function issueTokens(Request $request, User $user)
    {
        $accessToken = $user->createToken(
            'access-token',
            ['*'],
            now()->addMinutes(self::ACCESS_TTL_MINUTES)
        )->plainTextToken;

        $refreshPlain = Str::random(64);

        RefreshToken::create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $refreshPlain),
            'expires_at' => now()->addDays(self::REFRESH_TTL_DAYS),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        $cookie = Cookie::make(
            self::REFRESH_COOKIE,
            $refreshPlain,
            self::REFRESH_TTL_DAYS * 24 * 60,
            path: '/',
            domain: null,
            secure: ! app()->environment('local'),
            httpOnly: true,
            sameSite: 'Lax'
        );

        return response()->json([
            'access_token' => $accessToken,
            'token_type' => 'Bearer',
            'expires_at' => now()->addMinutes(self::ACCESS_TTL_MINUTES)->toIso8601String(),
            'user' => $user->only('id', 'name', 'email', 'is_active'),
            'roles' => $user->getRoleNames(),
            'permissions' => $user->getAllPermissions()->pluck('name'),
        ])->withCookie($cookie);
    }
}
