<?php

namespace App\Http\Controllers;

use App\Http\Requests\AttachmentTypeRequest;
use App\Models\AttachmentType;
use App\Support\CodeFromLabel;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AttachmentTypeController extends Controller
{
    /**
     * List the kinds of administrative document.
     */
    public function index(): Response
    {
        return Inertia::render('attachment-types/index', [
            'types' => AttachmentType::query()->orderBy('applies_to')->orderBy('label_fr')
                ->get(['id', 'code', 'label_fr', 'label_en', 'applies_to', 'required', 'has_expiry', 'on_documents', 'active']),
            'kinds' => AttachmentType::KINDS,
        ]);
    }

    /**
     * Create a kind of document.
     */
    public function store(AttachmentTypeRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['on_documents'] = ($data['applies_to'] ?? null) === 'company' && ($data['on_documents'] ?? false);
        $data['code'] ??= CodeFromLabel::make($data['label_fr'], fn (string $code): bool => AttachmentType::query()->where('code', $code)->exists(), 40);

        AttachmentType::create($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('attachments.types.created')]);

        return to_route('attachment-types.index');
    }

    /**
     * Update a kind of document. Its code and what it applies to never change, because documents refer to it.
     */
    public function update(AttachmentTypeRequest $request, AttachmentType $attachmentType): RedirectResponse
    {
        $data = $request->safe()->except(['code', 'applies_to']);
        $data['on_documents'] = $attachmentType->applies_to === 'company' && ($data['on_documents'] ?? false);

        $attachmentType->update($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('attachments.types.updated')]);

        return to_route('attachment-types.index');
    }
}
