<?php

namespace App\Actions\Attachments;

use App\Models\Attachment;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DeleteAttachment
{
    /**
     * Remove a document and its file. The audit log keeps what it was. The file is only erased once
     * the document is gone for good: a failure leaves both in place, never a document without its file.
     */
    public function handle(Attachment $attachment, ?User $user = null): void
    {
        DB::transaction(function () use ($attachment, $user): void {
            AuditLog::record('attachment.deleted', $attachment, ['number' => $attachment->number, 'type' => $attachment->attachment_type_id], [], $user);

            $attachment->delete();
        });

        if ($attachment->file_path !== null) {
            Storage::disk('local')->delete($attachment->file_path);
        }
    }
}
