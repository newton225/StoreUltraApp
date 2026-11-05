<?php

use App\Models\Client;
use App\Models\Delivery;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Storage::fake('local');
    $this->employee = User::factory()->create();
});

function agentOf(Zone $zone): User
{
    $agent = User::factory()->agent()->create();
    $agent->zones()->attach($zone);

    return $agent;
}

test('the office issues a proforma and the document is kept', function () {
    $order = realOrder('draft');

    $this->actingAs($this->employee)
        ->post(route('documents.store'), ['type' => 'proforma', 'subject_id' => $order->id, 'locale' => 'fr'])
        ->assertSessionHasNoErrors();

    $document = Document::sole();

    expect($document->type->value)->toBe('proforma');
    expect($document->subject_id)->toBe($order->id);
    Storage::disk('local')->assertExists($document->file_path);
});

test('a second document of the same kind is refused with a message', function () {
    $order = realOrder('draft');
    $this->actingAs($this->employee);

    $this->post(route('documents.store'), ['type' => 'proforma', 'subject_id' => $order->id, 'locale' => 'fr']);
    $this->post(route('documents.store'), ['type' => 'proforma', 'subject_id' => $order->id, 'locale' => 'fr'])
        ->assertSessionHasErrors('type');

    expect(Document::count())->toBe(1);
});

test('the invoice takes the fne number given by the office', function () {
    $order = realOrder('delivered');

    $this->actingAs($this->employee)
        ->post(route('documents.store'), ['type' => 'invoice', 'subject_id' => $order->id, 'locale' => 'fr', 'fne_number' => 'FNE-1'])
        ->assertSessionHasNoErrors();

    expect(Invoice::sole()->fne_number)->toBe('FNE-1');
});

test('the office issues a statement for a period', function () {
    $this->actingAs($this->employee)
        ->post(route('documents.store'), [
            'type' => 'statement',
            'locale' => 'en',
            'period_start' => today()->startOfMonth()->toDateString(),
            'period_end' => today()->endOfMonth()->toDateString(),
        ])
        ->assertSessionHasNoErrors();

    expect(Document::sole()->type->value)->toBe('statement');
});

test('a request is rejected for invalid data', function (array $payload, string $field) {
    $order = realOrder('draft');
    $payload = array_map(fn ($value) => $value === ':order' ? $order->id : $value, $payload);

    $this->actingAs($this->employee)
        ->post(route('documents.store'), $payload)
        ->assertSessionHasErrors($field);

    expect(Document::count())->toBe(0);
})->with([
    'unknown type' => [['type' => 'contract', 'subject_id' => ':order', 'locale' => 'fr'], 'type'],
    'unsupported language' => [['type' => 'proforma', 'subject_id' => ':order', 'locale' => 'de'], 'locale'],
    'missing subject' => [['type' => 'proforma', 'locale' => 'fr'], 'subject_id'],
    'unknown subject' => [['type' => 'proforma', 'subject_id' => 9999, 'locale' => 'fr'], 'subject_id'],
    'statement without period' => [['type' => 'statement', 'locale' => 'fr'], 'period_start'],
    'statement ending before it starts' => [['type' => 'statement', 'locale' => 'fr', 'period_start' => '2026-07-10', 'period_end' => '2026-07-01'], 'period_end'],
]);

test('a field agent issues delivery notes and receipts for their zone only', function () {
    $zone = Zone::factory()->create();
    $order = Order::factory()->for(Client::factory()->for($zone))->create(['status' => 'delivered']);
    $delivery = Delivery::factory()->for($order)->create();
    $payment = Payment::factory()->for($order)->create(['payment_method_id' => PaymentMethod::factory()->create()->id]);
    $foreignDelivery = Delivery::factory()->create();
    $agent = agentOf($zone);

    $this->actingAs($agent);

    $this->post(route('documents.store'), ['type' => 'delivery_note', 'subject_id' => $delivery->id, 'locale' => 'fr'])->assertSessionHasNoErrors();
    $this->post(route('documents.store'), ['type' => 'receipt', 'subject_id' => $payment->id, 'locale' => 'fr'])->assertSessionHasNoErrors();
    $this->post(route('documents.store'), ['type' => 'delivery_note', 'subject_id' => $foreignDelivery->id, 'locale' => 'fr'])->assertForbidden();

    expect(Document::count())->toBe(2);
});

test('a field agent cannot issue the other documents', function (string $type) {
    $zone = Zone::factory()->create();
    $order = Order::factory()->for(Client::factory()->for($zone))->create(['status' => 'delivered']);
    $purchase = PurchaseOrder::factory()->create(['status' => 'sent']);
    $subject = $type === 'purchase_order' ? $purchase->id : $order->id;

    $this->actingAs(agentOf($zone))
        ->post(route('documents.store'), ['type' => $type, 'subject_id' => $subject, 'locale' => 'fr', 'period_start' => '2026-07-01', 'period_end' => '2026-07-31'])
        ->assertForbidden();

    expect(Document::count())->toBe(0);
})->with(['proforma', 'invoice', 'purchase_order', 'statement']);

test('guests are sent to the login page', function () {
    $this->post(route('documents.store'), [])->assertRedirect(route('login'));
    $this->get(route('documents.index'))->assertRedirect(route('login'));
});

