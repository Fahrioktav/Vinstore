<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    static $defaultRoutesByRole = [
        'user' => '/',
        'seller' => '/seller/dashboard',
        'admin' => '/admin/dashboard'
    ];

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        // Jika guest (belum login) dan middleware tidak mengandung route:guestOnly
        // suruh guest untuk login
        if (!Auth::check()) {
            if (in_array('guestOnly', $roles)) {
                return $next($request);
            } else {
                return redirect('/login');
            }
        }

        // Jika user yang telah login mengakses route yang tidak sesuai role
        // arahkan ke route default
        $role = Auth::user()->role;

        if (!in_array($role, $roles)) {
            return redirect(self::$defaultRoutesByRole[$role] ?? '/');
        }

        return $next($request);
    }
}
