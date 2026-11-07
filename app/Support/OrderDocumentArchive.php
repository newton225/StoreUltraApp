<?php

namespace App\Support;

use App\Enums\DocumentType;
use App\Enums\OrderStatus;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

/**
 * The proformas and invoices already issued for orders, found without opening each order, and packed in one zip.
 *
 * Nothing is generated here: only the valid (not cancelled) documents that exist are read, and an order the user
 * may not see is never included.
 */
class OrderDocumentArchive
{
    /** One zip never holds more files than this: each is read from the disk and the user waits for the download. */
    public const MAX_FILES = 200;

    /** How many delivery weeks the download-by-zone form offers. */
    public const WEEKS = 26;

    /**
     * The valid proforma and invoice of each order, to show a download link on every row of a list.
     *
     * @param  array<int, int>  $orderIds
     * @return array{proforma: array<int, int>, invoice: array<int, int>}
     */
    public function idsByOrder(array $orderIds): array
    {
        if ($orderIds === []) {
            return ['proforma' => [], 'invoice' => []];
        }

        $proformas = Document::query()
            ->where('type', DocumentType::Proforma->value)
            ->where('subject_type', (new Order)->getMorphClass())
            ->whereIn('subject_id', $orderIds)
            ->whereNull('cancelled_at')
            ->orderBy('id')
            ->pluck('id', 'subject_id');

        $invoices = Document::query()
            ->where('documents.type', DocumentType::Invoice->value)
            ->where('documents.subject_type', (new Invoice)->getMorphClass())
            ->whereNull('documents.cancelled_at')
            ->join('invoices', 'invoices.id', '=', 'documents.subject_id')
            ->whereIn('invoices.order_id', $orderIds)
            ->orderBy('documents.id')
            ->pluck('documents.id', 'invoices.order_id');

        return [
            'proforma' => array_map('intval', $proformas->all()),
            'invoice' => array_map('intval', $invoices->all()),
        ];
    }

    /**
     * The valid documents of a kind for the chosen orders that the user may see.
     *
     * @param  array<int, int>  $orderIds
     * @return Collection<int, Document>
     */
    public function forOrders(array $orderIds, DocumentType $type, User $user): Collection
    {
        $visible = Order::query()->visibleTo($user)->whereIn('id', $orderIds)->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();

        return $this->documents($visible, $type);
    }

    /**
     * The valid documents of a kind for the orders of one zone (sub-prefecture) delivered in one week.
     *
     * @return Collection<int, Document>
     */
    public function forZone(int $zoneId, CarbonInterface $week, DocumentType $type, User $user): Collection
    {
        $ids = Order::query()
            ->visibleTo($user)
            ->whereDate('delivery_week', $week)
            ->whereHas('client', fn (Builder $clients) => $clients->where('zone_id', $zoneId))
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        return $this->documents($ids, $type);
    }

