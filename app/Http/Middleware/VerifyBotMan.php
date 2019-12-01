<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Log;
use function Psy\debug;

class VerifyBotMan
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        Log::debug($request->toArray());
        if ($request->input('hub.mode') === 'subscribe'
            && $request->input('hub.verify_token') === config('services.facebook.verification'))
        {
            return response($request->input('hub.challenge'), 200);
        }

        return $next($request);
    }
}
