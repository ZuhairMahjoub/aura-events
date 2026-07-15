<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\App;

class SetLocale
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
   public function handle(Request $request, Closure $next)
    {
        // سحب اللغة من الهيدر، افتراضياً 'ar'
        $locale = $request->header('Accept-Language', 'ar');

        if (!in_array($locale, ['ar', 'en'])) {
            $locale = 'ar';
        }

        App::setLocale($locale);

        return $next($request);
    }
}
