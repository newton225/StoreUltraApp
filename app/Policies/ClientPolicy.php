<?php

namespace App\Policies;

use App\Models\Client;
use App\Models\User;

class ClientPolicy
{
    /**
     * The clients menu opens the list; the user then only sees the clients they are allowed to.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('manage-clients');
    }

    /**
     * The office sees every client, a field agent only the ones of their zones.
     */
    public function view(User $user, Client $client): bool
    {
        if (! $this->viewAny($user)) {
            return false;
        }

        return $user->reachesClient($client);
    }

    /**
     * Whoever holds the action registers a client (a field agent only in their zones, see the request).
     */
    public function create(User $user): bool
    {
        return $this->viewAny($user) && $user->can('clients.create');
    }

    /**
     * Who can see a client and holds the action can edit its details.
     */
    public function update(User $user, Client $client): bool
    {
        return $this->view($user, $client) && $user->can('clients.update');
    }
}