test('a document is downloaded as a pdf by whoever can see its order', function () {
    $zone = Zone::factory()->create();
    $order = Order::factory()->for(Client::factory()->for($zone))->create(['status' => 'delivered']);
    $delivery = Delivery::factory()->for($order)->create();
    $this->actingAs($this->employee)->post(route('documents.store'), ['type' => 'delivery_note', 'subject_id' => $delivery->id, 'locale' => 'fr']);
    $document = Document::sole();

    $this->get(route('documents.download', $document))->assertOk()->assertHeader('Content-Type', 'application/pdf');

    $this->actingAs(agentOf($zone))->get(route('documents.download', $document))->assertOk();
    $this->actingAs(agentOf(Zone::factory()->create()))->get(route('documents.download', $document))->assertForbidden();
});

test('a field agent cannot download an office document', function () {
    $this->actingAs($this->employee)->post(route('documents.store'), [
        'type' => 'statement',
        'locale' => 'fr',
        'period_start' => '2026-07-01',
        'period_end' => '2026-07-31',
    ]);

    $this->actingAs(User::factory()->agent()->create())
        ->get(route('documents.download', Document::sole()))
        ->assertForbidden();
});

test('the office cancels a document with a reason, and can replace it in one step', function () {
    $order = realOrder('draft');
    $this->actingAs($this->employee)->post(route('documents.store'), ['type' => 'proforma', 'subject_id' => $order->id, 'locale' => 'fr']);
    $document = Document::sole();

    $this->post(route('documents.cancel', $document), [])->assertSessionHasErrors('reason');

    $this->post(route('documents.cancel', $document), ['reason' => 'Wrong client', 'replace' => true, 'locale' => 'en'])
        ->assertSessionHasNoErrors();

    $document->refresh();

    expect($document->cancelled_at)->not->toBeNull();
    expect($document->replaced_by_id)->not->toBeNull();
    expect(Document::whereNull('cancelled_at')->sole()->locale)->toBe('en');
});

test('cancelling without a replacement leaves nothing valid', function () {
    $order = realOrder('draft');
    $this->actingAs($this->employee)->post(route('documents.store'), ['type' => 'proforma', 'subject_id' => $order->id, 'locale' => 'fr']);

    $this->post(route('documents.cancel', Document::sole()), ['reason' => 'Not needed'])->assertSessionHasNoErrors();

    expect(Document::whereNull('cancelled_at')->count())->toBe(0);
});

test('a field agent cannot cancel a document', function () {
    $order = realOrder('draft');
    $this->actingAs($this->employee)->post(route('documents.store'), ['type' => 'proforma', 'subject_id' => $order->id, 'locale' => 'fr']);

    $this->actingAs(User::factory()->agent()->create())
        ->post(route('documents.cancel', Document::sole()), ['reason' => 'x'])
        ->assertForbidden();
});

test('the office lists the documents and filters them', function () {
    $this->actingAs($this->employee);
    $first = realOrder('draft');
    $this->post(route('documents.store'), ['type' => 'proforma', 'subject_id' => $first->id, 'locale' => 'fr']);
    $second = realOrder('delivered');
    $this->post(route('documents.store'), ['type' => 'invoice', 'subject_id' => $second->id, 'locale' => 'fr']);

    $this->get(route('documents.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('documents/index')->where('documents.total', 2));

    $this->get(route('documents.index', ['type' => 'invoice']))
        ->assertInertia(fn (Assert $page) => $page->where('documents.total', 1)->where('documents.data.0.type', 'invoice'));

    $this->get(route('documents.index', ['search' => 'PRO-']))
        ->assertInertia(fn (Assert $page) => $page->where('documents.total', 1)->where('documents.data.0.type', 'proforma'));

    $this->actingAs(User::factory()->agent()->create())->get(route('documents.index'))->assertForbidden();
});

test('the order page lists what can be issued and what already was', function () {
    $order = realOrder('delivered');
    $delivery = Delivery::factory()->for($order)->create();
    $this->actingAs($this->employee)->post(route('documents.store'), ['type' => 'proforma', 'subject_id' => $order->id, 'locale' => 'fr']);

    $this->get(route('orders.show', $order))
        ->assertInertia(fn (Assert $page) => $page
            ->where('documents.targets', fn ($targets) => collect($targets)->contains(fn ($t) => $t['type'] === 'proforma' && $t['document'] !== null)
                && collect($targets)->contains(fn ($t) => $t['type'] === 'delivery_note' && $t['subject_id'] === $delivery->id && $t['document'] === null)
                && collect($targets)->contains(fn ($t) => $t['type'] === 'invoice' && $t['can_issue'] === true)));
});

test('the fne number is recorded once on an invoice', function () {
    $order = realOrder('delivered');
    $this->actingAs($this->employee)->post(route('documents.store'), ['type' => 'invoice', 'subject_id' => $order->id, 'locale' => 'fr']);
    $invoice = Invoice::sole();

    $this->patch(route('invoices.fne', $invoice), ['fne_number' => ''])->assertSessionHasErrors('fne_number');

    $this->patch(route('invoices.fne', $invoice), ['fne_number' => 'FNE-555'])->assertSessionHasNoErrors();
    expect($invoice->refresh()->fne_status)->toBe('issued');
    expect($invoice->fne_number)->toBe('FNE-555');

    $this->patch(route('invoices.fne', $invoice), ['fne_number' => 'FNE-666'])->assertSessionHasErrors('fne_number');
    expect($invoice->refresh()->fne_number)->toBe('FNE-555');
});

test('a field agent cannot record a fne number', function () {
    $this->actingAs(User::factory()->agent()->create())
        ->patch(route('invoices.fne', Invoice::factory()->create()), ['fne_number' => 'FNE-1'])
        ->assertForbidden();
});
