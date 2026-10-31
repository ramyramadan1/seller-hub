<?php

namespace App\Models;

use Laravel\Sanctum\PersonalAccessToken as SanctumToken;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/** Tokens live in the central database so they resolve before a tenant is initialized. */
class PersonalAccessToken extends SanctumToken
{
    use CentralConnection;
}
