<?php

namespace App\Support;

use Illuminate\Support\Facades\Lang;

/**
 * The legal notice, the privacy policy and the terms of use (playbook chapter 08). The sentences live in
 * the `legal` lang files and say what the application really does; the identity of the company comes from the
 * "Mon entreprise" page, the host and the country from `config/legal.php`, and every duration from the same
 * settings the code enforces (access journal, session, backups, journal copies), so that a text cannot drift from
 * the behaviour (PRIV-03, PRIV-04, PRIV-05). A blank value is printed as « [à compléter] » and is reported by the
 * production check and the health screen.
 */
class LegalTexts
{
    /** The three texts, in the order they are linked. */
    public const DOCUMENTS = ['notice', 'privacy', 'terms'];

    /**
     * The values that must not stay blank on a production server.
     *
     * @var list<string>
     */
    private const REQUIRED = ['company', 'address', 'email', 'director', 'host', 'host_address', 'host_country', 'mail_provider', 'privacy_email', 'country', 'law', 'authority'];

    public function __construct(private readonly CompanyProfile $company) {}

    /**
     * One text, ready to show: its title, its version and its sections with every placeholder filled.
     *
     * @return array{key: string, title: string, version: string, sections: list<array<string, mixed>>}
     */
    public function document(string $name, string $locale): array
    {
        $text = Lang::get("legal.{$name}", [], $locale);
        $values = $this->values($locale);

        /** @var array{title: string, sections: list<array<string, mixed>>} $filled */
        $filled = $this->fill(is_array($text) ? $text : ['title' => $name, 'sections' => []], $values);

        return ['key' => $name, 'title' => $filled['title'], 'version' => (string) config('legal.version'), 'sections' => $filled['sections']];
    }

    /**
     * Whether a lawyer has approved the current version of the texts.
     */
    public function reviewConfirmed(): bool
    {
        return (string) config('legal.reviewed_version') === (string) config('legal.version');
    }

    /**
     * The required values that are still blank (names, not texts), for the production check and the health screen.
     *
     * @return list<string>
     */
    public function missing(): array
    {
        $values = $this->raw();

        return array_values(array_filter(self::REQUIRED, fn (string $name): bool => ($values[$name] ?? '') === ''));
    }

    /**
     * The required values that come from config/legal.php and are blank. They need no database, so the check that
     * every production request goes through can use them.
     *
     * @return list<string>
     */
    public function missingFromConfig(): array
    {
        return array_values(array_filter(
            ['host' => 'host_name', 'host_address' => 'host_address', 'host_country' => 'host_country', 'mail_provider' => 'mail_provider', 'country' => 'country', 'law' => 'data_protection_law', 'authority' => 'data_protection_authority'],
            fn (string $key): bool => (string) config("legal.{$key}") === '',
        ));
    }

    /**
     * Every value a text can use, with a blank shown as the placeholder « [à compléter] ».
     *
     * @return array<string, string>
     */
    public function values(string $locale): array
    {
        $blank = (string) Lang::get('legal.blank', [], $locale);
        $values = [];

        foreach ($this->raw($locale) as $name => $value) {
            $values['{'.$name.'}'] = $value === '' ? (in_array($name, self::REQUIRED, true) ? $blank : '—') : $value;
        }

        return $values;
    }

    /**
     * The values as they are, blank when not set.
     *
     * @return array<string, string>
     */
    private function raw(string $locale = 'fr'): array
    {
        $company = $this->company->forDocuments($locale);
        $director = trim(implode(', ', array_filter([$company['signatory'], $company['signatory_title']])));

        return [
            'company' => (string) $company['name'],
            'legal_form' => (string) $company['legal_form'],
            'capital' => (string) $company['capital'],
            'address' => (string) $company['address'],
            'phone' => (string) $company['phone'],
            'email' => (string) $company['email'],
            'website' => (string) $company['website'],
            'identifiers' => implode(' ; ', $company['identifiers']),
            'director' => $director,
            'host' => (string) config('legal.host_name'),
            'host_address' => (string) config('legal.host_address'),
            'host_country' => (string) config('legal.host_country'),
            'mail_provider' => (string) config('legal.mail_provider'),
            'privacy_email' => (string) (config('legal.privacy_email') ?: $company['email']),
            'country' => (string) config('legal.country'),
            'law' => (string) config('legal.data_protection_law'),
            'authority' => (string) config('legal.data_protection_authority'),
            'version' => (string) config('legal.version'),
            'access_days' => (string) config('security.access_log_retention_days'),
            'session_minutes' => (string) config('session.lifetime'),
            'backup_days' => (string) config('backup.retention.max_days'),
            'audit_months' => (string) config('security.audit_archive_retention_months'),
        ];
    }

    /**
     * Replace the placeholders in every string of a text, whatever its depth.
     *
     * @param  array<array-key, mixed>  $text
     * @param  array<string, string>  $values
     * @return array<array-key, mixed>
     */
    private function fill(array $text, array $values): array
    {
        return array_map(fn (mixed $item): mixed => match (true) {
            is_array($item) => $this->fill($item, $values),
            is_string($item) => strtr($item, $values),
            default => $item,
        }, $text);
    }
}
