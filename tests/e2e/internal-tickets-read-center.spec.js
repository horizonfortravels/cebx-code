const { execSync } = require('node:child_process');
const { test, expect } = require('@playwright/test');

const PASSWORD = process.env.E2E_USER_PASSWORD || 'Password123!';

const USERS = {
  externalOrganizationOwner: 'e2e.c.organization_owner@example.test',
  internalOpsReadonly: 'e2e.internal.ops_readonly@example.test',
  internalSupport: 'e2e.internal.support@example.test',
};

let supportDetailPath = null;

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

function routeLinkSelector(routeName, fallbackPath) {
  return `a[href$="${resolveRoutePath(routeName, fallbackPath)}"]`;
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

test('internal support can open tickets list and detail', async ({ page }) => {
  await loginWith(page, 'admin', USERS.internalSupport);
  await openTicketsCenter(page);

  await expect(page.locator('body')).toContainText('TKT-I9A-C-001');
  await expect(page.locator('body')).toContainText('Delayed organization shipment follow-up');

  await page.getByRole('link', { name: 'TKT-I9A-C-001' }).click();
  await page.waitForLoadState('networkidle');
  await expect(page.locator('[data-testid="internal-ticket-summary-card"]')).toBeVisible();
  await expect(page.locator('[data-testid="internal-ticket-context-card"]')).toBeVisible();
  await expect(page.locator('[data-testid="internal-ticket-request-card"]')).toBeVisible();
  await expect(page.locator('[data-testid="internal-ticket-activity-card"]')).toBeVisible();
  await expect(page.locator('[data-testid="internal-ticket-account-link"]')).toBeVisible();
  await expect(page.locator('[data-testid="internal-ticket-shipment-link"]')).toBeVisible();
  await expect(page.locator('body')).toContainText('Organization');
  await expect(page.locator('body')).toContainText('E2E Account C Logistics LLC');
  await expect(page.locator('body')).toContainText('SHP-I5A-C-001');
  await expect(page.locator('body')).toContainText('Support reply');
  await expect(page.locator('[data-testid="internal-ticket-notes-card"]')).toBeVisible();
  await expect(page.locator('[data-testid="internal-ticket-notes-card"]')).toContainText('Internal escalation note for leadership only.');
  await expect(page.locator('[data-testid="internal-ticket-reply-form"]')).toBeVisible();
  await expect(page.locator('[data-testid="internal-ticket-note-form"]')).toBeVisible();
  await expect(page.locator('[data-testid="internal-ticket-status-form"]')).toHaveCount(0);
  await expect(page.locator('[data-testid="internal-ticket-assignment-form"]')).toHaveCount(0);
  await expect(page.locator('[data-testid="internal-ticket-workflow-activity-card"]')).toHaveCount(0);
  await expect(page.locator('body')).not.toContainText('Internal Server Error');
  supportDetailPath = new URL(page.url()).pathname;
});

test('internal ops_readonly can open tickets list and detail read-only', async ({ page }) => {
  await loginWith(page, 'admin', USERS.internalOpsReadonly);
  await openTicketsCenter(page);

  await page.getByRole('link', { name: 'TKT-I9A-C-001' }).click();
  await page.waitForLoadState('networkidle');
  await expect(page.locator('[data-testid="internal-ticket-summary-card"]')).toBeVisible();
  await expect(page.locator('[data-testid="internal-ticket-context-card"]')).toBeVisible();
  await expect(page.locator('[data-testid="internal-ticket-request-card"]')).toBeVisible();
  await expect(page.locator('[data-testid="internal-ticket-activity-card"]')).toBeVisible();
  await expect(page.locator('[data-testid="internal-ticket-notes-card"]')).toBeVisible();
  await expect(page.locator('[data-testid="internal-ticket-notes-card"]')).toContainText('Internal escalation note for leadership only.');
  await expect(page.locator('[data-testid="internal-ticket-account-link"]')).toHaveCount(0);
  await expect(page.locator('[data-testid="internal-ticket-shipment-link"]')).toBeVisible();
  await expect(page.locator('[data-testid="internal-ticket-reply-form"]')).toHaveCount(0);
  await expect(page.locator('[data-testid="internal-ticket-note-form"]')).toHaveCount(0);
  await expect(page.locator('[data-testid="internal-ticket-status-form"]')).toHaveCount(0);
  await expect(page.locator('[data-testid="internal-ticket-assignment-form"]')).toHaveCount(0);
  await expect(page.locator('[data-testid="internal-ticket-workflow-activity-card"]')).toHaveCount(0);
  await expect(page.locator('body')).toContainText('SHP-I5A-C-001');
  await expect(page.locator('body')).not.toContainText('Internal Server Error');
});

test('external organization user is denied from internal ticket routes', async ({ page }) => {
  await loginWith(page, 'b2b', USERS.externalOrganizationOwner);

  const response = await page.goto(resolveRoutePath('internal.tickets.index', '/internal/tickets'));
  expect(response).not.toBeNull();
  expect(response.status()).toBe(403);
  await expect(page.locator('.panel')).toBeVisible();
  await expect(page.locator('.panel .meta')).toContainText('403');
  await expect(page.locator('[data-testid="internal-tickets-table"]')).toHaveCount(0);
  await expect(page.locator('body')).not.toContainText('Internal Server Error');

  if (supportDetailPath) {
    const detailResponse = await page.goto(supportDetailPath);
    expect(detailResponse).not.toBeNull();
    expect(detailResponse.status()).toBe(403);
    await expect(page.locator('.panel')).toBeVisible();
    await expect(page.locator('.panel .meta')).toContainText('403');
    await expect(page.locator('[data-testid="internal-ticket-summary-card"]')).toHaveCount(0);
    await expect(page.locator('body')).not.toContainText('Internal Server Error');
  }
});
