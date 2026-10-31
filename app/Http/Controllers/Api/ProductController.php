<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\PublishProductToChannel;
use App\Models\Channel;
use App\Models\Product;
use App\Models\ProductChannel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Tenant endpoints: the default connection is the store's own database (SetTenant middleware).
 * Models are looked up by id inside actions: route-model binding could run before tenancy starts.
 */
class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Product::query()->with('channelLinks')->latest('id');

        if ($search = trim((string) $request->query('search'))) {
            $query->where(fn ($q) => $q->where('title', 'like', "%{$search}%")->orWhere('sku', 'like', "%{$search}%"));
        }

        $page = $query->paginate(min((int) $request->query('per_page', 20), 100));
        $page->getCollection()->transform(fn (Product $p) => $this->present($p));

        return response()->json($page);
    }

    public function show(int $id): JsonResponse
    {
        return response()->json($this->present(Product::with('channelLinks')->findOrFail($id)));
    }

    public function store(Request $request): JsonResponse
    {
        $product = Product::create($this->validated($request) + ['stock' => 0]);

        return response()->json($this->present($product->fresh('channelLinks')), 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $product = Product::findOrFail($id);
        $product->update($this->validated($request, $product->id));

        return response()->json($this->present($product->fresh('channelLinks')));
    }

    public function destroy(int $id): JsonResponse
    {
        // Removing a product here does not delete it on the sales channels.
        Product::findOrFail($id)->delete();

        return response()->json(['deleted' => true]);
    }

    /** Publish (or re-publish) products to channels: one queued job per product and channel. */
    public function publish(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_ids' => ['required', 'array', 'min:1', 'max:100'],
            'product_ids.*' => ['integer', 'distinct', Rule::exists('products', 'id')],
            'channel_ids' => ['required', 'array', 'min:1', 'max:10'],
            'channel_ids.*' => ['integer', 'distinct', Rule::exists('channels', 'id')],
        ]);

        $channels = Channel::whereIn('id', $data['channel_ids'])->get();

        if ($channels->contains(fn (Channel $c) => $c->status !== Channel::STATUS_CONNECTED)) {
            return response()->json(['message' => 'Reconnect the channels that are not connected before publishing.'], 422);
        }

        $queued = 0;
        foreach ($data['product_ids'] as $productId) {
            foreach ($channels as $channel) {
                ProductChannel::updateOrCreate(
                    ['product_id' => $productId, 'channel_id' => $channel->id],
                    ['sync_status' => ProductChannel::PENDING, 'last_error' => null],
                );
                PublishProductToChannel::dispatch($productId, $channel->id);
                $queued++;
            }
        }

        return response()->json(['queued' => $queued], 202);
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:100', Rule::unique('products', 'sku')->ignore($ignoreId)],
            'description' => ['nullable', 'string', 'max:20000'],
            'price' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'cost_price' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'stock' => ['nullable', 'integer', 'min:0', 'max:10000000'],
        ]);
    }

    private function present(Product $p): array
    {
        $out = $p->makeHidden('channelLinks')->toArray();
        $out['channels'] = $p->channelLinks->map(fn (ProductChannel $l) => [
            'channel_id' => $l->channel_id,
            'sync_status' => $l->sync_status,
            'last_error' => $l->last_error,
            'synced_at' => $l->synced_at?->toIso8601String(),
        ])->values()->all();

        return $out;
    }
}
