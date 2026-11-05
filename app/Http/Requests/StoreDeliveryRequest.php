<?php

namespace App\Http\Requests;

use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Support\BusinessSettings;
use App\Support\UploadedFiles;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDeliveryRequest extends FormRequest
{
    private const MAX_SIGNATURE_LENGTH = 700000;

    /**
     * Whoever can see the order can record its delivery: the office, or the agent of its zone.
     */
    public function authorize(): bool
    {
        /** @var Order $order */
        $order = $this->route('order');

        return $this->user()->can('view', $order);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string|Closure>
     */
    public function rules(): array
    {
        return [
            'kits_delivered' => ['required', 'integer', 'min:1'],
            'delivered_on' => ['required', 'date', 'before_or_equal:today'],
            'received_by' => ['required', 'string', 'max:255'],
            'signature' => [
                Rule::requiredIf(fn (): bool => (bool) app(BusinessSettings::class)->get('delivery_signature_required')),
                'nullable',
                'string',
                'max:'.self::MAX_SIGNATURE_LENGTH,
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (self::decodeSignature($value) === null) {
                        $fail(__('deliveries.errors.signature'));
                    }
                },
            ],
            'agent_id' => ['nullable', 'integer', Rule::exists(User::class, 'id')->where('role', 'agent')->where('is_active', true)],
            'notes' => ['nullable', 'string', 'max:1000'],
            'idempotency_key' => ['nullable', 'string', 'max:64'],
            'collect_amount' => ['nullable', 'integer', 'min:1'],
            'payment_method_id' => ['required_with:collect_amount', 'nullable', 'integer', Rule::exists(PaymentMethod::class, 'id')->where('active', true)],
            'reference' => [
                Rule::requiredIf(fn (): bool => $this->filled('collect_amount') && (bool) PaymentMethod::find($this->integer('payment_method_id'))?->requires_reference),
                'nullable',
                'string',
                'max:100',
            ],
        ];
    }

    /**
     * The binary PNG of a signature sent as a data URL, written again from its pixels so that nothing
     * else it carried is kept, or null if it is not a real PNG of a reasonable size.
     */
    public static function decodeSignature(mixed $value): ?string
    {
        if (! is_string($value) || ! str_starts_with($value, 'data:image/png;base64,')) {
            return null;
        }

        $binary = base64_decode(substr($value, strlen('data:image/png;base64,')), true);

        if ($binary === false) {
            return null;
        }

        return app(UploadedFiles::class)->cleanImage($binary, 'image/png');
    }
}
