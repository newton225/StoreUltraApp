<?php

namespace App\Support\Exports;

use App\Enums\ClientStatus;
use App\Enums\DocumentType;
use App\Enums\OrderStatus;
use App\Enums\PaymentKind;
use App\Enums\PurchaseOrderStatus;
use App\Models\AccessLog;
use App\Models\Attachment;
use App\Models\AttachmentType;
use App\Models\AuditLog;
use App\Models\CashClosing;
use App\Models\CashMovement;
use App\Models\Client;
use App\Models\Delivery;
use App\Models\Document;
use App\Models\Expense;
use App\Models\Kit;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Support\AdvanceArrears;
use App\Support\AttachmentAlerts;
use App\Support\KitPricing;
use App\Support\ListSort;
use App\Support\WeekCalendar;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * The lists that can be exported, each with the same filters, the same rights and the same visibility rules
 * as its page: what a user cannot see on the screen is not in the file either (a field agent only gets the
 * clients and orders of their zones; purchase prices only come with the profit right).
 */
class ListExports
{
    /**
     * Rows beyond this are not exported: a list this long is a report, not a list.
     */
    public const MAX_ROWS = 20000;

    /**
     * @var list<string>
     */
    public const VIEWS = ['clients', 'orders', 'payments', 'cash', 'expenses', 'deliveries', 'purchases', 'products', 'kits', 'audit', 'access', 'documents', 'arrears', 'closings', 'attachments', 'users'];

    public function __construct(private readonly WeekCalendar $calendar, private readonly KitPricing $pricing, private readonly AdvanceArrears $arrears, private readonly AttachmentAlerts $attachmentAlerts) {}

    /**
     * Whether the user may open the page this export comes from.
     */
    public static function allows(User $user, string $view): bool
    {
        $gate = Gate::forUser($user);

        return match ($view) {
            'clients' => $gate->allows('clients.export'),
            'orders' => true,
            'deliveries' => $gate->allows('deliveries.export'),
            'payments', 'arrears' => $gate->allows('payments.export'),
            'documents' => $gate->allows('documents.export'),
            'cash', 'closings' => $gate->allows('manage-cash'),
            'users' => $gate->allows('manage-users'),
            'expenses' => $gate->allows('manage-expenses'),
            'purchases' => $gate->allows('manage-purchases'),
            'products', 'kits' => $gate->allows('manage-catalog'),
            'audit', 'access', 'attachments' => $gate->allows('manage-settings'),
            default => false,
        };
    }

    public function build(string $view, Request $request, User $user): ExportTable
    {
        [$columns, $rows] = match ($view) {
            'clients' => $this->clients($request, $user),
            'orders' => $this->orders($request, $user),
            'payments' => $this->payments($request),
            'cash' => $this->cash($request),
            'expenses' => $this->expenses($request),
            'deliveries' => $this->deliveries($request, $user),
            'purchases' => $this->purchases($request),
            'products' => $this->products($request, $user),
            'kits' => $this->kits($user),
            'audit' => $this->audit(),
            'access' => $this->access($request),
            'arrears' => $this->arrears($request),
            'closings' => $this->closings($request),
            'attachments' => $this->attachments($request),
            'users' => $this->users(),
            default => $this->documents($request),
        };

        return new ExportTable(
            $this->t("exports.views.{$view}"),
            array_map(fn (string $column): string => $this->t("exports.columns.{$column}"), $columns),
            $rows,
        );
    }

