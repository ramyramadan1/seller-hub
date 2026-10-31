<?php

namespace App\Http\Controllers\Api;

use App\Channels\ChannelException;
use App\Channels\ChannelManager;
use App\Channels\UrlGuard;
use App\Http\Controllers\Controller;
use App\Models\Channel;
use App\Models\ChannelRoute;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Tenant endpoints (SetTenant has initialized the user's own store database).
 * Models are looked up by id inside the action on purpose: route-model binding could run before tenancy starts.
 */
class ChannelController extends Controller
{
    public function __construct(private ChannelManager $channels)
    {
    }

    public function index(): JsonResponse
    {
        return response()->json([
            'data' => Channel::query()->orderBy('id')->get()->map(fn (Channel $c) => $this->present($c))->all(),
            'available_drivers' => $this->channels->available(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'driver' => ['required', 'string'],
            'name' => ['required', 'string', 'max:100'],
        ]);

        if (! $this->channels->supports($data['driver'])) {
            throw ValidationException::withMessages(['driver' => 'This platform is not available yet.']);
        }

        // Phase 2 only ships WooCommerce (key/secret). OAuth platforms get their own flow in phase 7.
        $creds = $request->validate([
            'store_url' => ['required', 'string', 'max:255'],
            'consumer_key' => ['required', 'string', 'max:255'],
            'consumer_secret' => ['required', 'string', 'max:255'],
        ]);

        try {
            $creds['store_url'] = UrlGuard::normalize($creds['store_url']);
        } catch (ChannelException $e) {
            throw ValidationException::withMessages(['store_url' => $e->getMessage()]);
        }

        $externalId = parse_url($creds['store_url'], PHP_URL_HOST);

        if (Channel::where('driver', $data['driver'])->where('external_store_id', $externalId)->exists()) {
            throw ValidationException::withMessages(['store_url' => 'This store is already connected.']);
        }

        $channel = new Channel($data + ['credentials' => $creds, 'external_store_id' => $externalId]);

        // Test BEFORE saving so a wrong key never leaves a half-connected channel behind.
        try {
            $this->channels->driver($channel)->testConnection($channel);
        } catch (ChannelException $e) {
            throw ValidationException::withMessages(['credentials' => $e->getMessage()]);
        }

        $channel->status = Channel::STATUS_CONNECTED;
        $channel->save();

        // Central lookup row for webhooks/OAuth callbacks (ChannelRoute is pinned to the central connection).
        ChannelRoute::create([
            'tenant_id' => tenant('id'),
            'channel_id' => $channel->id,
            'driver' => $channel->driver,
            'external_store_id' => $externalId,
            'webhook_token' => Str::random(48),
        ]);

        return response()->json($this->present($channel->fresh()), 201);
    }

    public function test(int $id): JsonResponse
    {
        $channel = Channel::findOrFail($id);

        try {
            $this->channels->driver($channel)->testConnection($channel);
            $channel->update(['status' => Channel::STATUS_CONNECTED, 'last_error' => null]);
        } catch (ChannelException $e) {
            $channel->update([
                'status' => $e->needsReauth ? Channel::STATUS_NEEDS_REAUTH : Channel::STATUS_ERROR,
                'last_error' => $e->getMessage(),
            ]);
        }

        return response()->json($this->present($channel->fresh()));
    }

    public function destroy(int $id): JsonResponse
    {
        $channel = Channel::findOrFail($id);

        ChannelRoute::where('tenant_id', tenant('id'))->where('channel_id', $channel->id)->delete();
        $channel->delete();

        return response()->json(['deleted' => true]);
    }

    /** Never includes keys or secrets; the store URL is not secret and helps the owner recognize the channel. */
    private function present(Channel $c): array
    {
        return [
            'id' => $c->id,
            'driver' => $c->driver,
            'name' => $c->name,
            'status' => $c->status,
            'last_error' => $c->last_error,
            'store_url' => $c->credentials['store_url'] ?? null,
            'last_synced_at' => $c->last_synced_at?->toIso8601String(),
            'created_at' => $c->created_at?->toIso8601String(),
        ];
    }
}
