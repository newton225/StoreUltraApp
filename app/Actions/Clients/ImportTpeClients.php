<?php

namespace App\Actions\Clients;

use App\Enums\ClientStatus;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\ClientMember;
use App\Models\Zone;
use App\Support\TpeWorkbook;
use Illuminate\Support\Facades\DB;

/**
 * Registers each small business (TPE) of the workbook as a client with its manager attached (table
 * `client_members`; assistants are neither imported nor managed), one zone per sub-prefecture.
 * A second phone number, the department and the village are kept in the client's fields or notes. Every
 * client gets a phone number of its own: a manager without one, or whose number already belongs to another
 * client, receives a placeholder number (00 00 00 00 NN) and the original number goes to the notes, so the
 * owner can fix it later. A TPE already registered (same name in the same zone) is skipped, so the import
 * can be run again.
 *
 * @phpstan-import-type Tpe from TpeWorkbook
 */
class ImportTpeClients
{
    public function __construct(private readonly TpeWorkbook $workbook) {}

    /**
     * @return array{created: int, skipped: int, members: int, zones: list<string>, without_phone: list<string>, shared_phones: array<string, list<string>>, placeholders: array<string, string>}
     */
    public function handle(string $path, bool $apply): array
    {
        $tpes = $this->workbook->read($path);

        $result = ['created' => 0, 'skipped' => 0, 'members' => 0, 'zones' => [], 'without_phone' => [], 'shared_phones' => [], 'placeholders' => []];
        $usedPhones = Client::query()->pluck('phone')->filter()->flip()->all();
        $placeholder = 0;

        DB::transaction(function () use ($tpes, $apply, $path, &$result, &$usedPhones, &$placeholder): void {
            foreach ($tpes as $tpe) {
                $manager = collect($tpe['people'])->firstWhere('role', ClientMember::ROLE_MANAGER) ?? $tpe['people'][0] ?? null;

                if ($manager === null) {
                    continue;
                }

                $phones = $this->phones($manager['phone']);
                $result['zones'][$tpe['zone']] = $tpe['zone'];

                $zone = $apply
                    ? Zone::query()->whereRaw('lower(name) = ?', [mb_strtolower($tpe['zone'])])->first()
                        ?? Zone::query()->create(['name' => $tpe['zone'], 'active' => true])
                    : Zone::query()->whereRaw('lower(name) = ?', [mb_strtolower($tpe['zone'])])->first();

                $exists = $zone !== null && Client::query()
                    ->where('zone_id', $zone->id)
                    ->whereRaw('lower(name) = ?', [mb_strtolower($tpe['name'])])
                    ->exists();

                if ($exists) {
                    $result['skipped']++;

                    continue;
                }

                $original = null;

                if ($phones === []) {
                    $result['without_phone'][] = $tpe['name'];
                } elseif (isset($usedPhones[$phones[0]])) {
                    $result['shared_phones'][$phones[0]][] = $tpe['name'];
                    $original = $phones[0];
                    $phones = array_slice($phones, 1);
                }

                if ($phones === [] || $original !== null) {
                    do {
                        $fake = sprintf('00 00 00 %02d %02d', intdiv(++$placeholder, 100), $placeholder % 100);
                    } while (isset($usedPhones[$fake]));

                    array_unshift($phones, $fake);
                    $result['placeholders'][$tpe['name']] = $fake;
                }

                $usedPhones[$phones[0]] = true;

                $result['created']++;
                $result['members']++;

                if (! $apply || $zone === null) {
                    continue;
                }

                $client = new Client([
                    'name' => $tpe['name'],
                    'phone' => $phones[0],
                    'zone_id' => $zone->id,
                    'locality' => $tpe['village'],
                    'contact_person' => $manager['name'],
                    'notes' => $this->notes($tpe, array_slice($phones, 1), $original, $result['placeholders'][$tpe['name']] ?? null),
                ]);
                $client->forceFill(['status' => ClientStatus::Active])->save();
                $client->forceFill(['reference' => sprintf('CLI-%04d', $client->id)])->save();

                AuditLog::record('client.imported', $client, [], ['source' => basename($path), 'sheet' => $tpe['sheet'], 'placeholder_phone' => $result['placeholders'][$tpe['name']] ?? null]);

                $client->members()->create([
                    'name' => $manager['name'],
                    'role' => ClientMember::ROLE_MANAGER,
                    'trade' => $manager['trade'],
                    'phone' => $phones[0],
                    'position' => 0,
                ]);
            }
        });

        $result['zones'] = array_values($result['zones']);

        return $result;
    }

    /**
     * The phone numbers of a cell, which may hold several separated by "/".
     *
     * @return list<string>
     */
    private function phones(?string $cell): array
    {
        if ($cell === null) {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode('/', $cell)), fn (string $phone): bool => $phone !== ''));
    }

    /**
     * @param  Tpe  $tpe
     * @param  list<string>  $otherPhones
     */
    private function notes(array $tpe, array $otherPhones, ?string $sharedPhone, ?string $placeholder): ?string
    {
        $lines = array_filter([
            $tpe['department'] !== null ? "Département : {$tpe['department']}" : null,
            $placeholder !== null ? "Numéro provisoire ({$placeholder}) à remplacer par le vrai numéro du responsable".($sharedPhone !== null ? " ; numéro du fichier déjà utilisé par un autre client : {$sharedPhone}" : ' ; aucun numéro dans le fichier').'.' : null,
            $otherPhones !== [] ? 'Autre contact du responsable : '.implode(' / ', $otherPhones) : null,
        ]);

        return $lines === [] ? null : implode("\n", $lines);
    }
}
