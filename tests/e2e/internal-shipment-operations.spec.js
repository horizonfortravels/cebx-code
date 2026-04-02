const { execSync } = require('node:child_process');
const { test, expect } = require('@playwright/test');

const PASSWORD = process.env.E2E_USER_PASSWORD || 'Password123!';

const USERS = {
  internalCarrierManager: 'e2e.internal.carrier_manager@example.test',
  internalOpsReadonly: 'e2e.internal.ops_readonly@example.test',
  internalSupport: 'e2e.internal.support@example.test',
};

let shipmentDetailPath = null;
let documentsPath = null;

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
  return portal === 'admin'
    ? (routeMap.get('admin.login') || '/admin/login')
    : (routeMap.get('b2b.login') || '/b2b/login');
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

async function enableClipboard(context) {
  await context.grantPermissions(['clipboard-read', 'clipboard-write']);
}

async function openShipmentDetail(page, reference) {
  await expect(page.locator(routeLinkSelector('internal.shipments.index', '/internal/shipments')).first()).toBeVisible();
  await page.locator(routeLinkSelector('internal.shipments.index', '/internal/shipments')).first().click();
  await page.waitForLoadState('networkidle');
  await page.getByRole('link', { name: reference }).click();
  await page.waitForLoadState('networkidle');
  await expect(page.locator('[data-testid="internal-shipment-actions-card"]')).toBeVisible();
}

test.describe.configure({ mode: 'serial' });

test('internal support can use the safe shipment operational actions', async ({ page, context }) => {
  await loginWith(page, 'admin', USERS.internalSupport);
  await openShipmentDetail(page, 'SHP-I5A-D-001');
  await enableClipboard(context);

  shipmentDetailPath = new URL(page.url()).pathname;
  documentsPath = await page.locator('[data-testid="internal-shipment-documents-workspace-link"]').getAttribute('href');

  await expect(page.locator('[data-testid="internal-shipment-refresh-link"]')).toBeVisible();
  await expect(page.locator('[data-testid="internal-shipment-documents-workspace-link"]')).toBeVisible();
  await expect(page.locator('[data-testid="internal-shipment-actions-account-link"]')).toBeVisible();
  await expect(page.locator('[data-testid="internal-shipment-actions-kyc-link"]')).toBeVisible();
  await expect(page.locator('[data-testid="internal-shipment-actions-public-tracking-link"]')).toBeVisible();
  await expect(page.locator('[data-testid="internal-shipment-copy-public-tracking-link"]')).toBeVisible();
  await expect(page.locator('[data-testid="internal-shipment-actions-public-tracking-link"]')).toHaveAttribute('href', /\/track\//);

  await page.locator('[data-testid="internal-shipment-copy-public-tracking-link"]').click();
  await expect(page.locator('[data-testid="internal-shipment-copy-status"]')).toContainText('copied');

  await Promise.all([
    page.waitForURL(/\/internal\/accounts\//),
    page.locator('[data-testid="internal-shipment-actions-account-link"]').click(),
  ]);
  await expect(page.locator('body')).toContainText('E2E Account D');
  await expect(page.locator('body')).not.toContainText('Internal Server Error');

  await page.goto(shipmentDetailPath);
  await page.waitForLoadState('networkidle');

  await Promise.all([
    page.waitForURL(/\/internal\/kyc\//),
    page.locator('[data-testid="internal-shipment-actions-kyc-link"]').click(),
  ]);
  await expect(page.locator('[data-testid="kyc-status-card"]')).toBeVisible();
  await expect(page.locator('body')).not.toContainText('Internal Server Error');

  await page.goto(shipmentDetailPath);
  await page.waitForLoadState('networkidle');

  await Promise.all([
    page.waitForURL(/\/internal\/shipments\/.*\/documents/),
    page.locator('[data-testid="internal-shipment-documents-workspace-link"]').click(),
  ]);
  await expect(page.locator('[data-testid="internal-shipment-documents-workspace"]')).toBeVisible();
  await expect(page.locator('body')).not.toContainText('Internal Server Error');
});

test('internal ops_readonly sees read-only shipment operational actions only', async ({ page, context }) => {
  await loginWith(page, 'admin', USERS.internalOpsReadonly);
  await openShipmentDetail(page, 'SHP-I5A-D-001');
  await enableClipboard(context);

  await expect(page.locator('[data-testid="internal-shipment-refresh-link"]')).toBeVisible();
  await expect(page.locator('[data-testid="internal-shipment-documents-workspace-link"]')).toBeVisible();
  await expect(page.locator('[data-testid="internal-shipment-actions-kyc-link"]')).toBeVisible();
  await expect(page.locator('[data-testid="internal-shipment-actions-public-tracking-link"]')).toBeVisible();
  await expect(page.locator('[data-testid="internal-shipment-copy-public-tracking-link"]')).toBeVisible();
  await expect(page.locator('[data-testid="internal-shipment-actions-account-link"]')).toHaveCount(0);
  await expect(page.locator('form[action*="/shipments/"]')).toHaveCount(0);

  await page.locator('[data-testid="internal-shipment-copy-public-tracking-link"]').click();
  await expect(page.locator('[data-testid="internal-shipment-copy-status"]')).toContainText('copied');

  await Promise.all([
    page.waitForURL(/\/internal\/kyc\//),
    page.locator('[data-testid="internal-shipment-actions-kyc-link"]').click(),
  ]);
  await expect(page.locator('[data-testid="kyc-status-card"]')).toBeVisible();

  await page.goto(shipmentDetailPath || resolveRoutePath('internal.shipments.index', '/internal/shipments'));
  await page.waitForLoadState('networkidle');

  await Promise.all([
    page.waitForURL(/\/internal\/shipments\/.*\/documents/),
    page.locator('[data-testid="internal-shipment-documents-workspace-link"]').click(),
  ]);
  await expect(page.locator('[data-testid="internal-shipment-documents-workspace"]')).toBeVisible();
  await expect(page.locator('body')).not.toContainText('Internal Server Error');
});

test('internal carrier_manager only keeps the canonical shipment documents surface', async ({ page }) => {
  await loginWith(page, 'admin', USERS.internalCarrierManager);

  const deniedResponse = await page.goto(shipmentDetailPath || resolveRoutePath('internal.shipments.index', '/internal/shipments'));
  expect(deniedResponse).not.toBeNull();
  expect(deniedResponse.status()).toBe(403);
  await expect(page.locator('.panel')).toBeVisible();
  await expect(page.locator('[data-testid="internal-shipment-actions-card"]')).toHaveCount(0);

  const documentsResponse = await page.goto(documentsPath || '/internal/shipments/blocked/documents');
  expect(documentsResponse).not.toBeNull();
  expect(documentsResponse.status()).toBe(200);
  await expect(page.locator('[data-testid="internal-shipment-documents-workspace"]')).toBeVisible();
  await expect(page.locator(`a[href="${shipmentDetailPath || '/internal/shipments/blocked'}"]`)).toHaveCount(0);
  await expect(page.locator('body')).not.toContainText('Internal Server Error');
});
