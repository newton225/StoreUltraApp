<?php

use App\Models\Client;
use App\Models\ClientMember;
use App\Models\User;
use App\Models\Zone;

/**
 * Builds a small xlsx (one sheet) from rows of cells, with shared strings like Excel writes them.
 *
 * @param  list<list<string>>  $rows
 */
function tpeWorkbook(array $rows): string
{
    $strings = [];
    $xmlRows = '';

    foreach ($rows as $r => $cells) {
        $xmlRows .= '<row r="'.($r + 1).'">';

        foreach ($cells as $c => $text) {
            if ($text === '') {
                continue;
            }

            $strings[] = $text;
            $xmlRows .= '<c r="'.chr(65 + $c).($r + 1).'" t="s"><v>'.(count($strings) - 1).'</v></c>';
        }

        $xmlRows .= '</row>';
    }

    $path = tempnam(sys_get_temp_dir(), 'tpe').'.xlsx';
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE);
    $zip->addFromString('xl/workbook.xml', '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="TPE Fronan" sheetId="1" r:id="rId1"/></sheets></workbook>');
    $zip->addFromString('xl/_rels/workbook.xml.rels', '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Target="worksheets/sheet1.xml"/></Relationships>');
    $zip->addFromString('xl/sharedStrings.xml', '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'.implode('', array_map(fn (string $s): string => '<si><t>'.htmlspecialchars($s, ENT_XML1).'</t></si>', $strings)).'</sst>');
    $zip->addFromString('xl/worksheets/sheet1.xml', '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'.$xmlRows.'</sheetData></worksheet>');
    $zip->close();

    return $path;
}

function tpeRows(): array
{
    return [
        ['LISTE DES TPE DE FRONAN'],
        ['N°', 'Nom et Prénoms', 'Rôle', 'Métier', 'Contact', 'Nom de la TPE', 'Village / Localité'],
        ['1', 'Konan Justin', 'Responsable', 'Plombier', '07 09 28 41 82 / 05 00 00 00 00', 'GROUPE PETCHOU', 'Darakokaha'],
        ['', 'Gueu Lazard', 'Assistant', 'Macon', '05 76 86 04 86', '', ''],
        ['', 'Kpenan Abraham', 'Assistant', 'Menuisier', '', '', ''],
        ['2', 'Mahan Diabate', 'Responsable', 'Plombier', '', 'DIABATE SERVICE', 'Fronan'],
        ['S/Total Fronan', '', '2 TPE - 4 artisans'],
    ];
}

test('the simulation writes nothing', function () {
    $path = tpeWorkbook(tpeRows());

    $this->artisan('clients:import-tpe', ['file' => $path])->assertSuccessful();

    expect(Client::count())->toBe(0)->and(Zone::count())->toBe(0);
});

test('each TPE becomes a client with its manager only, assistants are left out', function () {
    $path = tpeWorkbook(tpeRows());

    $this->artisan('clients:import-tpe', ['file' => $path, '--apply' => true])->assertSuccessful();

    $client = Client::query()->where('name', 'GROUPE PETCHOU')->firstOrFail();

    expect($client->zone->name)->toBe('Fronan')
        ->and($client->locality)->toBe('Darakokaha')
        ->and($client->phone)->toBe('07 09 28 41 82')
        ->and($client->contact_person)->toBe('Konan Justin')
        ->and($client->notes)->toContain('05 00 00 00 00')
        ->and($client->reference)->toBe(sprintf('CLI-%04d', $client->id))
        ->and($client->members->pluck('name')->all())->toBe(['Konan Justin'])
        ->and($client->members->pluck('role')->all())->toBe(['manager'])
        ->and($client->members->first()->trade)->toBe('Plombier')
        ->and(Client::count())->toBe(2)
        ->and(ClientMember::count())->toBe(2);
});

test('running the import again does not duplicate the clients', function () {
    $path = tpeWorkbook(tpeRows());

    $this->artisan('clients:import-tpe', ['file' => $path, '--apply' => true])->assertSuccessful();
    $this->artisan('clients:import-tpe', ['file' => $path, '--apply' => true])->assertSuccessful();

    expect(Client::count())->toBe(2)->and(ClientMember::count())->toBe(2)->and(Zone::count())->toBe(1);
});

test('the client page shows the trade of the manager', function () {
    $this->artisan('clients:import-tpe', ['file' => tpeWorkbook(tpeRows()), '--apply' => true]);
    $client = Client::query()->where('name', 'GROUPE PETCHOU')->firstOrFail();

    $this->actingAs(User::factory()->owner()->create())
        ->get(route('clients.show', $client))
        ->assertInertia(fn ($page) => $page->where('client.contact_trade', 'Plombier')->missing('members'));
});

test('a manager without a number, or with a number already used, gets a placeholder and the original is kept', function () {
    $rows = tpeRows();
    $rows[5][4] = '07 09 28 41 82';

    $this->artisan('clients:import-tpe', ['file' => tpeWorkbook($rows), '--apply' => true])->assertSuccessful();

    $first = Client::query()->where('name', 'GROUPE PETCHOU')->firstOrFail();
    $second = Client::query()->where('name', 'DIABATE SERVICE')->firstOrFail();

    expect($first->phone)->toBe('07 09 28 41 82')
        ->and($second->phone)->toBe('00 00 00 00 01')
        ->and($second->notes)->toContain('07 09 28 41 82')->toContain('00 00 00 00 01')
        ->and($second->members->first()->phone)->toBe('00 00 00 00 01');

    $this->artisan('clients:import-tpe', ['file' => tpeWorkbook($rows), '--apply' => true])->assertSuccessful();

    expect(Client::query()->pluck('phone')->unique()->count())->toBe(2);
});

test('a manager without any number gets a placeholder too', function () {
    $this->artisan('clients:import-tpe', ['file' => tpeWorkbook(tpeRows()), '--apply' => true])->assertSuccessful();

    $client = Client::query()->where('name', 'DIABATE SERVICE')->firstOrFail();

    expect($client->phone)->toBe('00 00 00 00 01')->and($client->notes)->toContain('aucun numéro dans le fichier');
});