    /**
     * @return array{0: list<string>, 1: list<list<int|float|string|null>>}
     */
    private function clients(Request $request, User $user): array
    {
        $search = trim($request->string('search')->toString());
        $status = ClientStatus::tryFrom($request->string('status')->toString());
        $zoneId = $request->integer('zone') ?: null;
        $sort = ListSort::resolve($request, Client::SORTABLE);

        $rows = Client::query()
            ->visibleTo($user)
            ->with('zone:id,name')
            ->when($search !== '', fn (Builder $query) => $query->search($search))
            ->when($status !== null, fn (Builder $query) => $query->where('status', $status->value))
            ->when($zoneId !== null, fn (Builder $query) => $query->where('zone_id', $zoneId))
            ->sortedBy($sort['sort'], $sort['direction'])->limit(self::MAX_ROWS)->get()
            ->map(fn (Client $client): array => [
                $client->reference, $client->commercial_number, $client->name, $client->phone, $client->zone->name,
                $client->locality, $client->delivery_address, $client->contact_person,
                $this->t('clients.statuses.'.$client->status->value), $client->notes,
            ])->all();

        return [['reference', 'commercial_number', 'name', 'phone', 'zone', 'locality', 'delivery_address', 'contact_person', 'status', 'notes'], array_values($rows)];
    }

    /**
     * @return array{0: list<string>, 1: list<list<int|float|string|null>>}
     */
    private function orders(Request $request, User $user): array
    {
        $search = trim($request->string('search')->toString());
        $status = OrderStatus::tryFrom($request->string('status')->toString());
        $week = $request->date('week');
        $sort = ListSort::resolve($request, Order::SORTABLE);

        $rows = Order::query()
            ->visibleTo($user)
            ->with('client:id,name,reference')
            ->when($search !== '', fn (Builder $query) => $query->where(function (Builder $inner) use ($search): void {
                $like = '%'.addcslashes($search, '%_\\').'%';

                $inner->whereLike('reference', $like)
                    ->orWhereHas('client', fn (Builder $clients) => $clients->whereLike('name', $like)->orWhereLike('reference', $like));
            }))
            ->when($status !== null, fn (Builder $query) => $query->where('status', $status->value))
            ->when($week !== null, fn (Builder $query) => $query->whereDate('delivery_week', $this->calendar->startOf($week)))
            ->sortedBy($sort['sort'], $sort['direction'])->limit(self::MAX_ROWS)->get()
            ->map(fn (Order $order): array => [
                $order->reference, $order->client?->name, $order->delivery_week->toDateString(),
                $this->t('orders.statuses.'.$order->status->value), $order->kits_count, $order->total_sale, $order->advance_due,
            ])->all();

        return [['reference', 'client', 'week', 'status', 'kits', 'total_sale', 'advance_due'], array_values($rows)];
    }

    /**
     * @return array{0: list<string>, 1: list<list<int|float|string|null>>}
     */
    private function payments(Request $request): array
    {
        $search = trim($request->string('search')->toString());
        $kind = PaymentKind::tryFrom($request->string('kind')->toString());
        $methodId = $request->integer('method') ?: null;
        $from = $request->date('from');
        $to = $request->date('to');
        $sort = ListSort::resolve($request, Payment::SORTABLE);

        $rows = Payment::query()
            ->with(['order:id,reference,client_id', 'order.client:id,name', 'method', 'collector:id,name', 'recorder:id,name'])
            ->when($search !== '', fn (Builder $query) => $query->where(function (Builder $inner) use ($search): void {
                $like = '%'.addcslashes($search, '%_\\').'%';

                $inner->whereLike('receipt_number', $like)
                    ->orWhereHas('order', fn (Builder $orders) => $orders
                        ->whereLike('reference', $like)
                        ->orWhereHas('client', fn (Builder $clients) => $clients->whereLike('name', $like)));
            }))
            ->when($kind !== null, fn (Builder $query) => $query->where('kind', $kind->value))
            ->when($methodId !== null, fn (Builder $query) => $query->where('payment_method_id', $methodId))
            ->when($from !== null, fn (Builder $query) => $query->whereDate('paid_on', '>=', $from))
            ->when($to !== null, fn (Builder $query) => $query->whereDate('paid_on', '<=', $to))
            ->sortedBy($sort['sort'], $sort['direction'])->limit(self::MAX_ROWS)->get()
            ->map(fn (Payment $payment): array => [
                $payment->receipt_number, $payment->paid_on->toDateString(), $this->t('payments.kinds.'.$payment->kind->value),
                $payment->kind === PaymentKind::Refund ? -$payment->amount : $payment->amount,
                $payment->method->name, $payment->reference, $payment->order->reference, $payment->order->client?->name,
                $payment->payer_name, $payment->collector?->name, $payment->recorder?->name,
            ])->all();

        return [['receipt', 'date', 'kind', 'amount', 'method', 'reference', 'order', 'client', 'payer', 'collector', 'recorded_by'], array_values($rows)];
    }

