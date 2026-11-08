import { spawn, spawnSync } from 'node:child_process';
import {
    existsSync,
    mkdirSync,
    mkdtempSync,
    readdirSync,
    rmSync,
    writeFileSync,
} from 'node:fs';
import net from 'node:net';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = resolve(fileURLToPath(new URL('../../../', import.meta.url)));

/** The accounts of the fixture. They exist only in the throw-away database of a run. */
export const OWNER = {
    email: 'e2e-owner@example.test',
    password: 'E2e-Owner-Pass-2026!',
};
export const READER = {
    email: 'e2e-reader@example.test',
    password: 'E2e-Reader-Pass-2026!',
};

/**
 * What the journeys start from: an owner, a reader who only has the clients menu and the action to edit them, a
 * zone, a payment method, three clients and one order waiting for its advance. Built through the factories, in
 * the throw-away database only (playbook TEST-32).
 */
const FIXTURE = `
$owner = App\\Models\\User::factory()->owner()->create(['name' => 'Propriétaire E2E', 'email' => '${OWNER.email}', 'password' => bcrypt('${OWNER.password}'), 'email_verified_at' => now()]);
$zone = App\\Models\\Zone::firstOrCreate(['name' => 'Zone E2E'], ['active' => true]);
App\\Models\\PaymentMethod::factory()->create(['label_fr' => 'Espèces']);
foreach (['Charlie E2E', 'Alpha E2E', 'Bravo E2E'] as $name) {
    $client = App\\Models\\Client::factory()->create(['zone_id' => $zone->id, 'name' => $name]);
}
foreach (range(1, 25) as $n) {
    App\\Models\\Client::factory()->create(['zone_id' => $zone->id, 'name' => sprintf('Filler E2E %02d', $n)]);
}
App\\Models\\Client::factory()->create(['zone_id' => $zone->id, 'name' => 'Zoé Traoré E2E']);
App\\Models\\Order::factory()->for($client)->create(['status' => 'proforma_issued', 'advance_due' => 40000, 'total_sale' => 336600]);
$profile = App\\Models\\Profile::factory()->create(['label_fr' => 'Lecteur clients', 'label_en' => 'Client reader', 'permissions' => ['manage_clients', 'clients.update']]);
App\\Models\\User::factory()->create(['name' => 'Lecteur E2E', 'email' => '${READER.email}', 'password' => bcrypt('${READER.password}'), 'email_verified_at' => now(), 'profile_id' => $profile->id]);
echo 'fixture ready';
`;

const freePort = () =>
    new Promise((resolvePort, reject) => {
        const server = net.createServer();
        server.once('error', reject);
        server.listen(0, '127.0.0.1', () => {
            const { port } = server.address();
            server.close(() => resolvePort(port));
        });
    });

const artisan = (args, env) => {
    const result = spawnSync('php', ['artisan', ...args], {
        cwd: root,
        env,
        encoding: 'utf8',
    });

    if (result.status !== 0) {
        throw new Error(
            `php artisan ${args[0]} failed:\n${result.stdout}\n${result.stderr}`,
        );
    }

    return result.stdout;
};

/**
 * Start the application on its own: its own port, its own SQLite database and its own storage folder, created
 * empty for this run and deleted afterwards. It never touches the development or production data.
 */
export async function startApp() {
    // A `public/hot` file means a Vite dev server is (or was) running: the pages would load their scripts from it
    // and reload when a file changes, instead of the built ones this run is meant to test.
    if (existsSync(join(root, 'public/hot'))) {
        throw new Error(
            'public/hot exists: stop the Vite dev server (or delete public/hot if it is stale), then run `npm run build` and `npm run e2e` again.',
        );
    }

    const dir = mkdtempSync(join(tmpdir(), 'storeultra-e2e-'));

    for (const sub of [
        'app',
        'framework/cache/data',
        'framework/sessions',
        'framework/views',
        'logs',
    ]) {
        mkdirSync(join(dir, 'storage', sub), { recursive: true });
    }

    const database = join(dir, 'e2e.sqlite');
    writeFileSync(database, '');

    const port = await freePort();
    const url = `http://127.0.0.1:${port}`;
    const env = {
        ...process.env,
        APP_ENV: 'local',
        APP_DEBUG: 'false',
        APP_URL: url,
        APP_LOCALE: 'fr',
        DB_CONNECTION: 'sqlite',
        DB_DATABASE: database,
        LARAVEL_STORAGE_PATH: join(dir, 'storage'),
        SESSION_DRIVER: 'file',
        CACHE_STORE: 'file',
        QUEUE_CONNECTION: 'sync',
        MAIL_MAILER: 'array',
        LOG_CHANNEL: 'single',
        LOG_STACK: 'single',
        LOG_LEVEL: 'error',
        SECURITY_REQUIRE_OWNER_TWO_FACTOR: 'false',
    };

    artisan(['migrate', '--force', '--no-interaction'], env);
    artisan(['tinker', '--execute', FIXTURE], env);

    const server = spawn(
        'php',
        [
            // Without this, `php -S` leaves $_ENV empty and Laravel ignores LARAVEL_STORAGE_PATH: the run would then
            // write its PDFs and sessions in the real storage folder of the project.
            '-d',
            'variables_order=EGPCS',
            '-S',
            `127.0.0.1:${port}`,
            '-t',
            join(root, 'public'),
            join(
                root,
                'vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php',
            ),
        ],
        { cwd: join(root, 'public'), env, stdio: 'ignore' },
    );

    const deadline = Date.now() + 30_000;

    while (Date.now() < deadline) {
        try {
            if ((await fetch(`${url}/login`)).ok) {
                break;
            }
        } catch {
            // not listening yet
        }

        await new Promise((r) => setTimeout(r, 300));
    }

    // Prove the isolation instead of trusting it: a request must leave its session in this run's own folder.
    const sessions = join(dir, 'storage', 'framework', 'sessions');

    if (readdirSync(sessions).length === 0) {
        server.kill();
        throw new Error(
            'The application does not use the storage folder of this run: it would write in the real one. Nothing was tested.',
        );
    }

    return {
        url,
        async stop() {
            server.kill();
            await new Promise((r) => setTimeout(r, 300));
            rmSync(dir, { recursive: true, force: true, maxRetries: 5 });
        },
    };
}
