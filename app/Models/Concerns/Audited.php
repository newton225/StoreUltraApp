<?php

namespace App\Models\Concerns;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Writes to the audit journal every creation, change and deletion of a record (who, when, from which
 * address and browser, what the values were before and after), through AuditLog::record() only. The action
 * is named after the model: client.created, zone.updated, product_price.deleted... Timestamps and hidden
 * attributes are never written.
 *
 * @mixin Model
 */
trait Audited
{
    public static function bootAudited(): void
    {
        static::created(function (Model $model): void {
            if (! config('security.audit_reference_data')) {
                return;
            }

            AuditLog::record(self::auditAction($model, 'created'), $model, [], self::auditValues($model, $model->getAttributes()));
        });

        static::updated(function (Model $model): void {
            if (! config('security.audit_reference_data')) {
                return;
            }

            $changes = self::auditValues($model, $model->getChanges());

            if ($changes === []) {
                return;
            }

            $before = array_intersect_key(self::auditValues($model, $model->getOriginal()), $changes);

            AuditLog::record(self::auditAction($model, 'updated'), $model, $before, $changes);
        });

        static::deleted(function (Model $model): void {
            if (! config('security.audit_reference_data')) {
                return;
            }

            AuditLog::record(self::auditAction($model, 'deleted'), $model, self::auditValues($model, $model->getAttributes()));
        });
    }

    private static function auditAction(Model $model, string $verb): string
    {
        return Str::snake(class_basename($model)).'.'.$verb;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private static function auditValues(Model $model, array $attributes): array
    {
        return array_diff_key($attributes, array_flip([
            ...$model->getHidden(),
            $model->getKeyName(),
            'created_at',
            'updated_at',
        ]));
    }
}
