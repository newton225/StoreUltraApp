<?php

use App\Enums\DocumentType;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Support\Facades\Storage;
use Inertia\Support\SessionKey;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Storage::fake('local');
    $this->owner = User::factory()->owner()->create();
    $this->week = today()->startOfWeek();
});

function archiveOrder(string $zone, string $client, ?string $week = null): Order
{
    $zoneModel = Zone::query()->firstOrCreate(['name' => $zone], ['active' => true]);

    return Order::factory()
        ->for(Client::factory()->create(['zone_id' => $zoneModel->id, 'name' => $client]))
        ->create(['delivery_week' => $week ?? today()->startOfWeek()->toDateString()]);
}

function proformaOf(Order $order, bool $cancelled = false): Document
{
    $document = Document::factory()->create([
        'type' => DocumentType::Proforma->value,
        'subject_type' => $order->getMorphClass(),
        'subject_id' => $order->id,
        'file_path' => "documents/proforma/{$order->id}.pdf",
        'cancelled_at' => $cancelled ? now() : null,
    ]);
    Storage::disk('local')->put($document->file_path, '%PDF-1.4 proforma '.$order->id);

    return $document;
}

function invoiceOf(Order $order): Document
{
    $invoice = Invoice::factory()->create(['order_id' => $order->id, 'client_id' => $order->client_id]);
    $document = Document::factory()->create([
        'type' => DocumentType::Invoice->value,
        'number' => $invoice->number,
        'subject_type' => $invoice->getMorphClass(),
        'subject_id' => $invoice->id,
        'file_path' => "documents/invoice/{$invoice->id}.pdf",
    ]);
    Storage::disk('local')->put($document->file_path, '%PDF-1.4 invoice '.$order->id);

    return $document;
}

/**
 * @return list<string> the file names inside the zip sent in the response
 */
function zipEntries($response): array
{
    $zip = new ZipArchive;
    $zip->open($response->baseResponse->getFile()->getPathname());
    $names = [];

    for ($index = 0; $index < $zip->numFiles; $index++) {
        $names[] = $zip->getNameIndex($index);
    }

    $zip->close();
    sort($names);

    return $names;
}

test('each order of the list says which valid proforma and invoice it has', function () {
    $both = archiveOrder('Fronan', 'Aya Kone');
    $cancelled = archiveOrder('Fronan', 'Bema Traore');
    $none = archiveOrder('Fronan', 'Cisse Ali');
    $proforma = proformaOf($both);
    $invoice = invoiceOf($both);
    proformaOf($cancelled, cancelled: true);

    $this->actingAs($this->owner)->get(route('orders.index'))->assertInertia(fn (Assert $page) => $page
        ->where('orders.data', fn ($rows) => collect($rows)->firstWhere('id', $both->id)['proforma_document'] === $proforma->id
            && collect($rows)->firstWhere('id', $both->id)['invoice_document'] === $invoice->id
            && collect($rows)->firstWhere('id', $cancelled->id)['proforma_document'] === null
            && collect($rows)->firstWhere('id', $none->id)['invoice_document'] === null)
        ->where('archiveChoices.zones', fn ($zones) => collect($zones)->pluck('name')->all() === ['Fronan']));
});

test('the chosen proformas come in one zip, named after the proforma and the client', function () {
    $first = archiveOrder('Fronan', 'Aya Kone');
    $second = archiveOrder('Fronan', 'Bema Traore');
    $left = archiveOrder('Fronan', 'Cisse Ali');
    $firstDocument = proformaOf($first);
    $secondDocument = proformaOf($second);
    proformaOf($left);

    $response = $this->actingAs($this->owner)
        ->get(route('orders.documents.zip', ['type' => 'proforma', 'orders' => [$first->id, $second->id]]))
        ->assertOk()
        ->assertDownload('proformas.zip');

    expect(zipEntries($response))->toBe(collect([
        str($firstDocument->number.' Aya Kone')->slug()->append('.pdf')->toString(),
        str($secondDocument->number.' Bema Traore')->slug()->append('.pdf')->toString(),
    ])->sort()->values()->all());
    expect($response->headers->get('Cache-Control'))->toContain('no-store');
});

