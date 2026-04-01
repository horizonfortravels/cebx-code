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

test('internal super_admin can browse the external accounts read center', async ({ page }) => {
  await loginWith(page, 'admin', USERS.internalSuperAdmin);

  await expect(page.locator(routeLinkSelector('internal.accounts.index', '/internal/accounts')).first()).toBeVisible();
  await page.locator(routeLinkSelector('internal.accounts.index', '/internal/accounts')).first().click();
  await page.waitForLoadState('networkidle');

  await expect(page).toHaveURL(/\/internal\/accounts$/);
  await expect(page.locator('body')).toContainText('حسابات العملاء');
  await expect(page.locator('body')).toContainText('E2E Account A');
  await expect(page.locator('body')).toContainText('E2E Account C');

  await page.getByRole('link', { name: 'E2E Account C' }).click();
  await page.waitForLoadState('networkidle');
  await expect(page.locator('body')).toContainText('ملخص الحساب');
  await expect(page.locator('body')).toContainText('ملخص المؤسسة');

  await page.goto(resolveRoutePath('internal.accounts.index', '/internal/accounts'));
  await page.getByRole('link', { name: 'E2E Account A' }).click();
  await page.waitForLoadState('networkidle');
  await expect(page.locator('body')).toContainText('قاعدة الحساب الفردي');
});

test('internal support can open the external accounts read center list and detail', async ({ page }) => {
  await loginWith(page, 'admin', USERS.internalSupport);

  await expect(page).toHaveURL(/\/internal$/);
  await expect(page.locator(routeLinkSelector('internal.accounts.index', '/internal/accounts')).first()).toBeVisible();
  await page.locator(routeLinkSelector('internal.accounts.index', '/internal/accounts')).first().click();
  await page.waitForLoadState('networkidle');

  await expect(page.locator('body')).toContainText('حسابات العملاء');
  await page.getByRole('link', { name: 'E2E Account C' }).click();
  await page.waitForLoadState('networkidle');
  await expect(page.locator('body')).toContainText('ملخص الحساب');
  await expect(page.locator('body')).not.toContainText('Internal Server Error');
});

test('external organization user is denied from the internal accounts read center', async ({ page }) => {
  await loginWith(page, 'b2b', USERS.externalOrganizationOwner);

  const response = await page.goto(resolveRoutePath('internal.accounts.index', '/internal/accounts'));
  expect(response).not.toBeNull();
  expect(response.status()).toBe(403);
  await expect(page.locator('body')).toContainText('هذه الصفحة مخصصة لفريق التشغيل الداخلي في المنصة');
  await expect(page.locator('body')).not.toContainText('حسابات العملاء');
  await expect(page.locator('body')).not.toContainText('Internal Server Error');
});
