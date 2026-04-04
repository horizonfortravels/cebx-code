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

async function openWebhookCenter(page) {
  await expect(page.locator(routeLinkSelector('internal.webhooks.index', '/internal/webhooks')).first()).toBeVisible();
  await page.locator(routeLinkSelector('internal.webhooks.index', '/internal/webhooks')).first().click();
  await page.waitForLoadState('networkidle');
  await expect(page).toHaveURL(/\/internal\/webhooks$/);
  await expect(page.locator('[data-testid="internal-webhooks-table"]')).toBeVisible();
}

test.describe.configure({ mode: 'serial' });

test('internal support sees webhook delivery history read-only', async ({ page }) => {
  await loginWith(page, 'admin', USERS.internalSupport);
  await openWebhookCenter(page);

  await expect(page.locator('body')).toContainText('I8A Shopify Store');
  await expect(page.locator('body')).toContainText('DHL Express inbound webhooks');

  await page.getByRole('link', { name: 'I8A Shopify Store' }).click();
  await page.waitForLoadState('networkidle');
  await expect(page.locator('[data-testid="internal-webhook-summary-card"]')).toBeVisible();
  await expect(page.locator('[data-testid="internal-webhook-attempts-card"]')).toBeVisible();
  await expect(page.locator('[data-testid="internal-webhook-failures-card"]')).toBeVisible();
  await expect(page.locator('[data-testid="internal-webhook-retry-form"]')).toHaveCount(0);
  await expect(page.locator('body')).toContainText('A prior processing attempt timed out for the stored delivery.');
  await expect(page.locator('body')).toContainText('External resource reference recorded');
  await expect(page.locator('body')).not.toContainText('1002-ORD');
  await expect(page.locator('body')).not.toContainText('i8a-shopify-webhook-secret-001');
  await expect(page.locator('body')).not.toContainText('masked-replay-token');
  await expect(page.locator('body')).not.toContainText('Internal Server Error');
});

test('internal super_admin can safely retry a failed store webhook delivery', async ({ page }) => {
  await loginWith(page, 'admin', USERS.internalSuperAdmin);
  await openWebhookCenter(page);

  await page.getByRole('link', { name: 'I8A Shopify Store' }).click();
  await page.waitForLoadState('networkidle');
  await expect(page.locator('[data-testid="internal-webhook-retry-form"]')).toBeVisible();
  await page.fill('[data-testid="internal-webhook-retry-form"] textarea[name="reason"]', 'Replayed after confirming the stored Shopify payload is still safe to import.');
  await page.locator('[data-testid="internal-webhook-retry-button"]').click();
  await page.waitForLoadState('networkidle');

  await expect(page.locator('.toast-container .toast.toast-success')).toBeVisible();
  await expect(page.locator('[data-testid="internal-webhook-retry-form"]')).toHaveCount(0);
  await expect(page.locator('[data-testid="internal-webhook-attempts-card"]')).toContainText('Processed');
  await expect(page.locator('body')).not.toContainText('Internal Server Error');
});

test('internal ops_readonly sees webhook history read-only with no retry controls', async ({ page }) => {
  await loginWith(page, 'admin', USERS.internalOpsReadonly);
  await openWebhookCenter(page);

  await page.getByRole('link', { name: 'DHL Express inbound webhooks' }).click();
  await page.waitForLoadState('networkidle');
  await expect(page.locator('[data-testid="internal-webhook-summary-card"]')).toBeVisible();
  await expect(page.locator('[data-testid="internal-webhook-attempts-card"]')).toBeVisible();
  await expect(page.locator('[data-testid="internal-webhook-retry-form"]')).toHaveCount(0);
  await expect(page.locator('body')).toContainText('Signature validation failed for the stored delivery.');
  await expect(page.locator('body')).toContainText('Webhook reference recorded');
  await expect(page.locator('body')).not.toContainText('i8b-dhl-webhook-failed-001');
  await expect(page.locator('body')).not.toContainText('masked-signature-failed');
  await expect(page.locator('body')).not.toContainText('Internal Server Error');
});

test('external organization user is denied from internal webhook routes', async ({ page }) => {
  await loginWith(page, 'b2b', USERS.externalOrganizationOwner);

  const response = await page.goto(resolveRoutePath('internal.webhooks.index', '/internal/webhooks'));
  expect(response).not.toBeNull();
  expect(response.status()).toBe(403);
  await expect(page.locator('.panel')).toBeVisible();
  await expect(page.locator('.panel .meta')).toContainText('403');
  await expect(page.locator('[data-testid="internal-webhooks-table"]')).toHaveCount(0);
  await expect(page.locator('body')).not.toContainText('Internal Server Error');
});
