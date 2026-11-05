<?php

namespace Database\Seeders;

use App\Models\AttachmentType;
use Illuminate\Database\Seeder;

class InitialAttachmentTypesSeeder extends Seeder
{
    /**
     * The usual documents: code, french label, english label, kind of owner, required, has an expiry, printed on documents.
     *
     * @var array<int, array{string, string, string, string, bool, bool, bool}>
     */
    private const TYPES = [
        ['company_register', 'Registre de commerce (RCCM)', 'Trade register (RCCM)', 'company', true, false, true],
        ['company_tax_account', 'Numéro de compte contribuable', 'Taxpayer account number', 'company', true, false, true],
        ['company_unique_id', 'Identifiant unique d\'entreprise', 'Unique company identifier', 'company', false, false, true],
        ['company_other', 'Autre document de l\'entreprise', 'Other company document', 'company', false, true, false],
        ['shop_register', 'Registre de commerce de la boutique', 'Shop trade register', 'shop', false, false, false],
        ['client_identity', 'Pièce d\'identité', 'Identity document', 'client', false, true, false],
        ['client_address_proof', 'Justificatif de domicile', 'Proof of address', 'client', false, false, false],
    ];

    /**
     * Seed the document types. Running it again changes nothing.
     */
    public function run(): void
    {
        foreach (self::TYPES as [$code, $labelFr, $labelEn, $kind, $required, $hasExpiry, $onDocuments]) {
            AttachmentType::firstOrCreate(['code' => $code], [
                'label_fr' => $labelFr,
                'label_en' => $labelEn,
                'applies_to' => $kind,
                'required' => $required,
                'has_expiry' => $hasExpiry,
                'on_documents' => $onDocuments,
            ]);
        }
    }
}
