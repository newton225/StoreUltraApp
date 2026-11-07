<?php

namespace App\Support;

use App\Models\Attachment;
use Illuminate\Support\Facades\Storage;

/**
 * Who the company is, as printed in the header and the footer of every document we issue: the name, the
 * legal form, the contacts, the logo and the numbers of the administrative documents meant to be shown (trade
 * register, tax account...). One place, so that all the documents look the same.
 */
class CompanyProfile
{
    public function __construct(private readonly BusinessSettings $settings) {}

    /**
     * What the document views print about the company.
     *
     * @return array{name: string|null, address: string|null, phone: string|null, email: string|null, website: string|null, legal_form: string|null, capital: string|null, tax_office: string|null, signatory: string|null, signatory_title: string|null, footer_note: string|null, logo: string|null, identifiers: list<string>}
     */
    public function forDocuments(string $locale): array
    {
        return [
            'name' => $this->text('company_name'),
            'address' => $this->text('company_address'),
            'phone' => $this->text('company_phone'),
            'email' => $this->text('company_email'),
            'website' => $this->text('company_website'),
            'legal_form' => $this->text('company_legal_form'),
            'capital' => $this->text('company_capital'),
            'tax_office' => $this->text('company_tax_office'),
            'signatory' => $this->text('company_signatory'),
            'signatory_title' => $this->text('company_signatory_title'),
            'footer_note' => $this->text('company_footer_note'),
            'logo' => $this->logoDataUri(),
            'identifiers' => $this->identifiers($locale),
        ];
    }

    /**
     * The numbers of the company documents meant to be printed (trade register, tax account...), as "label : number".
     *
     * @return list<string>
     */
    public function identifiers(string $locale): array
    {
        $lines = [];

        $attachments = Attachment::query()
            ->with('type')
            ->whereHas('type', fn ($query) => $query->where('applies_to', 'company')->where('on_documents', true)->where('active', true))
            ->orderBy('attachment_type_id')
            ->orderByDesc('id')
            ->get()
            ->unique('attachment_type_id');

        foreach ($attachments as $attachment) {
            $label = $locale === 'en' ? $attachment->type->label_en : $attachment->type->label_fr;
            $lines[] = $label.' : '.$attachment->number;
        }

        return $lines;
    }

    /**
     * The logo written inside the page of a PDF (the PDF engine reads it from there, no file path to open).
     */
    public function logoDataUri(): ?string
    {
        $path = $this->text('company_logo');
        $disk = Storage::disk('local');

        if ($path === null || ! $disk->exists($path)) {
            return null;
        }

        $mime = str_ends_with($path, '.png') ? 'image/png' : 'image/jpeg';

        return 'data:'.$mime.';base64,'.base64_encode((string) $disk->get($path));
    }

    private function text(string $key): ?string
    {
        $value = $this->settings->get($key);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
