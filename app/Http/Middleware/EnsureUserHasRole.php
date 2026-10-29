<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string $role = 'staff'): Response
    {
        $user = $request->user();
        if (! $user || ! $user->is_active) {
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return redirect()->route('login')->withErrors(['email' => 'บัญชีนี้ถูกระงับหรือเซสชันหมดอายุ']);
        }
        if ($role === 'admin' && ! $user->isAdmin()) {
            abort(403, 'ไม่มีสิทธิ์ดำเนินการ');
        }
        return $next($request);
    }
}
