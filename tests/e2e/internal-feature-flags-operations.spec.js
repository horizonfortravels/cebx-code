const { execSync } = require('node:child_process');
const { test, expect } = require('@playwright/test');

const PASSWORD = process.env.E2E_USER_PASSWORD || 'Password123!';

const USERS = {
  externalOrganizationOwner: 'e2e.c.organization_owner@example.test',
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

async function openFeatureFlagsCenter(page) {
  await expect(page.locator(routeLinkSelector('internal.feature-flags.index', '/internal/feature-flags')).first()).toBeVisible();
  await page.locator(routeLinkSelector('internal.feature-flags.index', '/internal/feature-flags')).first().click();
  await page.waitForLoadState('networkidle');
  await expect(page).toHaveURL(/\/internal\/feature-flags$/);
  await expect(page.locator('[data-testid="internal-feature-flags-table"]')).toBeVisible();
}

test.describe.configure({ mode: 'serial' });

test('internal support sees feature flags read-only', async ({ page }) => {
  await loginWith(page, 'admin', USERS.internalSupport);
  await openFeatureFlagsCenter(page);

  await expect(page.locator('body')).toContainText('I8D Internal Ops Fixture');
  await expect(page.locator('body')).not.toContainText('enterprise');
  await expect(page.locator('[data-testid="internal-feature-flag-toggle-form"]')).toHaveCount(0);

  await page.getByRole('link', { name: 'I8D Internal Ops Fixture' }).click();
  await page.waitForLoadState('networkidle');
  await expect(page.locator('[data-testid="internal-feature-flag-summary-card"]')).toBeVisible();
  await expect(page.locator('[data-testid="internal-feature-flag-runtime-card"]')).toBeVisible();
  await expect(page.locator('[data-testid="internal-feature-flag-audit-card"]')).toBeVisible();
  await expect(page.locator('[data-testid="internal-feature-flag-toggle-form"]')).toHaveCount(0);
  await expect(page.locator('body')).not.toContainText('enterprise');
  await expect(page.locator('body')).not.toContainText('Internal Server Error');
});

test('internal ops_readonly sees feature flags read-only', async ({ page }) => {
  await loginWith(page, 'admin', USERS.internalOpsReadonly);
  await openFeatureFlagsCenter(page);

  await page.getByRole('link', { name: 'I8D Internal Ops Fixture' }).click();
  await page.waitForLoadState('networkidle');
  await expect(page.locator('[data-testid="internal-feature-flag-summary-card"]')).toBeVisible();
  await expect(page.locator('[data-testid="internal-feature-flag-runtime-card"]')).toBeVisible();
  await expect(page.locator('[data-testid="internal-feature-flag-audit-card"]')).toBeVisible();
  await expect(page.locator('[data-testid="internal-feature-flag-toggle-form"]')).toHaveCount(0);
  await expect(page.locator('body')).not.toContainText('Internal Server Error');
});

test('internal super_admin can toggle a DB-backed feature flag safely', async ({ page }) => {
  await loginWith(page, 'admin', USERS.internalSuperAdmin);
  await openFeatureFlagsCenter(page);

  await page.getByRole('link', { name: 'I8D Internal Ops Fixture' }).click();
  await page.waitForLoadState('networkidle');
  await expect(page.locator('[data-testid="internal-feature-flag-toggle-form"]')).toBeVisible();
  await page.fill('textarea[name="reason"]', 'Temporarily disabled during safe browser verification of the internal feature-flags center.');
  await page.locator('[data-testid="internal-feature-flag-toggle-button"]').click();
  await page.waitForLoadState('networkidle');

  await expect(page.locator('body')).toContainText('internal audit trail');
  await expect(page.locator('[data-testid="internal-feature-flag-audit-card"]')).toContainText('Temporarily disabled during safe browser verification');
  await expect(page.locator('[data-testid="internal-feature-flag-summary-card"]')).toContainText('Disabled');
  await expect(page.locator('body')).not.toContainText('Internal Server Error');
});

test('external organization user is denied from internal feature flag routes', async ({ page }) => {
  await loginWith(page, 'b2b', USERS.externalOrganizationOwner);

  const response = await page.goto(resolveRoutePath('internal.feature-flags.index', '/internal/feature-flags'));
  expect(response).not.toBeNull();
  expect(response.status()).toBe(403);
  await expect(page.locator('.panel')).toBeVisible();
  await expect(page.locator('.panel .meta')).toContainText('403');
  await expect(page.locator('[data-testid="internal-feature-flags-table"]')).toHaveCount(0);
  await expect(page.locator('body')).not.toContainText('Internal Server Error');
});
