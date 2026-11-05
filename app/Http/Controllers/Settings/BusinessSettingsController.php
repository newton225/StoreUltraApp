<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\BusinessSettingsUpdateRequest;
use App\Support\BusinessSettings;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class BusinessSettingsController extends Controller
{
    /**
     * Show the business settings with their current values.
     */
    public function edit(BusinessSettings $settings): Response
    {
        return Inertia::render('parameters/edit', [
            'values' => $settings->all(),
            'definitions' => collect($settings->definitions())
                ->map(fn (array $definition): array => [
                    'type' => $definition['type'],
                    'options' => $definition['options'] ?? null,
                    'min' => $definition['min'] ?? null,
                ])
                ->all(),
        ]);
    }

    /**
     * Save the business settings.
     */
    public function update(BusinessSettingsUpdateRequest $request, BusinessSettings $settings): RedirectResponse
    {
        foreach ($request->validated() as $key => $value) {
            $settings->set($key, $value);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('parameters.updated')]);

        return to_route('parameters.edit');
    }
}
