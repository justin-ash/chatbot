<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(): View
    {
        return view('admin.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $throttleKey = 'admin-login:' . strtolower($credentials['email']) . '|' . $request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            throw ValidationException::withMessages([
                'email' => "Too many login attempts. Please try again in {$seconds} seconds.",
            ]);
        }

        if (!Auth::attempt(['email' => $credentials['email'], 'password' => $credentials['password'], 'is_active' => true], $request->boolean('remember'))) {
            RateLimiter::hit($throttleKey, 60);
            throw ValidationException::withMessages([
                'email' => 'Invalid credentials or inactive administrator account.',
            ]);
        }

        RateLimiter::clear($throttleKey);
        $request->session()->regenerate();

        AuditLog::record(
            action: 'admin.login',
            targetType: 'web_user',
            targetId: (int) Auth::id(),
            reason: 'Admin web session authenticated',
            metadata: ['ip' => $request->ip()],
            actorAdminId: (int) Auth::id()
        );

        return redirect()->intended(route('admin.dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        $adminId = Auth::id();
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($adminId) {
            AuditLog::record(
                action: 'admin.logout',
                targetType: 'web_user',
                targetId: (int) $adminId,
                reason: 'Admin signed out',
                actorAdminId: (int) $adminId
            );
        }

        return redirect()->route('admin.login');
    }
}