test('a zone and a week give every valid invoice of that zone for that week only', function () {
    $here = archiveOrder('Fronan', 'Aya Kone');
    $otherWeek = archiveOrder('Fronan', 'Bema Traore', $this->week->copy()->subWeek()->toDateString());
    $otherZone = archiveOrder('Katiola', 'Cisse Ali');
    invoiceOf($here);
    invoiceOf($otherWeek);
    invoiceOf($otherZone);

    $response = $this->actingAs($this->owner)
        ->get(route('orders.documents.zip', ['type' => 'invoice', 'zone' => Zone::where('name', 'Fronan')->value('id'), 'week' => today()->toDateString()]))
        ->assertOk()
        ->assertDownload('invoices-fronan-'.$this->week->toDateString().'.zip');

    expect(zipEntries($response))->toHaveCount(1)->and(zipEntries($response)[0])->toEndWith('aya-kone.pdf');
});

test('a cancelled document is never packed and each one packed is written to the audit log', function () {
    $order = archiveOrder('Fronan', 'Aya Kone');
    $other = archiveOrder('Fronan', 'Bema Traore');
    $document = proformaOf($order);
    proformaOf($other, cancelled: true);

    $response = $this->actingAs($this->owner)
        ->get(route('orders.documents.zip', ['type' => 'proforma', 'orders' => [$order->id, $other->id]]))
        ->assertOk();

    expect(zipEntries($response))->toHaveCount(1);
    expect(AuditLog::query()->where('action', 'document.downloaded')->pluck('subject_id')->all())->toBe([$document->id]);
});

test('without any valid document the user is sent back with a message', function () {
    $order = archiveOrder('Fronan', 'Aya Kone');

    $this->actingAs($this->owner)
        ->from(route('orders.index'))
        ->get(route('orders.documents.zip', ['type' => 'proforma', 'orders' => [$order->id]]))
        ->assertRedirect(route('orders.index'));

    expect(session(SessionKey::FLASH_DATA)['toast'])->toBe(['type' => 'error', 'message' => __('orders.archive.none')]);
});

test('a user restricted to some zones only receives the documents of those zones', function () {
    $mine = archiveOrder('Fronan', 'Aya Kone');
    $theirs = archiveOrder('Katiola', 'Cisse Ali');
    proformaOf($mine);
    proformaOf($theirs);
    $agent = User::factory()->agent()->create();
    $agent->zones()->attach(Zone::where('name', 'Fronan')->value('id'));

    $response = $this->actingAs($agent)
        ->get(route('orders.documents.zip', ['type' => 'proforma', 'orders' => [$mine->id, $theirs->id]]))
        ->assertOk();

    expect(zipEntries($response))->toHaveCount(1)->and(zipEntries($response)[0])->toEndWith('aya-kone.pdf');
});

test('the request must name a kind of document and either orders or a zone with a week', function () {
    $this->actingAs($this->owner)->get(route('orders.documents.zip', ['orders' => [1]]))->assertSessionHasErrors('type');
    $this->actingAs($this->owner)->get(route('orders.documents.zip', ['type' => 'receipt', 'orders' => [1]]))->assertSessionHasErrors('type');
    $this->actingAs($this->owner)->get(route('orders.documents.zip', ['type' => 'proforma']))->assertSessionHasErrors(['orders', 'zone']);
    $this->actingAs($this->owner)->get(route('orders.documents.zip', ['type' => 'proforma', 'zone' => Zone::factory()->create()->id]))->assertSessionHasErrors('week');
});

test('a guest cannot download the archive', function () {
    $this->get(route('orders.documents.zip', ['type' => 'proforma', 'orders' => [1]]))->assertRedirect(route('login'));
});

test('each zone says, week by week, how many documents are ready and how many are still to generate', function () {
    $done = archiveOrder('Fronan', 'Aya Kone');
    proformaOf($done);
    archiveOrder('Fronan', 'Bema Traore');
    archiveOrder('Katiola', 'Cisse Ali', today()->subWeek()->startOfWeek()->toDateString());
    Order::query()->whereKey(archiveOrder('Fronan', 'Dia Sy')->id)->update(['status' => 'cancelled']);

    $week = $this->week->toDateString();

    $this->actingAs($this->owner)->get(route('orders.index'))->assertInertia(fn (Assert $page) => $page
        ->where('archiveChoices.weeks', fn ($weeks) => collect($weeks)->first() === $week && count($weeks) === 2)
        ->where('archiveChoices.zones', fn ($zones) => collect($zones)->firstWhere('name', 'Fronan')['counts'][$week]['proforma'] === ['ready' => 1, 'missing' => 1]
            && collect($zones)->firstWhere('name', 'Fronan')['counts'][$week]['invoice'] === ['ready' => 0, 'missing' => 0]
            && ! isset(collect($zones)->firstWhere('name', 'Katiola')['counts'][$week])));
});
