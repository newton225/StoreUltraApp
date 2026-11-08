<?php

namespace App\Http\Controllers;

use App\Support\LegalTexts;
use Inertia\Inertia;
use Inertia\Response;

class LegalController extends Controller
{
    /**
     * Show one of the legal texts. Public: it is linked from the sign-in page and read before having an account.
     * The texts are sent only to this page, never with every page (PRIV-10).
     */
    public function show(string $document, LegalTexts $texts): Response
    {
        abort_unless(in_array($document, LegalTexts::DOCUMENTS, true), 404);

        return Inertia::render('legal/show', [
            'document' => $texts->document($document, app()->getLocale()),
            'documents' => LegalTexts::DOCUMENTS,
        ]);
    }
}
