const { execSync } = require('node:child_process');
const { test, expect } = require('@playwright/test');

const PASSWORD = process.env.E2E_USER_PASSWORD || 'Password123!';

const USERS = {
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
  };

  return byName[portal] || '/admin/login';
}

function routeLinkSelector(routeName, fallbackPath) {
  return `a[href$="${routeMap.get(routeName) || fallbackPath}"]`;
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
  await page.locator('button[type="submit"]').click();
  await page.waitForLoadState('networkidle');
}

async function openTicketsCenter(page) {
  await expect(page.locator(routeLinkSelector('internal.tickets.index', '/internal/tickets')).first()).toBeVisible();
  await page.locator(routeLinkSelector('internal.tickets.index', '/internal/tickets')).first().click();
  await page.waitForLoadState('networkidle');
  await expect(page).toHaveURL(/\/internal\/tickets$/);
  await expect(page.locator('[data-testid="internal-tickets-table"]')).toBeVisible();
}

async function openShipmentsCenter(page) {
  await expect(page.locator(routeLinkSelector('internal.shipments.index', '/internal/shipments')).first()).toBeVisible();
  await page.locator(routeLinkSelector('internal.shipments.index', '/internal/shipments')).first().click();
  await page.waitForLoadState('networkidle');
  await expect(page).toHaveURL(/\/internal\/shipments$/);
  await expect(page.locator('[data-testid="internal-shipments-table"]')).toBeVisible();
}

test.describe.configure({ mode: 'serial' });

test('internal support can create one general ticket from the helpdesk center', async ({ page }) => {
  await loginWith(page, 'admin', USERS.internalSupport);
  await openTicketsCenter(page);

  await expect(page.locator('[data-testid="internal-tickets-create-link"]')).toBeVisible();
  await page.locator('[data-testid="internal-tickets-create-link"]').click();
  await page.waitForLoadState('networkidle');

  await expect(page.locator('[data-testid="internal-ticket-create-form"]')).toBeVisible();
  await page.locator('[data-testid="internal-ticket-account-select"]').selectOption({
    label: 'E2E Account A - Individual - e2e-account-a',
  });
  await page.fill('#ticket-subject', 'I9B Browser General Ticket');
  await page.selectOption('#ticket-category', 'general');
  await page.selectOption('#ticket-priority', 'medium');
  await page.fill('#ticket-description', 'Support created a safe general internal helpdesk ticket from the center.');
  await page.locator('[data-testid="internal-ticket-create-submit"]').click();
  await page.waitForLoadState('networkidle');

  await expect(page.locator('[data-testid="internal-ticket-summary-card"]')).toBeVisible();
  await expect(page.locator('[data-testid="internal-ticket-context-card"]')).toBeVisible();
  await expect(page.locator('body')).toContainText('I9B Browser General Ticket');
  await expect(page.locator('body')).toContainText('E2E Account A');
  await expect(page.locator('body')).toContainText('No linked shipment');
  await expect(page.locator('[data-testid="internal-ticket-shipment-link"]')).toHaveCount(0);
  await expect(page.locator('body')).not.toContainText('Internal Server Error');
});

test('internal support can create one shipment-linked ticket from shipment detail', async ({ page }) => {
  await loginWith(page, 'admin', USERS.internalSupport);
  await openShipmentsCenter(page);

  await page.getByRole('link', { name: 'SHP-I5A-C-001' }).click();
  await page.waitForLoadState('networkidle');
  await expect(page.locator('[data-testid="internal-shipment-summary-card"]')).toBeVisible();
  await expect(page.locator('[data-testid="internal-shipment-create-linked-ticket-link"]')).toBeVisible();

  await page.locator('[data-testid="internal-shipment-create-linked-ticket-link"]').click();
  await page.waitForLoadState('networkidle');

  await expect(page.locator('[data-testid="internal-ticket-create-form"]')).toBeVisible();
  await expect(page.locator('[data-testid="internal-ticket-linked-account-card"]')).toContainText('E2E Account C');
  await expect(page.locator('[data-testid="internal-ticket-linked-shipment-card"]')).toContainText('SHP-I5A-C-001');
  await page.fill('#ticket-subject', 'I9B Browser Shipment Ticket');
  await page.selectOption('#ticket-category', 'shipping');
  await page.selectOption('#ticket-priority', 'high');
  await page.fill('#ticket-description', 'Support created a shipment-linked internal ticket from the shipment detail surface.');
  await page.locator('[data-testid="internal-ticket-create-submit"]').click();
  await page.waitForLoadState('networkidle');

  await expect(page.locator('[data-testid="internal-ticket-summary-card"]')).toBeVisible();
  await expect(page.locator('[data-testid="internal-ticket-context-card"]')).toBeVisible();
  await expect(page.locator('[data-testid="internal-ticket-shipment-link"]')).toBeVisible();
  await expect(page.locator('[data-testid="internal-ticket-account-link"]')).toBeVisible();
  await expect(page.locator('body')).toContainText('I9B Browser Shipment Ticket');
  await expect(page.locator('body')).toContainText('E2E Account C');
  await expect(page.locator('body')).toContainText('SHP-I5A-C-001');
  await expect(page.locator('body')).not.toContainText('Internal Server Error');
});
