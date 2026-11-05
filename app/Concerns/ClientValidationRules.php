<?php

namespace App\Concerns;

use App\Enums\ClientStatus;
use App\Models\Client;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Database\Query\Builder;
use Illuminate\Validation\Rule;

trait ClientValidationRules
{
    /**
     * Get the validation rules used to validate a client.
     *
     * A new client needs an active zone; an existing one may stay in its current zone even if it was
     * deactivated since. A field agent can only use their own zones and cannot choose the status.
     *
     * @return array<string, array<int, mixed>>
     */
    protected function clientRules(User $user, ?Client $client = null): array
    {
        $zoneRules = [
            'required',
            'integer',
            Rule::exists(Zone::class, 'id')->where(function (Builder $query) use ($client): void {
                $query->where('active', true);

                if ($client !== null) {
                    $query->orWhere('id', $client->zone_id);
                }
            }),
        ];

        if ($user->isRestrictedToZones()) {
            $zoneRules[] = Rule::in($user->zones()->pluck('zones.id')->all());
        }

        $rules = [
            'commercial_number' => ['required', 'string', 'max:50', Rule::unique(Client::class)->ignore($client?->id)],
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'zone_id' => $zoneRules,
            'locality' => ['nullable', 'string', 'max:255'],
            'delivery_address' => ['nullable', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];

        if (! $user->isRestrictedToZones()) {
            $rules['status'] = ['nullable', Rule::enum(ClientStatus::class)];
        }

        return $rules;
    }
}