    /**
     * What the download-by-zone form offers: the delivery weeks that have orders (newest first, at most
     * {@see self::WEEKS}) and, for each zone, how many documents of each kind are ready to download and how many
     * are still to be generated, week by week. A proforma is due for every live order, an invoice once it is delivered.
     *
     * @return array{weeks: list<string>, zones: list<array{id: int, name: string, counts: array<string, array{proforma: array{ready: int, missing: int}, invoice: array{ready: int, missing: int}}>}>}
     */
    public function choices(User $user): array
    {
        $weeks = Order::query()
            ->visibleTo($user)
            ->where('status', '!=', OrderStatus::Cancelled->value)
            ->orderByDesc('delivery_week')
            ->distinct()
            ->limit(self::WEEKS)
            ->pluck('delivery_week')
            ->map(fn (mixed $week): string => CarbonImmutable::parse($week)->toDateString())
            ->values()
            ->all();

        if ($weeks === []) {
            return ['weeks' => [], 'zones' => []];
        }

        $orders = Order::query()
            ->visibleTo($user)
            ->join('clients', 'clients.id', '=', 'orders.client_id')
            ->join('zones', 'zones.id', '=', 'clients.zone_id')
            ->where('orders.status', '!=', OrderStatus::Cancelled->value)
            ->whereDate('orders.delivery_week', '>=', $weeks[count($weeks) - 1])
            ->get(['orders.id', 'orders.status', 'orders.delivery_week', 'zones.id as zone_id', 'zones.name as zone_name']);

        $documents = $this->idsByOrder($orders->pluck('id')->map(fn (mixed $id): int => (int) $id)->all());
        $zones = [];

        foreach ($orders as $order) {
            $zoneId = (int) $order->getAttribute('zone_id');
            $week = CarbonImmutable::parse($order->getAttribute('delivery_week'))->toDateString();
            $zones[$zoneId]['name'] = (string) $order->getAttribute('zone_name');
            $zones[$zoneId]['counts'][$week] ??= ['proforma' => ['ready' => 0, 'missing' => 0], 'invoice' => ['ready' => 0, 'missing' => 0]];

            $id = (int) $order->getKey();
            $kinds = ['proforma' => true, 'invoice' => in_array($order->status, [OrderStatus::Delivered, OrderStatus::Settled, OrderStatus::Closed], true)];

            foreach ($kinds as $kind => $due) {
                if (isset($documents[$kind][$id])) {
                    $zones[$zoneId]['counts'][$week][$kind]['ready']++;
                } elseif ($due) {
                    $zones[$zoneId]['counts'][$week][$kind]['missing']++;
                }
            }
        }

        $list = [];

        foreach ($zones as $zoneId => $zone) {
            $list[] = ['id' => $zoneId, 'name' => $zone['name'], 'counts' => $zone['counts']];
        }

        usort($list, fn (array $a, array $b): int => strcmp($a['name'], $b['name']));

        return ['weeks' => array_values($weeks), 'zones' => $list];
    }

    /**
     * Pack the documents in a zip written to a temporary file; the caller sends it and deletes it.
     *
     * @param  Collection<int, Document>  $documents
     */
    public function pack(Collection $documents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'docs');

        if ($path === false) {
            throw new RuntimeException('No temporary file.');
        }

        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('The archive could not be created.');
        }

        $disk = Storage::disk('local');
        $names = [];

        foreach ($documents->loadMissing('subject') as $document) {
            if (! $disk->exists($document->file_path)) {
                continue;
            }

            $name = $this->entryName($document);
            $names[$name] = ($names[$name] ?? 0) + 1;
            $zip->addFile($disk->path($document->file_path), $names[$name] > 1 ? Str::beforeLast($name, '.pdf')."-{$names[$name]}.pdf" : $name);
        }

        $zip->close();

        return $path;
    }

    /**
     * The name of the zip: what it holds, where and when.
     */
    public function archiveName(DocumentType $type, ?string $zone, ?CarbonInterface $week): string
    {
        $parts = [Str::plural($type->value), $zone === null ? null : Str::slug($zone), $week?->toDateString()];

        return implode('-', array_filter($parts)).'.zip';
    }

    /**
     * @param  array<int, int>  $orderIds
     * @return Collection<int, Document>
     */
    private function documents(array $orderIds, DocumentType $type): Collection
    {
        if ($orderIds === []) {
            return new Collection;
        }

        $query = Document::query()->where('documents.type', $type->value)->whereNull('documents.cancelled_at');

        if ($type === DocumentType::Invoice) {
            $query->where('documents.subject_type', (new Invoice)->getMorphClass())
                ->join('invoices', 'invoices.id', '=', 'documents.subject_id')
                ->whereIn('invoices.order_id', $orderIds)
                ->select('documents.*');
        } else {
            $query->where('documents.subject_type', (new Order)->getMorphClass())->whereIn('documents.subject_id', $orderIds);
        }

        return $query->orderBy('documents.id')->limit(self::MAX_FILES + 1)->get();
    }

    /**
     * "number-client.pdf", plain letters only, so it opens on any system.
     */
    private function entryName(Document $document): string
    {
        $subject = $document->subject;
        $order = $subject instanceof Invoice ? $subject->order : $subject;
        $client = $order instanceof Order ? (string) $order->client()->value('name') : '';

        return Str::slug($document->number.' '.$client, '-', 'fr').'.pdf';
    }
}
