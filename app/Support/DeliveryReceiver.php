<?php

namespace App\Support;

use App\Models\Client;
use App\Models\ClientMember;

/**
 * Who normally receives the kits of a client, and who pays for them: its manager, or else the contact person
 * (or the phone) of its record.
 */
class DeliveryReceiver
{
    /**
     * The phone of the manager of a client, or else the phone of its record.
     */
    public static function phoneFor(int $clientId): ?string
    {
        return ClientMember::query()
            ->where('client_id', $clientId)
            ->where('role', ClientMember::ROLE_MANAGER)
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->orderBy('position')
            ->orderBy('id')
            ->value('phone')
            ?? Client::query()->whereKey($clientId)->value('phone');
    }

    public static function for(int $clientId): ?string
    {
        return ClientMember::query()
            ->where('client_id', $clientId)
            ->where('role', ClientMember::ROLE_MANAGER)
            ->orderBy('position')
            ->orderBy('id')
            ->value('name')
            ?? Client::query()->whereKey($clientId)->value('contact_person');
    }
}
