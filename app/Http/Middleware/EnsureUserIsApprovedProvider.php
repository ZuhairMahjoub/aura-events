<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsApprovedProvider
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->hasRole('admin')) {
            return $next($request);
        }

         if (!$user || !$user->providerProfile) {
             return response()->json([
                 'success' => false,
                 'message' => __('auth.provider_profile_incomplete') 
             ], Response::HTTP_FORBIDDEN); 
         }

         if ($user->providerProfile->moderation_status !== 'approved'||  !$user->providerProfile->is_active) {
             return response()->json([
                 'success' => false,
                 'message' => __('auth.provider_not_approved') 
             ], Response::HTTP_FORBIDDEN); 
         }


        return $next($request);
    }
}