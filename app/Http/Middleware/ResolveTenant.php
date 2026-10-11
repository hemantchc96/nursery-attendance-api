<?php

namespace App\Http\Middleware;

use App\Attendance\TenantContext;
use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        // The exercise assumes upstream authentication authorizes this header.
        $header = $request->header('X-Tenant-ID');
        $id = is_string($header) && preg_match('/^[1-9][0-9]*$/D', $header)
            ? filter_var($header, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
            : false;
        abort_if($id === false, 400, 'A valid X-Tenant-ID header is required.');
        abort_unless(Tenant::query()->whereKey($id)->exists(), 404, 'Resource not found.');
        $request->attributes->set(TenantContext::class, new TenantContext($id));

        return $next($request);
    }
}
