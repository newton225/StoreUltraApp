import assert from 'node:assert/strict';
import { after, before, describe, test } from 'node:test';
import { chromium } from 'playwright';
import { OWNER, READER, startApp } from './support/app.mjs';

/**
 * The critical journeys, in a real browser, against an application started on its own with its own data
 * (playbook TEST-31, TEST-32). Elements are designated by `data-test` (or by the `name` of a field and the
 * address of a link), never by their text or their style. Run with `npm run e2e` after `npm run build`.
 */

let app;
let browser;
let owner;

before(async () => {
    app = await startApp();
    browser = await chromium.launch();

    // One sign-in for the owner, shared by the journeys: the sign-in page limits the attempts a minute.
    owner = await signedIn(OWNER);
});

after(async () => {
    await owner?.context.close();
    await browser?.close();
    await app?.stop();
});

/** A page of a fresh browser context (no cookie shared with the other journeys), signed in as someone. */
async function signedIn(user) {
    const context = await browser.newContext();
    const page = await context.newPage();

    await page.goto(`${app.url}/login`);
    await page.fill('input[name=email]', user.email);
    await page.fill('input[name=password]', user.password);
    await page.click('[data-test=login-button]');

    try {
        await page.waitForURL(/\/dashboard/);
    } catch (error) {
        // Say what the page showed instead: a refused sign-in explains itself in its text.
        const shown = (await page.locator('body').innerText()).slice(0, 300);
        throw new Error(`sign-in as ${user.email} did not reach the dashboard (at ${page.url()}): ${shown}`, { cause: error });
    }

    return { context, page };
}

/** Confirm the dialog that every sensitive action opens. */
async function confirm(page, trigger) {
    await trigger.click();
    await page.locator('[data-test=confirm-action-confirm]').click();
}

describe('access', () => {
    test('a visitor is sent to the sign-in page, a wrong password is refused, the owner signs in and out', async () => {
        const context = await browser.newContext();
        const page = await context.newPage();

        await page.goto(`${app.url}/clients`);
        await page.waitForURL(/\/login/);

        await page.fill('input[name=email]', OWNER.email);
        await page.fill('input[name=password]', 'not-the-password');
        await page.click('[data-test=login-button]');
        await page.waitForLoadState('networkidle');
        assert.match(page.url(), /\/login/);

        // After signing in, the visitor lands where they were going (the clients), not on a fixed page.
        await page.fill('input[name=password]', OWNER.password);
        await page.click('[data-test=login-button]');
        await page.waitForURL(/\/clients/);

        await page.locator('[data-test=sidebar-menu-button]').click();
        await page.locator('[data-test=logout-button]').click();
        await page.waitForURL(/\/login|\/$/);

        await page.goto(`${app.url}/orders`);
        await page.waitForURL(/\/login/);

        await context.close();
    });
});

describe('the sale', () => {
    test('an advance is recorded from the order and appears on it', async () => {
        const context = owner.context;
        const page = await context.newPage();

        await page.goto(`${app.url}/orders/1`);
        await page.locator('[data-test=tab-payments]').click();
        // The panel that holds the payment form (the tab has other panels).
        // (open already when an advance is due: only open it when it is closed).
        const panel = page.locator(
            'section:has(input[name=amount]) [data-test=collapsible-trigger]',
        );

        if ((await panel.getAttribute('aria-expanded')) !== 'true') {
            await panel.click();
        }

        await page.fill('input[name=amount]', '40000');
        await page.selectOption('select[name=payment_method_id]', { index: 1 });
        await confirm(
            page,
            page.locator('form [data-test=confirm-action]').first(),
        );

        await page
            .locator('[data-test=payment-row][data-amount="40000"]')
            .waitFor();

        await page.close();
    });

    test('a proforma is issued from the order and downloads as a real PDF', async () => {
        const context = owner.context;
        const page = await context.newPage();

        await page.goto(`${app.url}/orders/1`);
        await page.locator('[data-test=tab-documents]').click();

        const proforma = page.locator('[data-test=document-proforma]');
        await confirm(
            page,
            proforma.locator('[data-test=confirm-action]').first(),
        );

        const link = proforma.locator('a[href*="/download"]');
        await link.waitFor();

        const response = await context.request.get(
            new URL(await link.getAttribute('href'), app.url).toString(),
        );

        assert.equal(response.status(), 200);
        assert.match(response.headers()['content-type'], /application\/pdf/);
        assert.equal((await response.body()).subarray(0, 4).toString(), '%PDF');

        await page.close();
    });
});

describe('rights', () => {
    test('a profile with only the clients menu sees only that, and is refused the rest', async () => {
        const { context, page } = await signedIn(READER);

        await page.goto(`${app.url}/clients`);
        assert.equal(await page.locator('a[href$="/clients"]').count() > 0, true);

        for (const hidden of ['/orders', '/cash', '/purchases', '/expenses']) {
            assert.equal(
                await page.locator(`nav a[href$="${hidden}"], aside a[href$="${hidden}"]`).count(),
                0,
                `the menu must not offer ${hidden}`,
            );
        }

        // Not granted: no "new client" button, and the server refuses it anyway.
        assert.equal(await page.locator('[data-test=client-new]').count(), 0);
        assert.equal((await page.goto(`${app.url}/clients/create`)).status(), 403);
        assert.equal((await page.goto(`${app.url}/cash`)).status(), 403);

        // Granted: the edit button is there on a client's file.
        await page.goto(`${app.url}/clients/1`);
        await page.locator('[data-test=client-edit]').waitFor();

        await context.close();
    });
});

describe('administration', () => {
    test('the owner creates a profile with a menu, and it appears in the list', async () => {
        const context = owner.context;
        const page = await context.newPage();

        await page.goto(`${app.url}/profiles/create`);
        await page.fill('input[name=label_fr]', 'Profil créé en E2E');
        await page.fill('input[name=label_en]', 'Profile made in E2E');
        await page
            .locator('button[role=checkbox][value=manage_clients]')
            .first()
            .click();
        await page.locator('[data-test=profile-save]').click();

        await page.waitForURL(/\/profiles$/);
        await page.getByText('Profil créé en E2E').first().waitFor();

        await page.close();
    });
});

describe('public pages and lists', () => {
    test('the legal texts are public and switch language', async () => {
        const context = await browser.newContext();
        const page = await context.newPage();

        await page.goto(`${app.url}/legal/privacy`);
        await page.locator('h1').waitFor();
        const french = await page.locator('h1').innerText();

        for (const document of ['notice', 'terms']) {
            assert.ok(
                (await page.locator(`nav a[href$="/legal/${document}"]`).count()) > 0,
            );
        }

        await page.locator('[data-test=locale-en]').click();
        await page.waitForLoadState('networkidle');
        assert.notEqual(await page.locator('h1').innerText(), french);

        await context.close();
    });

    test('a column header sorts the list both ways and keeps the choice in the address', async () => {
        const context = owner.context;
        const page = await context.newPage();

        await page.goto(`${app.url}/clients`);
        const header = page.locator('th[aria-sort] button').first();

        await header.click();
        await page.waitForURL(/sort=.*direction=asc|direction=asc.*sort=/);
        assert.equal(
            await page.locator('th[aria-sort="ascending"]').count(),
            1,
        );

        await page.locator('th[aria-sort="ascending"] button').click();
        await page.waitForURL(/direction=desc/);
        assert.equal(
            await page.locator('th[aria-sort="descending"]').count(),
            1,
        );

        await page.close();
    });
});
