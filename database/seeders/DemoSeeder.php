<?php

namespace Database\Seeders;

use App\Actions\Cash\RecordCashMovement;
use App\Actions\Orders\CreateOrder;
use App\Enums\ClientStatus;
use App\Enums\OrderStatus;
use App\Models\Client;
use App\Models\Delivery;
use App\Models\KitVersion;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Zone;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Fictitious data to show or try the application: eight small businesses named "[DÉMO] …" with orders at every
 * stage of the sale, their advances, two deliveries and a few cash movements. It needs no dev package (names and
 * amounts are fixed), refuses to run in production, and running it again adds nothing. It is not part of
 * `DatabaseSeeder`: only `php artisan app:seed-demo` calls it. The records go through the usual models and
 * actions, so they are traced like real ones; they cannot be removed afterwards (payments and cash movements are
 * immutable), so load them in a database kept for demonstrations.
 */
class DemoSeeder extends Seeder
{
    public const PREFIX = '[DÉMO] ';

    /** @var list<array{string, string, string}> */
    private const TPE = [
        ['Atelier du Nord', 'Koné Mamadou', 'Plombier'],
        ['Bâtiment Service Plus', 'Traoré Awa', 'Maçon'],
        ['Menuiserie Espoir', 'Yao Koffi', 'Menuisier'],
        ['Électricité Moderne', 'Coulibaly Ibrahim', 'Électricien'],
        ['Peinture & Déco', 'Bamba Salimata', 'Peintre'],
        ['Carrelage Pro', 'Diallo Seydou', 'Carreleur'],
        ['Soudure Ouest', 'N\'Guessan Paul', 'Soudeur'],
        ['Entreprise Les Bâtisseurs', 'Ouattara Fatou', 'Maçon'],
    ];

    public function run(CreateOrder $createOrder, RecordCashMovement $cash): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('The demonstration data is never loaded in production.');
        }

        if (Client::query()->where('name', 'like', self::PREFIX.'%')->exists()) {
            $this->command?->info('The demonstration data is already there.');

            return;
        }

        if (! KitVersion::query()->exists() || ! PaymentMethod::query()->exists()) {
            $this->call(DatabaseSeeder::class);
        }

        $version = KitVersion::query()->latest('id')->firstOrFail();
        $methods = PaymentMethod::query()->get();
        $zones = Zone::query()->orderBy('id')->limit(3)->get();

        if ($zones->isEmpty()) {
            $zones = collect([Zone::query()->create(['name' => 'Zone démo', 'active' => true])]);
        }

        $stages = [
            OrderStatus::Draft,
            OrderStatus::ProformaIssued,
            OrderStatus::AdvanceReceived,
            OrderStatus::AdvanceReceived,
            OrderStatus::Ordered,
            OrderStatus::Delivered,
            OrderStatus::Settled,
            OrderStatus::Delivered,
        ];

        foreach (self::TPE as $i => [$name, $manager, $trade]) {
            $client = new Client([
                'name' => self::PREFIX.$name,
                'phone' => sprintf('00 00 00 9%d 00', $i + 1),
                'zone_id' => $zones[$i % $zones->count()]->id,
                'locality' => 'Localité démo '.($i % 3 + 1),
                'contact_person' => $manager,
                'notes' => 'Donnée fictive de démonstration.',
            ]);
            $client->forceFill(['status' => ClientStatus::Active])->save();
            $client->forceFill(['reference' => sprintf('CLI-%04d', $client->id)])->save();
            $client->members()->create(['name' => $manager, 'role' => 'manager', 'trade' => $trade, 'phone' => $client->phone, 'position' => 0]);

            $order = $createOrder->handle(
                client: $client,
                version: $version,
                kitsCount: $i % 3 + 1,
                deliveryWeek: today()->startOfWeek()->addWeeks($i % 3),
                advanceAmount: 20000,
            );
            $status = $stages[$i];
            $order->forceFill(['status' => $status])->save();

            if (! in_array($status, [OrderStatus::Draft, OrderStatus::ProformaIssued], true)) {
                (new Payment)->forceFill([
                    'receipt_number' => sprintf('REC-DEMO-%04d', $i + 1),
                    'order_id' => $order->id,
                    'kind' => 'advance',
                    'amount' => 20000,
                    'payment_method_id' => $methods[$i % $methods->count()]->id,
                    'paid_on' => today(),
                ])->save();
            }

            if (in_array($status, [OrderStatus::Delivered, OrderStatus::Settled], true)) {
                (new Delivery)->forceFill([
                    'reference' => sprintf('LIV-DEMO-%04d', $i + 1),
                    'order_id' => $order->id,
                    'kits_delivered' => $order->kits_count,
                    'delivered_on' => today(),
                    'received_by' => $manager,
                ])->save();
            }
        }

        foreach ([['in', 200000, 'Apport du responsable (démo)'], ['out', 15000, 'Petites fournitures (démo)'], ['out', 35000, 'Retrait pour la banque (démo)']] as [$direction, $amount, $label]) {
            $cash->handle(['direction' => $direction, 'amount' => $amount, 'occurred_on' => today(), 'label' => $label, 'idempotency_key' => 'demo-'.md5($label)]);
        }

        $this->command?->info('Demonstration data loaded: '.count(self::TPE).' TPE with their orders, advances, deliveries and cash movements.');
    }
}
