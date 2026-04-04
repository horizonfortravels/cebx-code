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

async function openIntegrationsCenter(page) {
  await expect(page.locator(routeLinkSelector('internal.integrations.index', '/internal/integrations')).first()).toBeVisible();
  await page.locator(routeLinkSelector('internal.integrations.index', '/internal/integrations')).first().click();
  await page.waitForLoadState('networkidle');
  await expect(page).toHaveURL(/\/internal\/integrations$/);
  await expect(page.locator('[data-testid="internal-integrations-table"]')).toBeVisible();
}

test.describe.configure({ mode: 'serial' });

test('internal support can open integrations list and detail read-only', async ({ page }) => {
  await loginWith(page, 'admin', USERS.internalSupport);
  await openIntegrationsCenter(page);

  await expect(page.locator('body')).toContainText('DHL Express');
  await expect(page.locator('body')).toContainText('I8A Shopify Store');
  await expect(page.locator('body')).toContainText('Moyasar');
  await expect(page.locator('body')).not.toContainText('i8a-shopify-token-001');
  await expect(page.locator('body')).not.toContainText('i8a-moyasar-secret-001');

  await page.getByRole('link', { name: 'I8A Shopify Store' }).click();
  await page.waitForLoadState('networkidle');
  await expect(page.locator('[data-testid="internal-integration-summary-card"]')).toBeVisible();
  await expect(page.locator('[data-testid="internal-integration-health-card"]')).toBeVisible();
  await expect(page.locator('[data-testid="internal-integration-activity-card"]')).toBeVisible();
  await expect(page.locator('[data-testid="internal-integration-feature-flags-card"]')).toBeVisible();
  await expect(page.locator('[data-testid="internal-integration-credentials-card"]')).toBeVisible();
  await expect(page.locator('[data-testid="internal-integration-account-link"]')).toBeVisible();
  await expect(page.locator('body')).not.toContainText('i8a-shopify-token-001');
  await expect(page.locator('body')).not.toContainText('i8a-shopify-webhook-secret-001');
  await expect(page.locator('body')).not.toContainText('Internal Server Error');
});

test('internal ops_readonly can open integrations read-only without account shortcut', async ({ page }) => {
  await loginWith(page, 'admin', USERS.internalOpsReadonly);
  await openIntegrationsCenter(page);

  await page.getByRole('link', { name: 'I8A Shopify Store' }).click();
  await page.waitForLoadState('networkidle');
  await expect(page.locator('[data-testid="internal-integration-summary-card"]')).toBeVisible();
  await expect(page.locator('[data-testid="internal-integration-health-card"]')).toBeVisible();
  await expect(page.locator('[data-testid="internal-integration-activity-card"]')).toBeVisible();
  await expect(page.locator('[data-testid="internal-integration-feature-flags-card"]')).toBeVisible();
  await expect(page.locator('[data-testid="internal-integration-credentials-card"]')).toBeVisible();
  await expect(page.locator('[data-testid="internal-integration-account-link"]')).toHaveCount(0);
  await expect(page.locator('body')).not.toContainText('i8a-shopify-token-001');
  await expect(page.locator('body')).not.toContainText('Internal Server Error');
});

test('carrier_manager only sees carrier-facing integration detail', async ({ page }) => {
  await loginWith(page, 'admin', USERS.internalCarrierManager);
  await openIntegrationsCenter(page);

  await expect(page.locator('body')).toContainText('DHL Express');
  await expect(page.locator('body')).toContainText('FedEx');
  await expect(page.locator('body')).not.toContainText('I8A Shopify Store');
  await expect(page.locator('body')).not.toContainText('Moyasar');

  await page.getByRole('link', { name: 'DHL Express' }).click();
  await page.waitForLoadState('networkidle');
  await expect(page.locator('[data-testid="internal-integration-summary-card"]')).toBeVisible();
  await expect(page.locator('[data-testid="internal-integration-health-card"]')).toBeVisible();
  await expect(page.locator('[data-testid="internal-integration-feature-flags-card"]')).toBeVisible();
  await expect(page.locator('[data-testid="internal-integration-credentials-card"]')).toHaveCount(0);
  await expect(page.locator('body')).not.toContainText('Internal Server Error');
});

test('external organization user is denied from the internal integrations routes', async ({ page }) => {
  await loginWith(page, 'b2b', USERS.externalOrganizationOwner);

  const response = await page.goto(resolveRoutePath('internal.integrations.index', '/internal/integrations'));
  expect(response).not.toBeNull();
  expect(response.status()).toBe(403);
  await expect(page.locator('.panel')).toBeVisible();
  await expect(page.locator('.panel .meta')).toContainText('403');
  await expect(page.locator('[data-testid="internal-integrations-table"]')).toHaveCount(0);
  await expect(page.locator('body')).not.toContainText('Internal Server Error');
});
