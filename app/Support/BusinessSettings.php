<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Contracts\Cache\Repository;
use InvalidArgumentException;

class BusinessSettings
{
    private const CACHE_KEY = 'business-settings';

    public function __construct(private Repository $cache) {}

    /**
     * Get the typed value of a setting: the stored one, or the default from the configuration.
     */
    public function get(string $key): mixed
    {
        $definition = $this->definition($key);
        $stored = $this->stored();

        return array_key_exists($key, $stored)
            ? $this->cast($definition['type'], $stored[$key])
            : $definition['default'];
    }

    /**
     * Store the value of a setting.
     */
    public function set(string $key, mixed $value): void
    {
        $definition = $this->definition($key);

        Setting::updateOrCreate(
            ['key' => $key],
            ['value' => $this->serialize($definition['type'], $value)],
        );

        $this->cache->forget(self::CACHE_KEY);
    }

    /**
     * Every setting with its current value.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return collect($this->definitions())
            ->mapWithKeys(fn (array $definition, string $key): array => [$key => $this->get($key)])
            ->all();
    }

    /**
     * The definitions declared in the configuration.
     *
     * @return array<string, array<string, mixed>>
     */
    public function definitions(): array
    {
        return config('business.settings');
    }

    /**
     * @return array<string, mixed>
     */
    private function definition(string $key): array
    {
        return $this->definitions()[$key]
            ?? throw new InvalidArgumentException("Unknown setting [{$key}].");
    }

    /**
     * @return array<string, string|null>
     */
    private function stored(): array
    {
        return $this->cache->rememberForever(
            self::CACHE_KEY,
            fn (): array => Setting::query()->pluck('value', 'key')->all(),
        );
    }

    private function cast(string $type, ?string $value): int|bool|string|null
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'integer' => (int) $value,
            'boolean' => $value === '1',
            default => $value,
        };
    }

    private function serialize(string $type, mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return match ($type) {
            'boolean' => $value ? '1' : '0',
            default => (string) $value,
        };
    }
}
