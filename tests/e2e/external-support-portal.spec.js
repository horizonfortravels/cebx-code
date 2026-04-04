const { execSync } = require('node:child_process');
const { test, expect } = require('@playwright/test');

const PASSWORD = process.env.E2E_USER_PASSWORD || 'Password123!';

const USERS = {
  externalOrganizationOwner: 'e2e.c.organization_owner@example.test',
  externalOtherOrganizationOwner: 'e2e.d.organization_owner@example.test',
};

function loadRouteMap() {
  const raw = execSync('php artisan route:list --json', { encoding: 'utf8' });
  const rows = JSON.parse(raw);
  const map = new Map();

  for (const row of rows) {
    if (row && row.name && row.uri) {
      map.set(String(row.name), `/${String(row.uri).replace(/^\/+/, '')}`);
    }
  }

  return map;
}

const routeMap = loadRouteMap();

function resolveLoginPath(portal) {
  const byName = {
    b2b: routeMap.get('b2b.login'),
  };

  return byName[portal] || '/b2b/login';
}

function resolveRoutePath(routeName, fallbackPath) {
  return routeMap.get(routeName) || fallbackPath;
}

function resolveParameterizedRoute(routeName, fallbackPath, params) {
  let path = resolveRoutePath(routeName, fallbackPath);

  for (const [key, value] of Object.entries(params)) {
    path = path.replace(`{${key}}`, value);
  }

  return path;
}

async function openPortalLogin(page, portal) {
  const response = await page.goto(resolveLoginPath(portal));
  expect(response).not.toBeNull();
  expect(response.status()).toBeLessThan(500);
  await expect(page.locator('input[name="email"]')).toBeVisible();
  await expect(page.locator('input[name="password"]')).toBeVisible();
}

async function loginWith(page, portal, email, password = PASSWORD) {
  await openPortalLogin(page, portal);
  await page.fill('input[name="email"]', email);
  await page.fill('input[name="password"]', password);
  await Promise.all([
    page.waitForURL(url => !/\/(admin\/login|b2b\/login|b2c\/login|login)$/.test(url.pathname), { timeout: 15000 }),
    page.locator('button[type="submit"]').click(),
  ]);
  await page.waitForLoadState('networkidle');
}

test('external organization user can create and view an in-scope help request while cross-tenant access is denied', async ({ browser }) => {
  const subject = `I9 external browser ticket ${Date.now()}`;
  const body = 'External organization owner created a browser help request for the final I9 verification.';

  const ownerContext = await browser.newContext();
  const ownerPage = await ownerContext.newPage();

  await loginWith(ownerPage, 'b2b', USERS.externalOrganizationOwner);

  const indexResponse = await ownerPage.goto(resolveRoutePath('support.index', '/support'));
  expect(indexResponse).not.toBeNull();
  expect(indexResponse.status()).toBeLessThan(500);
  await expect(ownerPage.locator('table')).toBeVisible();

  await ownerPage.locator('[data-modal-open="newTicket"]').click();
  await ownerPage.fill('input[name="subject"]', subject);
  await ownerPage.selectOption('select[name="category"]', 'shipment');
  await ownerPage.selectOption('select[name="priority"]', 'high');
  await ownerPage.fill('textarea[name="body"]', body);
  await Promise.all([
    ownerPage.waitForURL(url => /\/support\/[^/]+$/.test(url.pathname), { timeout: 15000 }),
    ownerPage.locator('form[action$="/support"]').evaluate(form => form.requestSubmit()),
  ]);
  await ownerPage.waitForLoadState('networkidle');

  await expect(ownerPage.locator('[data-testid="external-ticket-reply-form"]')).toBeVisible();
  await expect(ownerPage.locator('body')).toContainText(subject);
  await expect(ownerPage.locator('body')).toContainText(body);
  await expect(ownerPage.locator('body')).not.toContainText('Internal escalation note for leadership only.');

  const ticketId = new URL(ownerPage.url()).pathname.split('/').pop();
  expect(ticketId).toBeTruthy();

  const otherContext = await browser.newContext();
  const otherPage = await otherContext.newPage();

  await loginWith(otherPage, 'b2b', USERS.externalOtherOrganizationOwner);

  const deniedResponse = await otherPage.goto(
    resolveParameterizedRoute('support.show', '/support/{ticket}', { ticket: ticketId })
  );
  expect(deniedResponse).not.toBeNull();
  expect(deniedResponse.status()).toBe(404);
  await expect(otherPage.locator('[data-testid="external-ticket-thread-card"]')).toHaveCount(0);
  await expect(otherPage.locator('body')).not.toContainText('Internal Server Error');

  await otherContext.close();
  await ownerContext.close();
});
