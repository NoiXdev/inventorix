<?php

namespace App\Http\Middleware;

use App\Support\ApplySettings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApplyRuntimeSettings
{
    public function __construct(protected ApplySettings $applySettings) {}

    public function handle(Request $request, Closure $next): Response
    {
        ($this->applySettings)();

        return $next($request);
    }
}
