const { execSync } = require('node:child_process');
const { test, expect } = require('@playwright/test');

const PASSWORD = process.env.E2E_USER_PASSWORD || 'Password123!';

const USERS = {
  externalOrganizationOwner: 'e2e.c.organization_owner@example.test',
  internalCarrierManager: 'e2e.internal.carrier_manager@example.test',
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

test('internal support can open the compliance queue and detail', async ({ page }) => {
  await loginWith(page, 'admin', USERS.internalSupport);
  await openComplianceCenter(page);

  await expect(page.locator('body')).toContainText('SHP-I7A-A-001');
  await expect(page.locator('body')).toContainText('SHP-I7A-C-001');
  await expect(page.locator('body')).toContainText('SHP-I7A-D-001');

  await openComplianceDetail(page, 'SHP-I7A-C-001');
  await expect(page.locator('[data-testid="internal-compliance-shipment-card"]')).toBeVisible();
  await expect(page.locator('[data-testid="internal-compliance-account-card"]')).toBeVisible();
  await expect(page.locator('[data-testid="internal-compliance-legal-card"]')).toBeVisible();
  await expect(page.locator('[data-testid="internal-compliance-workflow-card"]')).toBeVisible();
  await expect(page.locator('[data-testid="internal-compliance-notes-card"]')).toBeVisible();
  await expect(page.locator('[data-testid="internal-compliance-effects-card"]')).toBeVisible();
  await expect(page.locator('[data-testid="internal-compliance-dg-card"]')).toBeVisible();
  await expect(page.locator('[data-testid="internal-compliance-audit-card"]')).toBeVisible();
  await expect(page.locator('[data-testid="internal-compliance-audit-change-summary"]').first()).toBeVisible();
  await expect(page.locator('[data-testid="internal-compliance-account-link"]')).toBeVisible();
  await expect(page.locator('[data-testid="internal-compliance-shipment-link"]')).toBeVisible();
  await expect(page.locator('[data-testid="internal-compliance-kyc-link"]')).toBeVisible();
  await expect(page.locator('[data-testid="internal-compliance-billing-link"]')).toBeVisible();
  await expect(page.locator('[data-testid="internal-compliance-preflight-link"]')).toHaveCount(0);
  await expect(page.locator('body')).toContainText('UN1993');
  await expect(page.locator('body')).toContainText('Dangerous goods were declared, so the shipment remains in manual review until an internal team resolves the hold.');
  await expect(page.locator('body')).toContainText('Change summary: DG metadata: UN1993 / 3 / II');
  await expect(page.locator('body')).not.toContainText('i7a-hidden-waiver-hash-a');
  await expect(page.locator('body')).not.toContainText('I7A hidden waiver text snapshot A');
  await expect(page.locator('body')).not.toContainText('I7A hidden dg additional info C');
  await expect(page.locator('body')).not.toContainText('I7A hidden user agent C');
  await expect(page.locator('body')).not.toContainText('I7A hidden audit payload C');
  await expect(page.locator('body')).not.toContainText('Internal Server Error');

  await page.locator('[data-testid="internal-compliance-shipment-link"]').click();
  await page.waitForLoadState('networkidle');
  await expect(page.locator('[data-testid="internal-shipment-summary-card"]')).toBeVisible();

  await openComplianceCenter(page);
  await openComplianceDetail(page, 'SHP-I7A-C-001');
  await page.locator('[data-testid="internal-compliance-account-link"]').click();
  await page.waitForLoadState('networkidle');
  await expect(page.locator('[data-testid="account-verification-status-card"]')).toBeVisible();

  await openComplianceCenter(page);
  await openComplianceDetail(page, 'SHP-I7A-C-001');
  await page.locator('[data-testid="internal-compliance-kyc-link"]').click();
  await page.waitForLoadState('networkidle');
  await expect(page.locator('[data-testid="kyc-account-summary-card"]')).toBeVisible();

  await openComplianceCenter(page);
  await openComplianceDetail(page, 'SHP-I7A-C-001');
  await page.locator('[data-testid="internal-compliance-billing-link"]').click();
  await page.waitForLoadState('networkidle');
  await expect(page.locator('[data-testid="internal-billing-summary-card"]')).toBeVisible();
});

test('internal ops_readonly can open the compliance queue and detail read-only', async ({ page }) => {
  await loginWith(page, 'admin', USERS.internalOpsReadonly);
  await openComplianceCenter(page);

  await openComplianceDetail(page, 'SHP-I7A-C-001');
  await expect(page.locator('[data-testid="internal-compliance-shipment-card"]')).toBeVisible();
  await expect(page.locator('[data-testid="internal-compliance-account-card"]')).toBeVisible();
  await expect(page.locator('[data-testid="internal-compliance-legal-card"]')).toBeVisible();
  await expect(page.locator('[data-testid="internal-compliance-workflow-card"]')).toBeVisible();
  await expect(page.locator('[data-testid="internal-compliance-notes-card"]')).toBeVisible();
  await expect(page.locator('[data-testid="internal-compliance-effects-card"]')).toBeVisible();
  await expect(page.locator('[data-testid="internal-compliance-dg-card"]')).toBeVisible();
  await expect(page.locator('[data-testid="internal-compliance-audit-card"]')).toBeVisible();
  await expect(page.locator('[data-testid="internal-compliance-audit-change-summary"]').first()).toBeVisible();
  await expect(page.locator('[data-testid="internal-compliance-account-link"]')).toHaveCount(0);
  await expect(page.locator('[data-testid="internal-compliance-shipment-link"]')).toBeVisible();
  await expect(page.locator('[data-testid="internal-compliance-kyc-link"]')).toBeVisible();
  await expect(page.locator('[data-testid="internal-compliance-billing-link"]')).toBeVisible();
  await expect(page.locator('[data-testid="internal-compliance-preflight-link"]')).toHaveCount(0);
  await expect(page.locator('body')).not.toContainText('Internal Server Error');

  await page.locator('[data-testid="internal-compliance-billing-link"]').click();
  await page.waitForLoadState('networkidle');
  await expect(page.locator('[data-testid="internal-billing-summary-card"]')).toBeVisible();
});

test('carrier_manager is denied from the internal compliance routes', async ({ page }) => {
  await loginWith(page, 'admin', USERS.internalCarrierManager);

  const response = await page.goto(resolveRoutePath('internal.compliance.index', '/internal/compliance'));
  expect(response).not.toBeNull();
  expect(response.status()).toBe(403);
  await expect(page.locator('.panel')).toBeVisible();
  await expect(page.locator('.panel .meta')).toContainText('403');
  await expect(page.locator('[data-testid="internal-compliance-table"]')).toHaveCount(0);
  await expect(page.locator('[data-testid="internal-compliance-case-summary-card"]')).toHaveCount(0);
  await expect(page.locator('body')).not.toContainText('Internal Server Error');
});

test('external organization user is denied from the internal compliance routes', async ({ page }) => {
  await loginWith(page, 'b2b', USERS.externalOrganizationOwner);

  const response = await page.goto(resolveRoutePath('internal.compliance.index', '/internal/compliance'));
  expect(response).not.toBeNull();
  expect(response.status()).toBe(403);
  await expect(page.locator('.panel')).toBeVisible();
  await expect(page.locator('.panel .meta')).toContainText('403');
  await expect(page.locator('[data-testid="internal-compliance-table"]')).toHaveCount(0);
  await expect(page.locator('[data-testid="internal-compliance-case-summary-card"]')).toHaveCount(0);
  await expect(page.locator('body')).not.toContainText('Internal Server Error');
});
