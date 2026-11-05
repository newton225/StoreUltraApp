<?php

namespace App\Http\Requests;

use App\Enums\DocumentType;
use App\Models\Order;
use App\Models\Zone;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DownloadDocumentArchiveRequest extends FormRequest
{
    /**
     * Whoever may see orders; the documents of orders out of reach are left out afterwards.
     */
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', Order::class);
    }

    /**
     * The chosen orders, or a zone and a week.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in([DocumentType::Proforma->value, DocumentType::Invoice->value])],
            'orders' => ['required_without:zone', 'array', 'min:1', 'max:200'],
            'orders.*' => ['integer'],
            'zone' => ['required_without:orders', 'integer', Rule::exists(Zone::class, 'id')],
            'week' => ['required_with:zone', 'date'],
        ];
    }
}
