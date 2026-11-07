<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCompanyLogoRequest;
use App\Http\Requests\UpdateCompanyRequest;
use App\Support\BusinessSettings;
use App\Support\CompanyProfile;
use App\Support\UploadedFiles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The page where the owner says who the company is: what is printed in the header and the footer of every
 * document (proforma, invoice, receipt, delivery note, purchase order, statement).
 */
class CompanyController extends Controller
{
    /**
     * Show the company details with the logo and the administrative numbers that are printed too.
     */
    public function edit(BusinessSettings $settings, CompanyProfile $profile): Response
    {
        return Inertia::render('company/edit', [
            'values' => array_intersect_key($settings->all(), $settings->company()),
            'hasLogo' => $settings->get('company_logo') !== null,
            'identifiers' => $profile->identifiers(app()->getLocale()),
        ]);
    }

    /**
     * Save the company details.
     */
    public function update(UpdateCompanyRequest $request, BusinessSettings $settings): RedirectResponse
    {
        foreach ($request->validated() as $key => $value) {
            $settings->set($key, $value);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('company.updated')]);

        return to_route('company.edit');
    }

    /**
     * Keep a new logo, written again from its pixels, and remove the previous one.
     */
    public function storeLogo(StoreCompanyLogoRequest $request, BusinessSettings $settings, UploadedFiles $files): RedirectResponse
    {
        $previous = $settings->get('company_logo');

        $settings->set('company_logo', $files->store($request->file('logo'), 'company'));

        if (is_string($previous)) {
            Storage::disk('local')->delete($previous);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('company.logo_saved')]);

        return to_route('company.edit');
    }

    /**
     * Remove the logo: the documents are then issued without one.
     */
    public function destroyLogo(BusinessSettings $settings): RedirectResponse
    {
        $previous = $settings->get('company_logo');

        $settings->set('company_logo', null);

        if (is_string($previous)) {
            Storage::disk('local')->delete($previous);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('company.logo_removed')]);

        return to_route('company.edit');
    }

    /**
     * The logo as an image, for the preview on the page. It is private: only the owner reads it here.
     */
    public function logo(BusinessSettings $settings, UploadedFiles $files): StreamedResponse
    {
        $path = $settings->get('company_logo');

        abort_unless(is_string($path) && Storage::disk('local')->exists($path), 404);

        return $files->response($path);
    }
}
