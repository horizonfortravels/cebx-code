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
  await Promise.all([
    page.waitForURL(url => !/\/(admin\/login|b2b\/login|b2c\/login|login)$/.test(url.pathname), { timeout: 15000 }),
    page.locator('button[type="submit"]').click(),
  ]);
  await page.waitForLoadState('networkidle');
}

async function openReportsHub(page) {
  const response = await page.goto(resolveRoutePath('internal.reports.index', '/internal/reports'));
  expect(response).not.toBeNull();
  expect(response.status()).toBe(200);
  await expect(page).toHaveURL(/\/internal\/reports$/);
  await expect(page.locator('[data-testid="internal-reports-grid"]')).toBeVisible();
}

async function openDashboardFromHub(page, dashboardKey) {
  await page.locator(`[data-testid="internal-report-card-${dashboardKey}-dashboard-link"]`).click();
  await page.waitForLoadState('networkidle');
  await expect(page.locator('[data-testid="internal-report-dashboard"]')).toBeVisible();
  await expect(page.locator('[data-testid="internal-report-breakdown-card"]').first()).toBeVisible();
  await expect(page.locator('[data-testid="internal-report-trend-card"]')).toBeVisible();
  await expect(page.locator('[data-testid="internal-report-actions-card"]')).toBeVisible();
  await expect(page.locator('[data-testid="internal-report-drilldown-card"]')).toBeVisible();
  await expect(page.locator('main.content form')).toHaveCount(0);
  await expect(page.locator('body')).not.toContainText('i8a-shopify-token-001');
  await expect(page.locator('body')).not.toContainText('fedex-client-secret-001');
  await expect(page.locator('body')).not.toContainText('Internal escalation note for leadership only.');
  await expect(page.locator('body')).not.toContainText('Internal Server Error');
}

async function openDashboardAndFollowPrimaryLink(page, dashboardKey, dashboardPath, centerPath) {
  await openReportsHub(page);
  await openDashboardFromHub(page, dashboardKey);
  await expect(page).toHaveURL(new RegExp(`${dashboardPath.replace(/\//g, '\\/')}$`));
  await page.locator('[data-testid="internal-report-dashboard-primary-link"]').click();
  await page.waitForLoadState('networkidle');
  await expect(page).toHaveURL(new RegExp(`${centerPath.replace(/\//g, '\\/')}$`));
  await expect(page.locator('body')).not.toContainText('Internal Server Error');
}

test.describe.configure({ mode: 'serial' });

test('internal support can open shipment, kyc, and tickets dashboards read-only', async ({ page }) => {
  await loginWith(page, 'admin', USERS.internalSupport);

  await openDashboardAndFollowPrimaryLink(
    page,
    'shipments',
    resolveRoutePath('internal.reports.shipments', '/internal/reports/shipments'),
    resolveRoutePath('internal.shipments.index', '/internal/shipments')
  );

  await openDashboardAndFollowPrimaryLink(
    page,
    'kyc',
    resolveRoutePath('internal.reports.kyc', '/internal/reports/kyc'),
    resolveRoutePath('internal.kyc.index', '/internal/kyc')
  );

  await openDashboardAndFollowPrimaryLink(
    page,
    'tickets',
    resolveRoutePath('internal.reports.tickets', '/internal/reports/tickets'),
    resolveRoutePath('internal.tickets.index', '/internal/tickets')
  );
});

test('internal ops_readonly can open shipment, kyc, and tickets dashboards read-only', async ({ page }) => {
  await loginWith(page, 'admin', USERS.internalOpsReadonly);

  await openDashboardAndFollowPrimaryLink(
    page,
    'shipments',
    resolveRoutePath('internal.reports.shipments', '/internal/reports/shipments'),
    resolveRoutePath('internal.shipments.index', '/internal/shipments')
  );

  await openDashboardAndFollowPrimaryLink(
    page,
    'kyc',
    resolveRoutePath('internal.reports.kyc', '/internal/reports/kyc'),
    resolveRoutePath('internal.kyc.index', '/internal/kyc')
  );

  await openDashboardAndFollowPrimaryLink(
    page,
    'tickets',
    resolveRoutePath('internal.reports.tickets', '/internal/reports/tickets'),
    resolveRoutePath('internal.tickets.index', '/internal/tickets')
  );
});

test('carrier_manager sees only the carrier integration slice and can open its dashboard', async ({ page }) => {
  await loginWith(page, 'admin', USERS.internalCarrierManager);
  await openReportsHub(page);

  await expect(page.locator('[data-testid="internal-report-card-carriers"]')).toBeVisible();
  await expect(page.locator('[data-testid="internal-report-card-carriers-dashboard-link"]')).toBeVisible();
  await expect(page.locator('[data-testid="internal-report-card-carriers-link"]')).toBeVisible();
  await expect(page.locator('[data-testid="internal-report-card-shipments"]')).toHaveCount(0);
  await expect(page.locator('[data-testid="internal-report-card-kyc"]')).toHaveCount(0);
  await expect(page.locator('[data-testid="internal-report-card-billing"]')).toHaveCount(0);
  await expect(page.locator('[data-testid="internal-report-card-compliance"]')).toHaveCount(0);
  await expect(page.locator('[data-testid="internal-report-card-tickets"]')).toHaveCount(0);

  await openDashboardFromHub(page, 'carriers');
  await expect(page).toHaveURL(/\/internal\/reports\/carriers$/);
  await page.locator('[data-testid="internal-report-dashboard-primary-link"]').click();
  await page.waitForLoadState('networkidle');
  await expect(page).toHaveURL(/\/internal\/carriers$/);
  await expect(page.locator('body')).not.toContainText('Internal Server Error');
});

test('external organization user is denied from the dashboard routes', async ({ page }) => {
  await loginWith(page, 'b2b', USERS.externalOrganizationOwner);

  const response = await page.goto(resolveRoutePath('internal.reports.shipments', '/internal/reports/shipments'));
  expect(response).not.toBeNull();
  expect(response.status()).toBe(403);
  await expect(page.locator('.panel')).toBeVisible();
  await expect(page.locator('.panel .meta')).toContainText('403');
  await expect(page.locator('[data-testid="internal-report-dashboard"]')).toHaveCount(0);
  await expect(page.locator('body')).not.toContainText('Internal Server Error');
});
