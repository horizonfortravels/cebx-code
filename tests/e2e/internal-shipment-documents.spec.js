const { test, expect } = require('@playwright/test');
const { execSync } = require('node:child_process');

const PASSWORD = process.env.E2E_USER_PASSWORD || 'Password123!';

const USERS = {
  externalOrganizationOwner: 'e2e.c.organization_owner@example.test',
  internalOpsReadonly: 'e2e.internal.ops_readonly@example.test',
  internalSupport: 'e2e.internal.support@example.test',
};

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

async function openShipmentDocumentsFromDetail(page) {
  await expect(page.locator(routeLinkSelector('internal.shipments.index', '/internal/shipments')).first()).toBeVisible();
  await page.locator(routeLinkSelector('internal.shipments.index', '/internal/shipments')).first().click();
  await page.waitForLoadState('networkidle');
  await page.getByRole('link', { name: 'SHP-I5A-D-001' }).click();
  await page.waitForLoadState('networkidle');
  await expect(page.locator('[data-testid="internal-shipment-documents-link"]')).toBeVisible();
  documentsPath = await page.locator('[data-testid="internal-shipment-documents-link"]').getAttribute('href');
  await page.locator('[data-testid="internal-shipment-documents-link"]').click();
  await page.waitForLoadState('networkidle');
  await expect(page.locator('[data-testid="internal-shipment-documents-workspace"]')).toBeVisible();
}

test.describe.configure({ mode: 'serial' });

test('internal support can preview and download shipment labels from the internal documents surface', async ({ page }) => {
  await loginWith(page, 'admin', USERS.internalSupport);
  await openShipmentDocumentsFromDetail(page);

  await expect(page.locator('[data-testid="internal-shipment-document-row"]').first()).toContainText('i5a-d-label.pdf');

  const previewHref = await page.locator('[data-testid="internal-shipment-document-preview-link"]').first().getAttribute('href');
  expect(previewHref).toBeTruthy();

  const previewResponse = await page.request.get(previewHref);
  expect(previewResponse.ok()).toBeTruthy();
  expect(String(previewResponse.headers()['content-type'] || '')).toContain('application/pdf');

  const [download] = await Promise.all([
    page.waitForEvent('download'),
    page.locator('[data-testid="internal-shipment-document-download-link"]').first().click(),
  ]);

  expect(download.suggestedFilename()).toBe('i5a-d-label.pdf');
  await expect(page.locator('body')).not.toContainText('Internal Server Error');
});

test('internal ops_readonly can view and download shipment labels from the internal documents surface', async ({ page }) => {
  await loginWith(page, 'admin', USERS.internalOpsReadonly);
  await openShipmentDocumentsFromDetail(page);

  await expect(page.locator('[data-testid="internal-shipment-document-row"]').first()).toContainText('i5a-d-label.pdf');

  const previewHref = await page.locator('[data-testid="internal-shipment-document-preview-link"]').first().getAttribute('href');
  expect(previewHref).toBeTruthy();

  const previewResponse = await page.request.get(previewHref);
  expect(previewResponse.ok()).toBeTruthy();
  expect(String(previewResponse.headers()['content-type'] || '')).toContain('application/pdf');

  const [download] = await Promise.all([
    page.waitForEvent('download'),
    page.locator('[data-testid="internal-shipment-document-download-link"]').first().click(),
  ]);

  expect(download.suggestedFilename()).toBe('i5a-d-label.pdf');
  await expect(page.locator('body')).not.toContainText('Internal Server Error');
});

test('external organization user is denied from the internal shipment document routes', async ({ page }) => {
  await loginWith(page, 'b2b', USERS.externalOrganizationOwner);

  const response = await page.goto(documentsPath || '/internal/shipments/blocked/documents');
  expect(response).not.toBeNull();
  expect(response.status()).toBe(403);
  await expect(page.locator('.panel')).toBeVisible();
  await expect(page.locator('.panel .meta')).toContainText('403');
  await expect(page.locator('[data-testid="internal-shipment-documents-workspace"]')).toHaveCount(0);
  await expect(page.locator('body')).not.toContainText('Internal Server Error');
});
