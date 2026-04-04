const { execSync } = require('node:child_process');
const { test, expect } = require('@playwright/test');

const PASSWORD = process.env.E2E_USER_PASSWORD || 'Password123!';

const USERS = {
  externalOrganizationOwner: 'e2e.c.organization_owner@example.test',
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

async function openApiKeysCenter(page) {
  await expect(page.locator(routeLinkSelector('internal.api-keys.index', '/internal/api-keys')).first()).toBeVisible();
  await page.locator(routeLinkSelector('internal.api-keys.index', '/internal/api-keys')).first().click();
  await page.waitForLoadState('networkidle');
  await expect(page).toHaveURL(/\/internal\/api-keys$/);
  await expect(page.locator('[data-testid="internal-api-keys-table"]')).toBeVisible();
}

test.describe.configure({ mode: 'serial' });

test('internal support sees masked API key surfaces read-only', async ({ page }) => {
  await loginWith(page, 'admin', USERS.internalSupport);
  await openApiKeysCenter(page);

  await expect(page.locator('body')).toContainText('I8C Active Operations Key');
  await expect(page.locator('body')).not.toContainText('sgw_i8c_seed_active_001');
  await expect(page.locator('[data-testid="internal-api-key-create-form"]')).toHaveCount(0);

  await page.getByRole('link', { name: 'I8C Active Operations Key' }).click();
  await page.waitForLoadState('networkidle');
  await expect(page.locator('[data-testid="internal-api-key-summary-card"]')).toBeVisible();
  await expect(page.locator('[data-testid="internal-api-key-scopes-card"]')).toBeVisible();
  await expect(page.locator('[data-testid="internal-api-key-security-card"]')).toBeVisible();
  await expect(page.locator('[data-testid="internal-api-key-account-link"]')).toBeVisible();
  await expect(page.locator('[data-testid="internal-api-key-rotate-form"]')).toHaveCount(0);
  await expect(page.locator('[data-testid="internal-api-key-revoke-form"]')).toHaveCount(0);
  await expect(page.locator('[data-testid="internal-api-key-plaintext-card"]')).toHaveCount(0);
  await expect(page.locator('body')).not.toContainText('sgw_i8c_seed_active_001');
  await expect(page.locator('body')).not.toContainText('Internal Server Error');
});

test('internal super_admin can create an API key and sees plaintext only once', async ({ page }) => {
  await loginWith(page, 'admin', USERS.internalSuperAdmin);
  await openApiKeysCenter(page);

  await expect(page.locator('[data-testid="internal-api-key-create-form"]')).toBeVisible();
  await page.selectOption('select[name="account_id"]', { label: 'E2E Account C' });
  await page.fill('input[name="name"]', 'I8C Browser Created Key');
  await page.check('input[name="scopes[]"][value="shipments:read"]');
  await page.fill('textarea[name="reason"]', 'Created for a safe browser verification of the internal API key center.');
  await page.locator('[data-testid="internal-api-key-create-button"]').click();
  await page.waitForLoadState('networkidle');

  await expect(page.locator('[data-testid="internal-api-key-summary-card"]')).toBeVisible();
  await expect(page.locator('[data-testid="internal-api-key-plaintext-card"]')).toBeVisible();
  const rawKey = (await page.locator('[data-testid="internal-api-key-plaintext-value"]').innerText()).trim();
  expect(rawKey.startsWith('sgw_')).toBeTruthy();

  await page.reload();
  await page.waitForLoadState('networkidle');
  await expect(page.locator('[data-testid="internal-api-key-plaintext-card"]')).toHaveCount(0);
  await expect(page.locator('body')).not.toContainText(rawKey);
  await expect(page.locator('body')).not.toContainText('Internal Server Error');
});

test('external organization user is denied from internal API key routes', async ({ page }) => {
  await loginWith(page, 'b2b', USERS.externalOrganizationOwner);

  const response = await page.goto(resolveRoutePath('internal.api-keys.index', '/internal/api-keys'));
  expect(response).not.toBeNull();
  expect(response.status()).toBe(403);
  await expect(page.locator('.panel')).toBeVisible();
  await expect(page.locator('.panel .meta')).toContainText('403');
  await expect(page.locator('[data-testid="internal-api-keys-table"]')).toHaveCount(0);
  await expect(page.locator('body')).not.toContainText('Internal Server Error');
});
