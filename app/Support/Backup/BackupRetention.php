<?php

namespace App\Support\Backup;

use Carbon\CarbonImmutable;

/**
 * Which backup copies to delete: every copy of the last days, then the newest of each day, of each week and of
 * each month, and nothing beyond the maximum age. Pure, so it is easy to check.
 */
class BackupRetention
{
    /**
     * @param  array{all_days: int, daily_days: int, weekly_days: int, max_days: int}  $rules
     */
    public function __construct(private readonly array $rules) {}

    /**
     * The copies to delete.
     *
     * @param  array<string, CarbonImmutable>  $copies  the date of each copy, by name
     * @return list<string>
     */
    public function toDelete(array $copies, CarbonImmutable $now): array
    {
        uasort($copies, fn (CarbonImmutable $a, CarbonImmutable $b): int => $b <=> $a);

        $kept = [];
        $delete = [];

        foreach ($copies as $name => $date) {
            $age = (int) floor($date->diffInDays($now, true));

            $bucket = match (true) {
                $age <= $this->rules['all_days'] => $name,
                $age <= $this->rules['daily_days'] => 'day:'.$date->format('Y-m-d'),
                $age <= $this->rules['weekly_days'] => 'week:'.$date->format('o-W'),
                $age <= $this->rules['max_days'] => 'month:'.$date->format('Y-m'),
                default => null,
            };

            // The list goes from the newest copy: the first one met in a bucket is the one kept.
            if ($bucket === null || isset($kept[$bucket])) {
                $delete[] = (string) $name;

                continue;
            }

            $kept[$bucket] = true;
        }

        return $delete;
    }
}
