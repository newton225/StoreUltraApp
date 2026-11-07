<?php

namespace App\Http\Requests;

use App\Enums\ClientStatus;
use App\Models\Client;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Changing only the status of a client (activate, suspend, end), without the rest of the form. Like on the
 * form, only the office chooses the status: a field agent cannot.
 */
class ClientStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Client $client */
        $client = $this->route('client');

        return ! $this->user()->isRestrictedToZones() && $this->user()->can('clients.change_status') && $this->user()->can('view', $client);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(ClientStatus::class)],
        ];
    }
}
