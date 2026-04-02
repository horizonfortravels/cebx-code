const { execSync } = require('node:child_process');
const { test, expect } = require('@playwright/test');

const PASSWORD = process.env.E2E_USER_PASSWORD || 'Password123!';

const USERS = {
  internalOpsReadonly: 'e2e.internal.ops_readonly@example.test',
  internalSuperAdmin: 'e2e.internal.super_admin@example.test',
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
  return routeMap.get('admin.login') || '/admin/login';
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
  await page.locator('button[type="submit"]').click();
  await page.waitForLoadState('networkidle');
}

async function openComplianceCenter(page) {
  await expect(page.locator(routeLinkSelector('internal.compliance.index', '/internal/compliance')).first()).toBeVisible();
  await page.locator(routeLinkSelector('internal.compliance.index', '/internal/compliance')).first().click();
  await page.waitForLoadState('networkidle');
  await expect(page).toHaveURL(/\/internal\/compliance$/);
  await expect(page.locator('[data-testid="internal-compliance-table"]')).toBeVisible();
}

async function openComplianceDetail(page, reference) {
  await page.getByRole('link', { name: reference }).click();
  await page.waitForLoadState('networkidle');
  await expect(page.locator('[data-testid="internal-compliance-case-summary-card"]')).toBeVisible();
}

test.describe.configure({ mode: 'serial' });

test('internal super_admin can request customer correction on a safe compliance case', async ({ page }) => {
  await loginWith(page, 'admin', USERS.internalSuperAdmin);
  await openComplianceCenter(page);
  await openComplianceDetail(page, 'SHP-I7A-D-001');

  await expect(page.locator('[data-testid="internal-compliance-actions-card"]')).toBeVisible();
  await expect(page.locator('[data-testid="internal-compliance-requires-action-form"]')).toBeVisible();

  await page.fill(
    '[data-testid="internal-compliance-requires-action-form"] textarea[name="reason"]',
    'Customer must correct the declaration wording before the shipment can continue.'
  );

  await Promise.all([
    page.waitForLoadState('networkidle'),
    page.locator('[data-testid="internal-compliance-requires-action-button"]').click(),
  ]);

  await expect(page.locator('.toast-container .toast.toast-success')).toBeVisible();
  await expect(page.locator('[data-testid="internal-compliance-case-summary-card"]')).toContainText('Requires action');
  await expect(page.locator('[data-testid="internal-compliance-case-summary-card"]')).toContainText('Customer must correct the declaration wording before the shipment can continue.');
  await expect(page.locator('[data-testid="internal-compliance-audit-card"]')).toContainText('Status changed');
  await expect(page.locator('[data-testid="internal-compliance-audit-card"]')).toContainText('Change summary: Status: Pending -> Requires action');
  await expect(page.locator('[data-testid="internal-compliance-notes-card"]')).toContainText('Customer must correct the declaration wording before the shipment can continue.');
  await expect(page.locator('body')).not.toContainText('Internal Server Error');
});

test('internal support stays read-only on compliance actions', async ({ page }) => {
  await loginWith(page, 'admin', USERS.internalSupport);
  await openComplianceCenter(page);
  await openComplianceDetail(page, 'SHP-I7A-D-001');

  await expect(page.locator('[data-testid="internal-compliance-actions-card"]')).toHaveCount(0);
  await expect(page.locator('[data-testid="internal-compliance-requires-action-form"]')).toHaveCount(0);
  await expect(page.locator('[data-testid="internal-compliance-requires-action-button"]')).toHaveCount(0);
  await expect(page.locator('body')).not.toContainText('Internal Server Error');
});

test('internal ops_readonly sees compliance detail read-only with no mutation controls', async ({ page }) => {
  await loginWith(page, 'admin', USERS.internalOpsReadonly);
  await openComplianceCenter(page);
  await openComplianceDetail(page, 'SHP-I7A-D-001');

  await expect(page.locator('[data-testid="internal-compliance-actions-card"]')).toHaveCount(0);
  await expect(page.locator('[data-testid="internal-compliance-requires-action-form"]')).toHaveCount(0);
  await expect(page.locator('[data-testid="internal-compliance-account-link"]')).toHaveCount(0);
  await expect(page.locator('[data-testid="internal-compliance-audit-card"]')).toBeVisible();
  await expect(page.locator('body')).not.toContainText('Internal Server Error');
});
