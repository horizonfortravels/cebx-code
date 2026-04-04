const { execSync } = require('node:child_process');
const { test, expect } = require('@playwright/test');

const PASSWORD = process.env.E2E_USER_PASSWORD || 'Password123!';

const USERS = {
  externalOrganizationOwner: 'e2e.c.organization_owner@example.test',
  internalOpsReadonly: 'e2e.internal.ops_readonly@example.test',
  internalSupport: 'e2e.internal.support@example.test',
  internalSuperAdmin: 'e2e.internal.super_admin@example.test',
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

async function openReportsHub(page) {
  await expect(page.locator(routeLinkSelector('internal.reports.index', '/internal/reports')).first()).toBeVisible();
  await page.locator(routeLinkSelector('internal.reports.index', '/internal/reports')).first().click();
  await page.waitForLoadState('networkidle');
  await expect(page).toHaveURL(/\/internal\/reports$/);
  await expect(page.locator('[data-testid="internal-reports-grid"]')).toBeVisible();
}

test.describe.configure({ mode: 'serial' });

test('internal super_admin can open the executive dashboard and sees no export control', async ({ page }) => {
  await loginWith(page, 'admin', USERS.internalSuperAdmin);
  await openReportsHub(page);

  await expect(page.locator('[data-testid="internal-report-card-executive"]')).toBeVisible();
  await expect(page.locator('[data-testid="internal-report-card-executive-dashboard-link"]')).toBeVisible();

  await page.locator('[data-testid="internal-report-card-executive-dashboard-link"]').click();
  await page.waitForLoadState('networkidle');

  await expect(page).toHaveURL(/\/internal\/reports\/executive$/);
  await expect(page.locator('[data-testid="internal-report-dashboard"]')).toBeVisible();
  await expect(page.locator('body')).toContainText('Executive profitability dashboard');
  await expect(page.locator('body')).toContainText('Quoted commercial snapshot');
  await expect(page.locator('body')).toContainText('Carrier performance snapshot');
  await expect(page.locator('body')).toContainText('Safe wallet activity snapshot');
  await expect(page.locator('[data-testid="internal-report-dashboard-export-link"]')).toHaveCount(0);
  await expect(page.locator('body')).not.toContainText('i8a-shopify-token-001');
  await expect(page.locator('body')).not.toContainText('Internal escalation note for leadership only.');
  await expect(page.locator('body')).not.toContainText('Internal Server Error');
});

test('support does not see the executive profitability slice', async ({ page }) => {
  await loginWith(page, 'admin', USERS.internalSupport);
  await openReportsHub(page);

  await expect(page.locator('[data-testid="internal-report-card-executive"]')).toHaveCount(0);

  const response = await page.goto(resolveRoutePath('internal.reports.executive', '/internal/reports/executive'));
  expect(response).not.toBeNull();
  expect(response.status()).toBe(403);
  await expect(page.locator('.panel')).toBeVisible();
  await expect(page.locator('.panel .meta')).toContainText('403');
  await expect(page.locator('[data-testid="internal-report-dashboard"]')).toHaveCount(0);
});

test('ops_readonly does not see the executive profitability slice', async ({ page }) => {
  await loginWith(page, 'admin', USERS.internalOpsReadonly);
  await openReportsHub(page);

  await expect(page.locator('[data-testid="internal-report-card-executive"]')).toHaveCount(0);

  const response = await page.goto(resolveRoutePath('internal.reports.executive', '/internal/reports/executive'));
  expect(response).not.toBeNull();
  expect(response.status()).toBe(403);
  await expect(page.locator('.panel')).toBeVisible();
  await expect(page.locator('.panel .meta')).toContainText('403');
  await expect(page.locator('[data-testid="internal-report-dashboard"]')).toHaveCount(0);
});

test('external organization user is denied from the executive dashboard route', async ({ page }) => {
  await loginWith(page, 'b2b', USERS.externalOrganizationOwner);

  const response = await page.goto(resolveRoutePath('internal.reports.executive', '/internal/reports/executive'));
  expect(response).not.toBeNull();
  expect(response.status()).toBe(403);
  await expect(page.locator('.panel')).toBeVisible();
  await expect(page.locator('.panel .meta')).toContainText('403');
  await expect(page.locator('[data-testid="internal-report-dashboard"]')).toHaveCount(0);
  await expect(page.locator('body')).not.toContainText('Internal Server Error');
});
