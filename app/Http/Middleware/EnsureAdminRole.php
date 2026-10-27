<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminRole
{
    public function handle(Request $request, Closure $next, string $minimumRole = 'moderator'): Response
    {
        $user = $request->user();

        if (!$user || !$user->is_active) {
            abort(403, 'Your admin account is inactive or unauthorized.');
        }

        $allowed = match ($minimumRole) {
            'super_admin' => $user->isSuperAdmin(),
            'admin' => $user->isAdmin(),
            default => $user->canModerate(),
        };

        abort_unless($allowed, 403, 'Insufficient role privileges.');

        return $next($request);
    }
}
