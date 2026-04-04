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

async function selectOptionByTextFragment(page, selector, fragment) {
  const value = await page.locator(selector).evaluate((element, expectedFragment) => {
    const option = [...element.options].find((candidate) => candidate.text.includes(expectedFragment));
    return option ? option.value : '';
  }, fragment);

  expect(value).not.toBe('');
  await page.locator(selector).selectOption(value);
  return value;
}

test.describe.configure({ mode: 'serial' });

test('internal support can update ticket workflow, assign a ticket, and add an internal note', async ({ page }) => {
  await loginWith(page, 'admin', USERS.internalSupport);
  await openTicketsCenter(page);

  await page.getByRole('link', { name: 'TKT-I9A-D-001' }).click();
  await page.waitForLoadState('networkidle');

  await expect(page.locator('[data-testid="internal-ticket-workflow-card"]')).toBeVisible();
  await expect(page.locator('[data-testid="internal-ticket-status-form"]')).toBeVisible();
  await expect(page.locator('[data-testid="internal-ticket-assignment-form"]')).toBeVisible();
  await expect(page.locator('[data-testid="internal-ticket-note-form"]')).toBeVisible();

  await page.locator('[data-testid="internal-ticket-status-select"]').selectOption('waiting_customer');
  await page.fill('[data-testid="internal-ticket-status-note"]', 'Waiting for the customer to confirm the requested compliance detail.');
  await page.locator('[data-testid="internal-ticket-status-submit"]').click();
  await page.waitForLoadState('networkidle');

  await expect(page.locator('body')).toContainText('The ticket workflow state was updated successfully.');
  await expect(page.locator('[data-testid="internal-ticket-summary-card"]')).toContainText('بانتظار العميل');
  await expect(page.locator('[data-testid="internal-ticket-notes-card"]')).toContainText('Workflow update: Open -> Waiting on customer.');

  const selectedAssignee = await selectOptionByTextFragment(
    page,
    '[data-testid="internal-ticket-assignment-select"]',
    'e2e.internal.support@example.test'
  );
  await page.fill('[data-testid="internal-ticket-assignment-note"]', 'Assigning the ticket to the active support queue owner.');
  await page.locator('[data-testid="internal-ticket-assignment-submit"]').click();
  await page.waitForLoadState('networkidle');

  await expect(page.locator('body')).toContainText('The ticket assignment was updated successfully.');
  await expect(page.locator('[data-testid="internal-ticket-assignment-select"]')).toHaveValue(selectedAssignee);
  await expect(page.locator('[data-testid="internal-ticket-notes-card"]')).toContainText('Assignment update: Unassigned ->');

  await page.fill('[data-testid="internal-ticket-note-body"]', 'I9C browser internal note for the support workflow.');
  await page.locator('[data-testid="internal-ticket-note-submit"]').click();
  await page.waitForLoadState('networkidle');

  await expect(page.locator('body')).toContainText('The internal ticket note was added successfully.');
  await expect(page.locator('[data-testid="internal-ticket-notes-card"]')).toContainText('I9C browser internal note for the support workflow.');
  await expect(page.locator('body')).not.toContainText('Internal Server Error');
});

test('internal ops_readonly sees ticket notes and workflow history but no mutation controls', async ({ page }) => {
  await loginWith(page, 'admin', USERS.internalOpsReadonly);
  await openTicketsCenter(page);

  await page.getByRole('link', { name: 'TKT-I9A-C-001' }).click();
  await page.waitForLoadState('networkidle');

  await expect(page.locator('[data-testid="internal-ticket-summary-card"]')).toBeVisible();
  await expect(page.locator('[data-testid="internal-ticket-notes-card"]')).toBeVisible();
  await expect(page.locator('[data-testid="internal-ticket-workflow-activity-card"]')).toBeVisible();
  await expect(page.locator('[data-testid="internal-ticket-notes-card"]')).toContainText('Internal escalation note for leadership only.');
  await expect(page.locator('[data-testid="internal-ticket-status-form"]')).toHaveCount(0);
  await expect(page.locator('[data-testid="internal-ticket-assignment-form"]')).toHaveCount(0);
  await expect(page.locator('[data-testid="internal-ticket-note-form"]')).toHaveCount(0);
  await expect(page.locator('body')).not.toContainText('Internal Server Error');
});
