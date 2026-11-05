<?php

namespace App\Actions\Attachments;

use App\Models\Attachment;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\UploadedFiles;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class RecordAttachment
{
    public function __construct(private UploadedFiles $uploads) {}

    /**
     * Keep an administrative document. The number is enough; the file is optional and stays private.
     * The document and its trace are written together, and a file stored for a document that could
     * not be kept is removed again.
     *
     * @param  array<string, mixed>  $data  attachment_type_id, owner_id, number, issued_on, expires_on, notes.
     */
    public function handle(array $data, ?UploadedFile $file = null, ?User $user = null): Attachment
    {
        $path = $file === null ? null : $this->uploads->store($file, 'attachments');

        try {
            return DB::transaction(function () use ($data, $path, $user): Attachment {
                $attachment = Attachment::create([
                    ...$data,
                    'file_path' => $path,
                    'created_by' => $user?->id,
                ]);

                AuditLog::record('attachment.recorded', $attachment, [], ['number' => $attachment->number, 'type' => $attachment->attachment_type_id], $user);

                return $attachment;
            });
        } catch (Throwable $exception) {
            if ($path !== null) {
                Storage::disk('local')->delete($path);
            }

            throw $exception;
        }
    }
}
