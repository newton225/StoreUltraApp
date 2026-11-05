<?php

namespace App\Support;

/**
 * The fingerprint of an audit entry. Each entry's hash covers the hash of the one before it and its
 * own content, so removing or changing any entry breaks every hash after it, and the head of the chain
 * kept apart shows when the last entries were removed. The address and the browser are not part of
 * it: they may have to be erased one day without breaking the chain.
 */
class AuditChain
{
    /**
     * What the first entry follows.
     */
    public const START = '0000000000000000000000000000000000000000000000000000000000000000';

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public static function hash(?string $previous, ?int $userId, string $action, string $subjectType, int $subjectId, ?array $before, ?array $after, string $createdAt): string
    {
        $content = [
            'user_id' => $userId,
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'before' => self::canonical($before),
            'after' => self::canonical($after),
            'created_at' => $createdAt,
        ];

        return hash('sha256', ($previous ?? self::START).'|'.json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    /**
     * The same content always gives the same text: keys are sorted and an empty array is nothing.
     *
     * @param  array<array-key, mixed>|null  $value
     * @return array<array-key, mixed>|null
     */
    private static function canonical(?array $value): ?array
    {
        if ($value === null || $value === []) {
            return null;
        }

        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = self::canonical($item) ?? [];
            }
        }

        if (! array_is_list($value)) {
            ksort($value);
        }

        return $value;
    }
}
