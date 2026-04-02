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
    b2b: routeMap.get('b2b.login'),
    admin: routeMap.get('admin.login'),
  };

  const fallbacks = {
    b2b: ['/b2b/login', '/login'],
    admin: ['/admin/login', '/login'],
  };

  return byName[portal] || fallbacks[portal][0];
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

test.describe.configure({ mode: 'serial' });

test('internal super_admin can open the internal staff list and detail', async ({ page }) => {
  await loginWith(page, 'admin', USERS.internalSuperAdmin);

  await expect(page.locator(routeLinkSelector('internal.staff.index', '/internal/staff')).first()).toBeVisible();
  await page.locator(routeLinkSelector('internal.staff.index', '/internal/staff')).first().click();
  await page.waitForLoadState('networkidle');

  await expect(page).toHaveURL(/\/internal\/staff$/);
  await expect(page.locator('body')).toContainText('دليل فريق المنصة');
  await expect(page.locator('body')).toContainText('E2E Internal Super Admin');
  await expect(page.locator('body')).not.toContainText('finance');
  await expect(page.locator('body')).not.toContainText('integration_admin');
  await expect(page.locator('body')).toContainText('E2E Internal Support');

  await page.getByRole('link', { name: 'E2E Internal Support' }).click();
  await page.waitForLoadState('networkidle');
  await expect(page.locator('body')).toContainText('ملخص الموظف');
  await expect(page.locator('body')).toContainText('ملخص الصلاحيات');
  await expect(page.getByTestId('staff-canonical-role-key')).toContainText('support');
  await expect(page.getByTestId('staff-permissions-note')).toContainText('مشتق من الدور الداخلي المعتمد الحالي');
  await expect(page.locator('body')).not.toContainText('finance');
  await expect(page.locator('body')).not.toContainText('integration_admin');
  await expect(page.locator('body')).toContainText('E2E Internal Support');
});

test('internal support can open the internal staff read center but not lifecycle surfaces', async ({ page }) => {
  await loginWith(page, 'admin', USERS.internalSupport);

  await expect(page).toHaveURL(/\/internal$/);
  await expect(page.locator(routeLinkSelector('internal.staff.index', '/internal/staff')).first()).toBeVisible();
  await page.locator(routeLinkSelector('internal.staff.index', '/internal/staff')).first().click();
  await page.waitForLoadState('networkidle');

  await expect(page.locator('body')).toContainText('دليل فريق المنصة');
  await page.getByRole('link', { name: 'E2E Internal Super Admin' }).click();
  await page.waitForLoadState('networkidle');
  await expect(page.locator('body')).toContainText('الدور الداخلي');
  await expect(page.getByTestId('staff-canonical-role-key')).toContainText('super_admin');
  await expect(page.locator('body')).not.toContainText('Internal Server Error');
  await expect(page.locator('body')).not.toContainText('finance');
  await expect(page.locator('body')).not.toContainText('integration_admin');
  await expect(page.locator('form[action$="/activate"]')).toHaveCount(0);
  await expect(page.locator('form[action$="/deactivate"]')).toHaveCount(0);
});

test('external organization user is denied from the internal staff routes', async ({ page }) => {
  await loginWith(page, 'b2b', USERS.externalOrganizationOwner);

  const response = await page.goto(resolveRoutePath('internal.staff.index', '/internal/staff'));
  expect(response).not.toBeNull();
  expect(response.status()).toBe(403);
  await expect(page.locator('.panel')).toBeVisible();
  await expect(page.getByRole('heading', { level: 1, name: 'هذه الصفحة مخصصة لفريق التشغيل الداخلي في المنصة' })).toBeVisible();
  await expect(page.locator('.panel .meta')).toContainText('الحالة الحالية: 403');
  await expect(page.getByRole('link', { name: 'العودة إلى بوابة الأعمال' })).toHaveAttribute(
    'href',
    new RegExp(`${resolveRoutePath('b2b.dashboard', '/b2b/dashboard').replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}$`)
  );
  await expect(page.locator('body')).not.toContainText('دليل فريق المنصة');
  await expect(page.locator('body')).not.toContainText('Internal Server Error');
});
