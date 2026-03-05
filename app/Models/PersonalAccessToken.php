<?php

namespace App\Models;

use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class PersonalAccessToken extends SanctumPersonalAccessToken
{
    // Ito ang mag-u-utos na lagyan ng UUID automatically ang ID field
    use HasUuids; 
}