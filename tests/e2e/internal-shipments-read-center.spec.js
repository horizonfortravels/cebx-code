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

async function openShipmentsCenter(page) {
  await expect(page.locator(routeLinkSelector('internal.shipments.index', '/internal/shipments')).first()).toBeVisible();
  await page.locator(routeLinkSelector('internal.shipments.index', '/internal/shipments')).first().click();
  await page.waitForLoadState('networkidle');
  await expect(page).toHaveURL(/\/internal\/shipments$/);
  await expect(page.locator('[data-testid="internal-shipments-table"]')).toBeVisible();
}

async function openShipmentDetail(page, reference) {
  await page.getByRole('link', { name: reference }).click();
  await page.waitForLoadState('networkidle');
  await expect(page.locator('[data-testid="internal-shipment-summary-card"]')).toBeVisible();
}

test.describe.configure({ mode: 'serial' });

test('internal super_admin can open the internal shipment list and detail', async ({ page }) => {
  await loginWith(page, 'admin', USERS.internalSuperAdmin);
  await openShipmentsCenter(page);

  await expect(page.locator('body')).toContainText('SHP-I5A-A-001');
  await expect(page.locator('body')).toContainText('SHP-I5A-D-001');

  await openShipmentDetail(page, 'SHP-I5A-D-001');
  await expect(page.locator('[data-testid="internal-shipment-linked-account-card"]')).toBeVisible();
  await expect(page.locator('[data-testid="internal-shipment-operational-state-card"]')).toBeVisible();
  await expect(page.locator('[data-testid="internal-shipment-parcels-card"]')).toBeVisible();
  await expect(page.locator('[data-testid="internal-shipment-timeline-card"]')).toBeVisible();
  await expect(page.locator('[data-testid="internal-shipment-documents-card"]')).toBeVisible();
  await expect(page.locator('[data-testid="internal-shipment-notifications-card"]')).toBeVisible();
  await expect(page.locator('[data-testid="internal-shipment-notification-item"]').first()).toBeVisible();
  await expect(page.locator('[data-testid="internal-shipment-kyc-summary-card"]')).toBeVisible();
  await expect(page.locator('[data-testid="internal-shipment-public-tracking-link"]')).toBeVisible();
  await expect(page.locator('[data-testid="internal-shipment-public-tracking-link"]')).toHaveAttribute('href', /\/track\//);
  await expect(page.locator('body')).toContainText('Shipment documents ready');
  await expect(page.locator('body')).toContainText('i5a-d-label.pdf');
  await expect(page.locator('body')).not.toContainText('i5a-public-token-d-001');
  await expect(page.locator('body')).not.toContainText('content_base64');
  await expect(page.locator('body')).not.toContainText('Internal Server Error');
});

test('internal support can open the shipment read center', async ({ page }) => {
  await loginWith(page, 'admin', USERS.internalSupport);
  await openShipmentsCenter(page);

  await openShipmentDetail(page, 'SHP-I5A-D-001');
  await expect(page.locator('[data-testid="internal-shipment-operational-state-card"]')).toBeVisible();
  await expect(page.locator('[data-testid="internal-shipment-timeline-card"]')).toBeVisible();
  await expect(page.locator('[data-testid="internal-shipment-notifications-card"]')).toBeVisible();
  await expect(page.locator('[data-testid="internal-shipment-notification-item"]').first()).toBeVisible();
  await expect(page.locator('[data-testid="internal-shipment-public-tracking-link"]')).toBeVisible();
  await expect(page.locator('[data-testid="internal-shipment-account-link"]')).toBeVisible();
  await expect(page.locator('[data-testid="internal-shipment-kyc-link"]')).toBeVisible();
  await expect(page.locator('body')).not.toContainText('Internal Server Error');
});

test('internal ops_readonly can open the shipment read center', async ({ page }) => {
  await loginWith(page, 'admin', USERS.internalOpsReadonly);
  await openShipmentsCenter(page);

  await openShipmentDetail(page, 'SHP-I5A-D-001');
  await expect(page.locator('[data-testid="internal-shipment-operational-state-card"]')).toBeVisible();
  await expect(page.locator('[data-testid="internal-shipment-timeline-card"]')).toBeVisible();
  await expect(page.locator('[data-testid="internal-shipment-notifications-card"]')).toBeVisible();
  await expect(page.locator('[data-testid="internal-shipment-notification-item"]').first()).toBeVisible();
  await expect(page.locator('[data-testid="internal-shipment-public-tracking-link"]')).toBeVisible();
  await expect(page.locator('[data-testid="internal-shipment-kyc-link"]')).toBeVisible();
  await expect(page.locator('[data-testid="internal-shipment-account-link"]')).toHaveCount(0);
  await expect(page.locator('[data-testid="internal-shipment-kyc-summary-card"]')).toBeVisible();
  await expect(page.locator('body')).not.toContainText('Internal Server Error');
});

test('external organization user is denied from the internal shipment routes', async ({ page }) => {
  await loginWith(page, 'b2b', USERS.externalOrganizationOwner);

  const response = await page.goto(resolveRoutePath('internal.shipments.index', '/internal/shipments'));
  expect(response).not.toBeNull();
  expect(response.status()).toBe(403);
  await expect(page.locator('.panel')).toBeVisible();
  await expect(page.getByRole('heading', { level: 1 })).toContainText('هذه الصفحة مخصصة لفريق التشغيل الداخلي في المنصة');
  await expect(page.locator('.panel .meta')).toContainText('الحالة الحالية: 403');
  await expect(page.locator('[data-testid="internal-shipments-table"]')).toHaveCount(0);
  await expect(page.locator('[data-testid="internal-shipment-summary-card"]')).toHaveCount(0);
  await expect(page.locator('body')).not.toContainText('Internal Server Error');
});
