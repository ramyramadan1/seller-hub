<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/** Central table: maps a webhook token / OAuth callback to (tenant, channel). Always on the central connection. */
class ChannelRoute extends Model
{
    use CentralConnection;

    protected $fillable = ['tenant_id', 'channel_id', 'driver', 'external_store_id', 'webhook_token'];

    protected $hidden = ['webhook_token'];
}
