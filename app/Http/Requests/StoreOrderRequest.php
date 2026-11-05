<?php

namespace App\Http\Requests;

use App\Models\Client;
use App\Models\Kit;
use App\Models\Order;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', Order::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'client_id' => ['required', 'integer', Rule::exists(Client::class, 'id')],
            'kit_id' => ['required', 'integer', Rule::exists(Kit::class, 'id')->where('active', true)],
            'kits_count' => ['required', 'integer', 'min:1', 'max:1000'],
            'delivery_week' => ['required', 'date'],
            'advance_due' => ['required', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
