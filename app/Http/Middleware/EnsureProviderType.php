<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;


class EnsureProviderType
{
    public function handle(Request $request, Closure $next, string $type): Response
    {
        $provider = $request->user()?->providerProfile;

        $actualType = $provider ? trim(strtolower($provider->provider_type ?? '')) : null;

        if ($actualType !== trim(strtolower($type))) {
            return response()->json([
                'success' => false,
                'message' => $type === 'company'
                    ? 'عذراً، هذا الإجراء متاح فقط لحسابات الشركات.'
                    : 'يجب أن يكون حسابك من نوع فريلانسر للقيام بهذا الإجراء.',
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}