    /**
     * @return array{0: list<string>, 1: list<list<int|float|string|null>>}
     */
    private function cash(Request $request): array
    {
        $direction = in_array($request->string('direction')->toString(), [CashMovement::IN, CashMovement::OUT], true) ? $request->string('direction')->toString() : null;
        $from = $request->date('from');
        $to = $request->date('to');

        $rows = CashMovement::query()
            ->with('recorder:id,name')
            ->when($direction !== null, fn (Builder $query) => $query->where('direction', $direction))
            ->when($from !== null, fn (Builder $query) => $query->whereDate('occurred_on', '>=', $from))
            ->when($to !== null, fn (Builder $query) => $query->whereDate('occurred_on', '<=', $to))
            ->orderByDesc('occurred_on')->orderByDesc('id')->limit(self::MAX_ROWS)->get()
            ->map(fn (CashMovement $movement): array => [
                $movement->reference, $movement->occurred_on->toDateString(), $this->t('cash.directions.'.$movement->direction),
                $movement->direction === CashMovement::OUT ? -$movement->amount : $movement->amount,
                $movement->label, $movement->counterparty, $movement->document_reference, $movement->recorder?->name,
                $movement->isCancelled() ? $this->t('cash.state_cancelled') : '', $movement->cancel_reason, $movement->notes,
            ])->all();

        return [['reference', 'date', 'direction', 'amount', 'purpose', 'counterparty', 'document_reference', 'recorded_by', 'state', 'cancel_reason', 'notes'], array_values($rows)];
    }

    /**
     * @return array{0: list<string>, 1: list<list<int|float|string|null>>}
     */
    private function arrears(Request $request): array
    {
        $rows = $this->arrears->rows($request->integer('zone') ?: null)
            ->take(self::MAX_ROWS)
            ->map(fn (array $row): array => [
                $row['reference'], $row['client'], $row['phone'], $row['zone'], $row['delivery_week'],
                $row['advance_due'], $row['paid_amount'], $row['advance_outstanding'],
            ])->all();

        return [['reference', 'client', 'phone', 'zone', 'week', 'advance_due', 'paid', 'outstanding'], array_values($rows)];
    }

    /**
     * @return array{0: list<string>, 1: list<list<int|float|string|null>>}
     */
    private function closings(Request $request): array
    {
        $rows = CashClosing::query()
            ->with('closer:id,name')
            ->orderByDesc('closed_on')->limit(self::MAX_ROWS)->get()
            ->map(fn (CashClosing $closing): array => [
                $closing->reference, $closing->closed_on->toDateString(), $closing->expected_amount, $closing->counted_amount,
                $closing->difference, $closing->closer?->name, $closing->notes,
            ])->all();

        return [['reference', 'date', 'expected', 'counted', 'difference', 'recorded_by', 'notes'], array_values($rows)];
    }

