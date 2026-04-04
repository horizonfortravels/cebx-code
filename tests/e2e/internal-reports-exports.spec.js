const fs = require('node:fs');
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

async function openReportsHub(page) {
  await expect(page.locator(routeLinkSelector('internal.reports.index', '/internal/reports')).first()).toBeVisible();
  await page.locator(routeLinkSelector('internal.reports.index', '/internal/reports')).first().click();
  await page.waitForLoadState('networkidle');
  await expect(page).toHaveURL(/\/internal\/reports$/);
}

async function openShipmentsDashboard(page) {
  await openReportsHub(page);
  await page.locator('[data-testid="internal-report-card-shipments-dashboard-link"]').click();
  await page.waitForLoadState('networkidle');
  await expect(page).toHaveURL(/\/internal\/reports\/shipments$/);
  await expect(page.locator('[data-testid="internal-report-dashboard"]')).toBeVisible();
}

async function expectCsvDownload(page, expectedHeaderStart) {
  const [download] = await Promise.all([
    page.waitForEvent('download'),
    page.locator('[data-testid="internal-report-dashboard-export-link"]').click(),
  ]);

  expect(download.suggestedFilename()).toMatch(/^internal-report-shipments-.*\.csv$/);

  const downloadPath = await download.path();
  expect(downloadPath).not.toBeNull();

  const body = fs.readFileSync(downloadPath, 'utf8');
  expect(body.startsWith(expectedHeaderStart)).toBeTruthy();
  expect(body).not.toContain('Internal escalation note for leadership only.');
  expect(body).not.toContain('public_tracking_token');
}

test.describe.configure({ mode: 'serial' });

test('super_admin can export a safe operational shipments csv', async ({ page }) => {
  await loginWith(page, 'admin', USERS.internalSuperAdmin);
  await openShipmentsDashboard(page);

  await expect(page.locator('[data-testid="internal-report-dashboard-export-link"]')).toBeVisible();
  await expectCsvDownload(
    page,
    'shipment_reference,account_name,account_slug,account_type,status,carrier,service,tracking_summary,source,international,cod,dangerous_goods,timeline_events,documents_available,created_at'
  );
});

test('support can export one allowed safe operational csv', async ({ page }) => {
  await loginWith(page, 'admin', USERS.internalSupport);
  await openShipmentsDashboard(page);

  await expect(page.locator('[data-testid="internal-report-dashboard-export-link"]')).toBeVisible();
  await expectCsvDownload(
    page,
    'shipment_reference,account_name,account_slug,account_type,status,carrier,service,tracking_summary,source,international,cod,dangerous_goods,timeline_events,documents_available,created_at'
  );
});

test('ops_readonly sees dashboards but no export control', async ({ page }) => {
  await loginWith(page, 'admin', USERS.internalOpsReadonly);
  await openShipmentsDashboard(page);

  await expect(page.locator('[data-testid="internal-report-dashboard-export-link"]')).toHaveCount(0);
  await expect(page.locator('body')).not.toContainText('Internal Server Error');
});

test('external organization user is denied from the internal report export route', async ({ page }) => {
  await loginWith(page, 'b2b', USERS.externalOrganizationOwner);

  const response = await page.goto(resolveRoutePath('internal.reports.shipments.export', '/internal/reports/shipments/export'));
  expect(response).not.toBeNull();
  expect(response.status()).toBe(403);
  await expect(page.locator('.panel')).toBeVisible();
  await expect(page.locator('.panel .meta')).toContainText('403');
  await expect(page.locator('body')).not.toContainText('Internal Server Error');
});
