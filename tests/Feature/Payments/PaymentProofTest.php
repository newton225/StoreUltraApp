<?php

use App\Models\AuditLog;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->mobile = PaymentMethod::factory()->create(['code' => 'mobile', 'requires_reference' => true]);
    $this->user = User::factory()->create();
});

/**
 * A real PNG over a temporary file: the fake upload takes its type from the name.
 */
function proofImage(int $colour = 0x336699): UploadedFile
{
    $image = imagecreatetruecolor(40, 40);
    imagefill($image, 0, 0, $colour);
    $path = tempnam(sys_get_temp_dir(), 'proof');

    imagepng($image, $path);

    return new UploadedFile($path, 'capture.png', 'image/png', null, true);
}

test('a proof can go with a payment and is opened through an audited private route', function () {
    $order = payableOrder();

    $this->actingAs($this->user)
        ->post(route('orders.payments.store', $order), paymentPayload($this->mobile, ['reference' => 'MP1', 'proof' => proofImage()]))
        ->assertSessionHasNoErrors();

    $payment = Payment::sole();

    expect($payment->proof_path)->not->toBeNull()->and($payment->proof_hash)->toHaveLength(64);
    Storage::disk('local')->assertExists($payment->proof_path);

    $this->get(route('payments.proof', $payment))->assertOk();

    expect(AuditLog::where('action', 'payment.proof_opened')->count())->toBe(1);
});

test('a payment without proof has no proof to open', function () {
    $this->actingAs($this->user)
        ->post(route('orders.payments.store', payableOrder()), paymentPayload($this->mobile, ['reference' => 'MP1']))
        ->assertSessionHasNoErrors();

    $this->get(route('payments.proof', Payment::sole()))->assertNotFound();
});

test('the same transaction reference is refused for the same method, whatever its spelling', function () {
    $this->actingAs($this->user)
        ->post(route('orders.payments.store', payableOrder()), paymentPayload($this->mobile, ['reference' => 'MP240101.1234', 'amount' => 10000]))
        ->assertSessionHasNoErrors();

    $this->post(route('orders.payments.store', payableOrder()), paymentPayload($this->mobile, ['reference' => 'mp240101 1234', 'amount' => 10000]))
        ->assertSessionHasErrors('reference');

    expect(Payment::count())->toBe(1)
        ->and(AuditLog::where('action', 'payment.duplicate_refused')->count())->toBe(1);
});

test('the same reference is accepted for another method of payment', function () {
    $other = PaymentMethod::factory()->create(['code' => 'bank', 'requires_reference' => true]);

    $this->actingAs($this->user)
        ->post(route('orders.payments.store', payableOrder()), paymentPayload($this->mobile, ['reference' => 'REF9', 'amount' => 10000]))
        ->assertSessionHasNoErrors();

    $this->post(route('orders.payments.store', payableOrder()), paymentPayload($other, ['reference' => 'REF9', 'amount' => 10000]))
        ->assertSessionHasNoErrors();

    expect(Payment::count())->toBe(2);
});

test('the same proof file cannot be attached to a second payment', function () {
    $this->actingAs($this->user)
        ->post(route('orders.payments.store', payableOrder()), paymentPayload($this->mobile, ['reference' => 'A1', 'amount' => 10000, 'proof' => proofImage()]))
        ->assertSessionHasNoErrors();

    $this->post(route('orders.payments.store', payableOrder()), paymentPayload($this->mobile, ['reference' => 'B2', 'amount' => 10000, 'proof' => proofImage()]))
        ->assertSessionHasErrors('proof');

    expect(Payment::count())->toBe(1)
        ->and(Storage::disk('local')->allFiles('payments'))->toHaveCount(1);
});

test('a different proof is accepted', function () {
    $this->actingAs($this->user)
        ->post(route('orders.payments.store', payableOrder()), paymentPayload($this->mobile, ['reference' => 'A1', 'amount' => 10000, 'proof' => proofImage(0x336699)]))
        ->assertSessionHasNoErrors();

    $this->post(route('orders.payments.store', payableOrder()), paymentPayload($this->mobile, ['reference' => 'B2', 'amount' => 10000, 'proof' => proofImage(0x993366)]))
        ->assertSessionHasNoErrors();

    expect(Payment::count())->toBe(2);
});

test('a reversal repeats the reference of the payment it cancels without being a duplicate', function () {
    $order = payableOrder();

    $this->actingAs($this->user)
        ->post(route('orders.payments.store', $order), paymentPayload($this->mobile, ['reference' => 'MP7', 'amount' => 10000]))
        ->assertSessionHasNoErrors();

    $this->post(route('orders.payments.store', $order), paymentPayload($this->mobile, [
        'kind' => 'refund',
        'amount' => 10000,
        'reference' => 'MP7',
        'reverses_payment_id' => Payment::sole()->id,
    ]))->assertSessionHasNoErrors();

    expect(Payment::count())->toBe(2);
});
