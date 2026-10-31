<?php

namespace App\Jobs;

use App\Channels\ChannelException;
use App\Channels\ChannelManager;
use App\Models\Channel;
use App\Models\Product;
use App\Models\ProductChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * One job per (product, channel). Dispatched while a store is initialized, so the queue tenancy
 * bootstrapper re-initializes the same store when the worker runs it. Only ids are queued.
 */
class PublishProductToChannel implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public int $productId, public int $channelId)
    {
        $this->onQueue('publish');
    }

    public function backoff(): array
    {
        return [15, 60];
    }

    public function handle(ChannelManager $channels): void
    {
        $link = ProductChannel::where('product_id', $this->productId)->where('channel_id', $this->channelId)->first();
        $product = Product::find($this->productId);
        $channel = Channel::find($this->channelId);

        // Product or channel was deleted while the job waited: nothing to do.
        if (! $link || ! $product || ! $channel) {
            return;
        }

        try {
            $driver = $channels->driver($channel);

            if ($link->external_id) {
                $driver->updateProduct($channel, $product, $link->external_id);
                $externalId = $link->external_id;
            } else {
                $externalId = $driver->pushProduct($channel, $product);
            }
        } catch (ChannelException $e) {
            if ($e->needsReauth) {
                $channel->update(['status' => Channel::STATUS_NEEDS_REAUTH, 'last_error' => $e->getMessage()]);
            }

            if ($e->retryable && $this->attempts() < $this->tries) {
                $link->update(['sync_status' => ProductChannel::PENDING, 'last_error' => $e->getMessage()]);
                $this->release($this->backoff()[$this->attempts() - 1] ?? 60);

                return;
            }

            $link->update(['sync_status' => ProductChannel::FAILED, 'last_error' => $e->getMessage()]);

            return;
        }

        $link->update([
            'external_id' => $externalId,
            'sync_status' => ProductChannel::SYNCED,
            'last_error' => null,
            'synced_at' => now(),
        ]);
    }

    /** Unexpected crashes must not leave the row "pending" forever. */
    public function failed(\Throwable $e): void
    {
        ProductChannel::where('product_id', $this->productId)->where('channel_id', $this->channelId)
            ->update(['sync_status' => ProductChannel::FAILED, 'last_error' => 'Unexpected error while publishing.']);
    }
}
