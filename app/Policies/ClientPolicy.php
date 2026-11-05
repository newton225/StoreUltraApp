<?php

namespace App\Policies;

use App\Models\Client;
use App\Models\User;

class ClientPolicy
{
    /**
     * Every staff member can list the clients they are allowed to see.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * The office sees every client, a field agent only the ones of their zones.
     */
    public function view(User $user, Client $client): bool
    {
        if (! $user->isRestrictedToZones()) {
            return true;
        }

        return $user->zones()->whereKey($client->zone_id)->exists();
    }

    /**
     * Every staff member can register a client (a field agent only in their zones, see the request).
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Who can see a client can edit its details.
     */
    public function update(User $user, Client $client): bool
    {
        return $this->view($user, $client);
    }
}