    /**
     * The administrative documents of the kind shown on the page (the file itself is never exported).
     *
     * @return array{0: list<string>, 1: list<list<int|float|string|null>>}
     */
    private function attachments(Request $request): array
    {
        $kind = in_array($request->query('kind'), AttachmentType::KINDS, true) ? (string) $request->query('kind') : 'company';
        $search = trim($request->string('search')->toString());
        $owners = $this->attachmentAlerts->owners();

        $rows = Attachment::query()
            ->with('type')
            ->whereHas('type', fn (Builder $query) => $query->where('applies_to', $kind))
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $inner) => $inner
                ->whereLike('number', '%'.addcslashes($search, '%_\\').'%')
                ->orWhereLike('notes', '%'.addcslashes($search, '%_\\').'%')))
            ->orderByDesc('id')->limit(self::MAX_ROWS)->get()
            ->map(fn (Attachment $attachment): array => [
                $attachment->type->name, $owners[$kind][$attachment->owner_id ?? 0] ?? '', $attachment->number,
                $attachment->issued_on?->toDateString(), $attachment->expires_on?->toDateString(),
                $attachment->file_path !== null ? $this->t('exports.yes') : '', $attachment->notes,
            ])->all();

        return [['type', 'owner', 'number', 'issued_on', 'expires_on', 'has_file', 'notes'], array_values($rows)];
    }

    /**
     * The accounts and their profile: who may do what, for the audit of access rights. Never a password.
     *
     * @return array{0: list<string>, 1: list<list<int|float|string|null>>}
     */
    private function users(): array
    {
        $rows = User::query()
            ->with('profile')
            ->orderBy('name')->limit(self::MAX_ROWS)->get()
            ->map(fn (User $user): array => [
                $user->name, $user->email, $user->effectiveProfile()->label,
                $user->is_active ? $this->t('exports.active') : $this->t('exports.inactive'),
            ])->all();

        return [['name', 'email', 'profile', 'state'], array_values($rows)];
    }

    /**
     * @return array{0: list<string>, 1: list<list<int|float|string|null>>}
     */
    private function expenses(Request $request): array
    {
        $typeId = $request->integer('type') ?: null;
        $from = $request->date('from');
        $to = $request->date('to');

        $rows = Expense::query()
            ->with(['type', 'author:id,name'])
            ->withCount('allocations')
            ->when($typeId !== null, fn (Builder $query) => $query->where('expense_type_id', $typeId))
            ->when($from !== null, fn (Builder $query) => $query->whereDate('incurred_on', '>=', $from))
            ->when($to !== null, fn (Builder $query) => $query->whereDate('incurred_on', '<=', $to))
            ->orderByDesc('incurred_on')->orderByDesc('id')->limit(self::MAX_ROWS)->get()
            ->map(fn (Expense $expense): array => [
                $expense->incurred_on->toDateString(), $expense->type->name, $expense->amount,
                $this->t('expenses.scopes.'.$expense->scope), $expense->week?->toDateString(), $expense->allocations_count,
                $expense->paid_from_cash ? $this->t('exports.yes') : '', $expense->author?->name,
                $expense->isCancelled() ? $this->t('expenses.state_cancelled') : '', $expense->cancel_reason, $expense->notes,
            ])->all();

        return [['date', 'type', 'amount', 'scope', 'week', 'orders', 'paid_from_cash', 'recorded_by', 'state', 'cancel_reason', 'notes'], array_values($rows)];
    }

    /**
     * @return array{0: list<string>, 1: list<list<int|float|string|null>>}
     */
    private function deliveries(Request $request, User $user): array
    {
        $zoneId = $request->integer('zone') ?: null;

        $rows = Delivery::query()
            ->whereHas('order', fn (Builder $orders) => $orders->visibleTo($user)
                ->when($zoneId !== null, fn (Builder $inner) => $inner->whereHas('client', fn (Builder $clients) => $clients->where('zone_id', $zoneId))))
            ->with(['order.client.zone', 'agent:id,name'])
            ->orderByDesc('delivered_on')->orderByDesc('id')->limit(self::MAX_ROWS)->get()
            ->map(fn (Delivery $delivery): array => [
                $delivery->reference, $delivery->delivered_on->toDateString(), $delivery->order->reference,
                $delivery->order->client?->name, $delivery->order->client?->zone->name, $delivery->kits_delivered,
                $delivery->received_by, $delivery->agent?->name, $delivery->signature_path !== null ? $this->t('exports.yes') : '',
            ])->all();

        return [['reference', 'date', 'order', 'client', 'zone', 'kits_delivered', 'received_by', 'agent', 'signed'], array_values($rows)];
    }

    /**
     * @return array{0: list<string>, 1: list<list<int|float|string|null>>}
     */
    private function purchases(Request $request): array
    {
        $week = $this->calendar->startOf($request->date('week') ?? now());
        $shopId = $request->integer('shop') ?: null;
        $status = PurchaseOrderStatus::tryFrom($request->string('status')->toString());

        $rows = PurchaseOrder::query()
            ->with('shop:id,name')
            ->whereDate('week', $week)
            ->when($shopId !== null, fn (Builder $query) => $query->where('shop_id', $shopId))
            ->when($status !== null, fn (Builder $query) => $query->where('status', $status->value))
            ->orderBy('shop_id')->orderBy('id')->limit(self::MAX_ROWS)->get()
            ->map(fn (PurchaseOrder $purchase): array => [
                $purchase->reference, $purchase->shop->name, $purchase->week->toDateString(),
                $this->t('purchases.statuses.'.$purchase->status->value), $purchase->expected_total, $purchase->actual_total,
            ])->all();

        return [['reference', 'shop', 'week', 'status', 'expected_total', 'actual_total'], array_values($rows)];
    }

    /**
     * @return array{0: list<string>, 1: list<list<int|float|string|null>>}
     */
    private function products(Request $request, User $user): array
    {
        $showArchived = $request->boolean('archived');
        $search = trim($request->string('search')->toString());
        $withPrices = Gate::forUser($user)->allows('view-profit');
        $sort = ListSort::resolve($request, Product::SORTABLE);

        $rows = Product::query()
            ->when(! $showArchived, fn ($query) => $query->notArchived())
            ->when($search !== '', fn ($query) => $query->search($search))
            ->with(['shop:id,name', 'currentPrice'])
            ->sortedBy($sort['sort'], $sort['direction'])->limit(self::MAX_ROWS)->get()
            ->map(fn (Product $product): array => [
                $product->name, $product->unit, $product->shop?->name,
                ...($withPrices ? [$product->currentPrice?->purchase_price, $product->currentPrice?->sale_price] : []),
                $product->archived_at !== null ? $this->t('exports.yes') : '',
            ])->all();

        return [
            ['name', 'unit', 'shop', ...($withPrices ? ['purchase_price', 'sale_price'] : []), 'archived'],
            array_values($rows),
        ];
    }

    /**
     * @return array{0: list<string>, 1: list<list<int|float|string|null>>}
     */
    private function kits(User $user): array
    {
        $withPrices = Gate::forUser($user)->allows('view-profit');

        $rows = Kit::query()
            ->with('currentVersion.items.product.currentPrice')
            ->orderBy('name_fr')->limit(self::MAX_ROWS)->get()
            ->map(function (Kit $kit) use ($withPrices): array {
                $version = $kit->currentVersion;
                $summary = $version === null ? null : $this->pricing->summary($version);

                return [
                    $kit->name, $kit->active ? $this->t('exports.yes') : '', $version?->version, $version?->valid_from->toDateString(),
                    $version === null ? null : $version->items->count(),
                    ...($withPrices ? [$summary?->purchase, $summary?->sale] : []),
                ];
            })->all();

        return [
            ['name', 'active', 'version', 'valid_from', 'items', ...($withPrices ? ['kit_purchase', 'kit_sale'] : [])],
            array_values($rows),
        ];
    }

    /**
     * @return array{0: list<string>, 1: list<list<int|float|string|null>>}
     */
    private function audit(): array
    {
        $rows = AuditLog::query()
            ->with('user:id,name')
            ->orderByDesc('id')->limit(self::MAX_ROWS)->get()
            ->map(fn (AuditLog $entry): array => [
                $entry->created_at?->format('Y-m-d H:i:s'),
                $entry->user_id === null ? $this->t('orders.audit.system') : $entry->user->name,
                $this->auditPhrase($entry->action),
                class_basename($entry->subject_type).' #'.$entry->subject_id,
                $entry->ip_address,
                $this->json($entry->before),
                $this->json($entry->after),
            ])->all();

        return [['date', 'user', 'action', 'subject', 'ip', 'before', 'after'], array_values($rows)];
    }

    /**
     * @param  array<string, mixed>|null  $value
     */
    private function json(?array $value): ?string
    {
        return $value === null ? null : (string) json_encode($value, JSON_UNESCAPED_UNICODE);
    }

    private function t(string $key): string
    {
        return (string) __($key);
    }

    /**
     * @return array{0: list<string>, 1: list<list<int|float|string|null>>}
     */
    private function access(Request $request): array
    {
        $userId = $request->integer('user') ?: null;
        $ip = trim($request->string('ip')->toString());
        $kind = in_array($request->string('kind')->toString(), [AccessLog::PAGE, AccessLog::LOGIN_FAILED, AccessLog::LOCKOUT], true) ? $request->string('kind')->toString() : null;
        $from = $request->date('from');
        $to = $request->date('to');

        $rows = AccessLog::query()
            ->with('user:id,name')
            ->when($userId !== null, fn (Builder $query) => $query->where('user_id', $userId))
            ->when($ip !== '', fn (Builder $query) => $query->where('ip_address', $ip))
            ->when($kind !== null, fn (Builder $query) => $query->where('kind', $kind))
            ->when($from !== null, fn (Builder $query) => $query->whereDate('created_at', '>=', $from))
            ->when($to !== null, fn (Builder $query) => $query->whereDate('created_at', '<=', $to))
            ->orderByDesc('id')->limit(self::MAX_ROWS)->get()
            ->map(fn (AccessLog $entry): array => [
                $entry->created_at->format('Y-m-d H:i:s'),
                $entry->user_id === null ? $entry->email : $entry->user->name,
                $this->t('accesslog.kinds.'.$entry->kind),
                $entry->method,
                $entry->path.($entry->query === null ? '' : '?'.$entry->query),
                $entry->status,
                $entry->ip_address,
                $entry->user_agent,
            ])->all();

        return [['date', 'user', 'kind', 'method', 'page', 'status', 'ip', 'browser'], array_values($rows)];
    }

    private function auditPhrase(string $action): string
    {
        [$model, $verb] = array_pad(explode('.', $action, 2), 2, '');
        $phrase = $this->t("orders.audit.actions.{$model}.{$verb}");

        return str_starts_with($phrase, 'orders.audit.') ? $action : $phrase;
    }

    /**
     * @return array{0: list<string>, 1: list<list<int|float|string|null>>}
     */
    private function documents(Request $request): array
    {
        $type = DocumentType::tryFrom($request->string('type')->toString());
        $search = trim($request->string('search')->toString());
        $state = $request->string('state')->toString();

        $rows = Document::query()
            ->when($type !== null, fn (Builder $query) => $query->where('type', $type->value))
            ->when($search !== '', fn (Builder $query) => $query->whereLike('number', '%'.addcslashes($search, '%_\\').'%'))
            ->when($state === 'valid', fn (Builder $query) => $query->whereNull('cancelled_at'))
            ->when($state === 'cancelled', fn (Builder $query) => $query->whereNotNull('cancelled_at'))
            ->orderByDesc('id')->limit(self::MAX_ROWS)->get()
            ->map(fn (Document $document): array => [
                $document->number, $this->t('documents.types.'.$document->type->value), $document->locale,
                $document->issued_at->format('Y-m-d H:i'),
                $document->isCancelled() ? $this->t('exports.cancelled') : '', $document->cancel_reason,
            ])->all();

        return [['number', 'type', 'language', 'issued_at', 'state', 'cancel_reason'], array_values($rows)];
    }
}
