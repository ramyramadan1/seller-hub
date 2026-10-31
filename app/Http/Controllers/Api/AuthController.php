<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TenantResource;
use App\Http\Resources\UserResource;
use App\Jobs\ProvisionTenant;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Services\CreditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rules\Password;
use Throwable;

/** Central endpoints: tenancy is NOT initialized here. */
class AuthController extends Controller
{
    public function register(Request $request, CreditService $credits): JsonResponse
    {
        $data = $request->validate([
            'store_name' => ['required', 'string', 'max:120'],
            'currency' => ['required', Rule::in(config('seller.currencies'))],
            'locale' => ['required', Rule::in(config('seller.locales'))],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:190', Rule::unique(User::class, 'email')],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        // 1) One central transaction: store + owner + trial credits.
        [$tenant, $user] = DB::connection(config('tenancy.database.central_connection'))->transaction(function () use ($data, $credits) {
            $tenant = Tenant::create([
                'name' => $data['store_name'],
                'slug' => $this->uniqueSlug($data['store_name']),
                'currency' => $data['currency'],
                'locale' => $data['locale'],
                'plan_id' => Plan::query()->where('slug', 'free')->value('id'),
                'status' => Tenant::STATUS_PROVISIONING,
                'ai_source_default' => 'own',
            ]);

            $user = User::create([
                'tenant_id' => $tenant->getTenantKey(),
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'role' => User::ROLE_OWNER,
            ]);

            $credits->grantBonus($tenant, (int) config('seller.trial_credits'));

            return [$tenant, $user];
        });

        // 2) Provision the store database in the background (runs inline when QUEUE_CONNECTION=sync).
        try {
            ProvisionTenant::dispatch($tenant);
        } catch (Throwable $e) {
            report($e); // the job already marked the tenant "failed"; the owner can retry from the UI
        }

        return response()->json([
            'token' => $user->createToken('spa')->plainTextToken,
            'user' => new UserResource($user),
            'tenant' => new TenantResource($tenant->fresh()),
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::query()->where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['email' => ['These credentials do not match our records.']]);
        }

        return response()->json([
            'token' => $user->createToken('spa')->plainTextToken,
            'user' => new UserResource($user),
            'tenant' => new TenantResource($user->tenant),
        ]);
    }

    public function me(Request $request, CreditService $credits): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'user' => new UserResource($user),
            'tenant' => new TenantResource($user->tenant),
            'credits_balance' => $user->tenant ? $credits->balance($user->tenant) : 0,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out.']);
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'store';
        $slug = $base;

        while (Tenant::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.Str::lower(Str::random(5));
        }

        return $slug;
    }
}
