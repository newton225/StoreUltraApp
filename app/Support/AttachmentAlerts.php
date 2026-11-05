<?php

namespace App\Support;

use App\Enums\ClientStatus;
use App\Models\Attachment;
use App\Models\AttachmentType;
use App\Models\Client;
use App\Models\Shop;

/**
 * What needs attention among the administrative documents: required ones that are missing, and
 * those that have expired or are about to. Only the latest document of a type for an owner counts,
 * so a renewal clears the alert of the old one.
 */
class AttachmentAlerts
{
    public function __construct(private BusinessSettings $settings) {}

    /**
     * @return array{
     *     missing: list<array{type_id: int, owner_id: int, type: string, kind: string, owner: string}>,
     *     expiring: list<array{id: int, type_id: int, owner_id: int, type: string, kind: string, owner: string, number: string, expires_on: string, days: int}>,
     *     expired: list<array{id: int, type_id: int, owner_id: int, type: string, kind: string, owner: string, number: string, expires_on: string, days: int}>
     * }
     */
    public function all(): array
    {
        $owners = $this->owners();
        $types = AttachmentType::query()->where('active', true)->get()->keyBy('id');
        $latest = $this->latestByTypeAndOwner();
        $horizon = today()->addDays((int) $this->settings->get('attachment_alert_days'));

        $missing = [];

        foreach ($types as $type) {
            if (! $type->required) {
                continue;
            }

            foreach ($owners[$type->applies_to] as $ownerId => $label) {
                if (! isset($latest[$type->id.':'.$ownerId])) {
                    $missing[] = ['type_id' => $type->id, 'owner_id' => $ownerId, 'type' => $type->name, 'kind' => $type->applies_to, 'owner' => $label];
                }
            }
        }

        $expiring = [];
        $expired = [];

        foreach ($latest as $attachment) {
            $type = $types->get($attachment->attachment_type_id);

            if ($type === null || $attachment->expires_on === null || $attachment->expires_on->greaterThan($horizon)) {
                continue;
            }

            $row = [
                'id' => $attachment->id,
                'type_id' => $type->id,
                'owner_id' => $attachment->owner_id ?? 0,
                'type' => $type->name,
                'kind' => $type->applies_to,
                'owner' => $owners[$type->applies_to][$attachment->owner_id ?? 0] ?? '—',
                'number' => $attachment->number,
                'expires_on' => $attachment->expires_on->toDateString(),
                'days' => (int) today()->diffInDays($attachment->expires_on, false),
            ];

            if ($attachment->expires_on->lessThan(today())) {
                $expired[] = $row;
            } else {
                $expiring[] = $row;
            }
        }

        return ['missing' => $missing, 'expiring' => $expiring, 'expired' => $expired];
    }

    /**
     * The owners each kind of document can be attached to, as id => label. The company has a single
     * owner with id 0.
     *
     * @return array{company: array<int, string>, shop: array<int, string>, client: array<int, string>}
     */
    public function owners(): array
    {
        $shops = [];

        foreach (Shop::query()->where('active', true)->orderBy('name')->get(['id', 'name']) as $shop) {
            $shops[$shop->id] = $shop->name;
        }

        $clients = [];

        foreach (Client::query()->where('status', '!=', ClientStatus::Ended->value)->orderBy('name')->get(['id', 'name']) as $client) {
            $clients[$client->id] = $client->name;
        }

        return [
            'company' => [0 => (string) ($this->settings->get('company_name') ?: __('attachments.company_owner'))],
            'shop' => $shops,
            'client' => $clients,
        ];
    }

    /**
     * The document with the latest expiry (or the newest one when none expires) for each type and owner.
     *
     * @return array<string, Attachment>
     */
    private function latestByTypeAndOwner(): array
    {
        $latest = [];

        foreach (Attachment::query()->orderBy('id')->get() as $attachment) {
            $key = $attachment->attachment_type_id.':'.($attachment->owner_id ?? 0);
            $current = $latest[$key] ?? null;

            if ($current === null
                || $attachment->expires_on === null
                || ($current->expires_on !== null && $attachment->expires_on->greaterThanOrEqualTo($current->expires_on))) {
                $latest[$key] = $attachment;
            }
        }

        return $latest;
    }
}
