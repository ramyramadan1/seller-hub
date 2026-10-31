<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Runs after auth:sanctum. The store is ALWAYS taken from the authenticated user,
 * never from the request body, headers or URL.
 */
class SetTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $request->user()?->tenant;

        if (! $tenant) {
            return response()->json(['message' => 'No store is linked to this account.'], 403);
        }

        if ($tenant->status === Tenant::STATUS_SUSPENDED) {
            return response()->json(['message' => 'This store is suspended.', 'status' => $tenant->status], 403);
        }

        if (! $tenant->isActive()) {
            return response()->json(['message' => 'Your store is not ready yet.', 'status' => $tenant->status], 409);
        }

        tenancy()->initialize($tenant);

        try {
            return $next($request);
        } finally {
            // Reset state so long-lived workers (Octane etc.) never leak a tenant into the next request.
            tenancy()->end();
        }
    }
}
