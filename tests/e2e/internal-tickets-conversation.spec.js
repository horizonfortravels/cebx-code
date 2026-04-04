const { execSync } = require('node:child_process');
const { test, expect } = require('@playwright/test');

const PASSWORD = process.env.E2E_USER_PASSWORD || 'Password123!';

const USERS = {
  externalOrganizationOwner: 'e2e.c.organization_owner@example.test',
  internalOpsReadonly: 'e2e.internal.ops_readonly@example.test',
  internalSupport: 'e2e.internal.support@example.test',
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
    admin: routeMap.get('admin.login'),
    b2b: routeMap.get('b2b.login'),
  };

  return byName[portal] || (portal === 'admin' ? '/admin/login' : '/b2b/login');
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

async function openTicketsCenter(page) {
  const response = await page.goto(resolveRoutePath('internal.tickets.index', '/internal/tickets'));
  expect(response).not.toBeNull();
  expect(response.status()).toBeLessThan(500);
  await expect(page).toHaveURL(/\/internal\/tickets$/);
  await expect(page.locator('[data-testid="internal-tickets-table"]')).toBeVisible();
}

test.describe.configure({ mode: 'serial' });

test('support replies to the customer thread, adds an internal note, and external sees only the customer-visible reply', async ({ browser }) => {
  const replyBody = 'I9C browser support reply visible to the customer.';
  const noteBody = 'I9C browser internal note hidden from the customer.';

  const supportContext = await browser.newContext();
  const supportPage = await supportContext.newPage();

  await loginWith(supportPage, 'admin', USERS.internalSupport);
  await openTicketsCenter(supportPage);

  await supportPage.getByRole('link', { name: 'TKT-I9A-C-001' }).click();
  await supportPage.waitForLoadState('networkidle');

  await expect(supportPage.locator('[data-testid="internal-ticket-activity-card"]')).toBeVisible();
  await expect(supportPage.locator('[data-testid="internal-ticket-notes-card"]')).toBeVisible();
  await expect(supportPage.locator('[data-testid="internal-ticket-reply-form"]')).toBeVisible();
  await expect(supportPage.locator('[data-testid="internal-ticket-note-form"]')).toBeVisible();

  await supportPage.fill('[data-testid="internal-ticket-reply-body"]', replyBody);
  await supportPage.locator('[data-testid="internal-ticket-reply-submit"]').click();
  await supportPage.waitForLoadState('networkidle');

  await expect(supportPage.locator('body')).toContainText('The customer-visible support reply was added successfully.');
  await expect(supportPage.locator('[data-testid="internal-ticket-activity-card"]')).toContainText(replyBody);

  await supportPage.fill('[data-testid="internal-ticket-note-body"]', noteBody);
  await supportPage.locator('[data-testid="internal-ticket-note-submit"]').click();
  await supportPage.waitForLoadState('networkidle');

  await expect(supportPage.locator('body')).toContainText('The internal ticket note was added successfully.');
  await expect(supportPage.locator('[data-testid="internal-ticket-notes-card"]')).toContainText(noteBody);
  await expect(supportPage.locator('body')).not.toContainText('Internal Server Error');

  const ticketId = new URL(supportPage.url()).pathname.split('/').pop();
  expect(ticketId).toBeTruthy();

  await supportContext.close();

  const externalContext = await browser.newContext();
  const externalPage = await externalContext.newPage();

  await loginWith(externalPage, 'b2b', USERS.externalOrganizationOwner);

  const response = await externalPage.goto(
    resolveParameterizedRoute('support.show', '/support/{ticket}', { ticket: ticketId })
  );
  expect(response).not.toBeNull();
  expect(response.status()).toBeLessThan(500);

  await expect(externalPage.locator('[data-testid="external-ticket-thread-card"]')).toBeVisible();
  await expect(externalPage.locator('body')).toContainText(replyBody);
  await expect(externalPage.locator('body')).not.toContainText(noteBody);
  await expect(externalPage.locator('body')).not.toContainText('Internal Server Error');

  await externalContext.close();
});

test('internal ops_readonly can review ticket notes read-only but sees no reply or note mutation controls', async ({ page }) => {
  await loginWith(page, 'admin', USERS.internalOpsReadonly);
  await openTicketsCenter(page);

  await page.getByRole('link', { name: 'TKT-I9A-C-001' }).click();
  await page.waitForLoadState('networkidle');

  await expect(page.locator('[data-testid="internal-ticket-activity-card"]')).toBeVisible();
  await expect(page.locator('[data-testid="internal-ticket-notes-card"]')).toBeVisible();
  await expect(page.locator('[data-testid="internal-ticket-notes-card"]')).toContainText('Internal escalation note for leadership only.');
  await expect(page.locator('[data-testid="internal-ticket-reply-form"]')).toHaveCount(0);
  await expect(page.locator('[data-testid="internal-ticket-note-form"]')).toHaveCount(0);
  await expect(page.locator('body')).not.toContainText('Internal Server Error');
});
