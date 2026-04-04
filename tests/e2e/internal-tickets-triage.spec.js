const { execSync } = require('node:child_process');
const { test, expect } = require('@playwright/test');

const PASSWORD = process.env.E2E_USER_PASSWORD || 'Password123!';

const USERS = {
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

test.describe.configure({ mode: 'serial' });

test('internal support can triage the queue and update ticket priority and category', async ({ page }) => {
  await loginWith(page, 'admin', USERS.internalSupport);
  await openTicketsCenter(page);

  await page.locator('[data-testid="internal-ticket-filter-status"]').selectOption('waiting_agent');
  await page.locator('[data-testid="internal-ticket-filter-priority"]').selectOption('high');
  await page.getByRole('button', { name: 'Apply' }).click();
  await page.waitForLoadState('networkidle');

  await expect(page.locator('[data-testid="internal-tickets-table"]')).toContainText('TKT-I9A-C-001');
  await expect(page.locator('[data-testid="internal-tickets-table"]')).not.toContainText('TKT-I9A-A-001');

  await page.getByRole('link', { name: 'TKT-I9A-C-001' }).click();
  await page.waitForLoadState('networkidle');

  await expect(page.locator('[data-testid="internal-ticket-triage-form"]')).toBeVisible();
  await page.locator('[data-testid="internal-ticket-priority-select"]').selectOption('urgent');
  await page.locator('[data-testid="internal-ticket-category-select"]').selectOption('technical');
  await page.fill('[data-testid="internal-ticket-triage-note"]', 'Support triaged the seeded ticket into the technical queue for follow-up.');
  await page.locator('[data-testid="internal-ticket-triage-submit"]').click();
  await page.waitForLoadState('networkidle');

  await expect(page.locator('body')).toContainText('The ticket triage details were updated successfully.');
  await expect(page.locator('[data-testid="internal-ticket-summary-card"]')).toContainText('Urgent');
  await expect(page.locator('[data-testid="internal-ticket-summary-card"]')).toContainText('Technical');
  await expect(page.locator('[data-testid="internal-ticket-notes-card"]')).toContainText('Triage update: Priority High -> Urgent; Category Shipping -> Technical.');
  await expect(page.locator('body')).not.toContainText('Internal Server Error');
});

test('internal ops_readonly sees queue triage filters but no ticket mutation controls', async ({ page }) => {
  await loginWith(page, 'admin', USERS.internalOpsReadonly);
  await openTicketsCenter(page);

  await expect(page.locator('[data-testid="internal-ticket-filter-status"]')).toBeVisible();
  await expect(page.locator('[data-testid="internal-ticket-filter-priority"]')).toBeVisible();
  await expect(page.locator('[data-testid="internal-ticket-filter-account"]')).toBeVisible();
  await expect(page.locator('[data-testid="internal-ticket-filter-shipment"]')).toBeVisible();
  await expect(page.locator('[data-testid="internal-ticket-filter-assignee"]')).toBeVisible();

  await page.locator('[data-testid="internal-ticket-filter-status"]').selectOption('waiting_agent');
  await page.getByRole('button', { name: 'Apply' }).click();
  await page.waitForLoadState('networkidle');

  await expect(page.locator('[data-testid="internal-tickets-table"]')).toContainText('TKT-I9A-C-001');

  await page.getByRole('link', { name: 'TKT-I9A-C-001' }).click();
  await page.waitForLoadState('networkidle');

  await expect(page.locator('[data-testid="internal-ticket-summary-card"]')).toBeVisible();
  await expect(page.locator('[data-testid="internal-ticket-triage-form"]')).toHaveCount(0);
  await expect(page.locator('[data-testid="internal-ticket-status-form"]')).toHaveCount(0);
  await expect(page.locator('[data-testid="internal-ticket-assignment-form"]')).toHaveCount(0);
  await expect(page.locator('[data-testid="internal-ticket-note-form"]')).toHaveCount(0);
  await expect(page.locator('body')).not.toContainText('Internal Server Error');
});
