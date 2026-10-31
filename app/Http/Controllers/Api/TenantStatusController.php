<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProvisionTenant;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/** Central endpoints (no tenancy): the SPA polls these while the store database is being built. */
class TenantStatusController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        abort_if(! $tenant, 404);

        return response()->json(['status' => $tenant->status, 'name' => $tenant->name]);
    }

    public function retry(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        abort_if(! $tenant, 404);

        if ($tenant->status !== Tenant::STATUS_FAILED) {
            return response()->json(['message' => 'Only a failed store can be retried.', 'status' => $tenant->status], 409);
        }

        $tenant->status = Tenant::STATUS_PROVISIONING;
        $tenant->save();

        try {
            ProvisionTenant::dispatch($tenant);
        } catch (Throwable $e) {
            report($e);
        }

        return response()->json(['status' => $tenant->fresh()->status, 'name' => $tenant->name]);
    }
}
