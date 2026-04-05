const { execSync } = require('node:child_process');
const { test, expect } = require('@playwright/test');

const PASSWORD = process.env.E2E_USER_PASSWORD || 'Password123!';

const USERS = {
  externalIndividualOwner: 'e2e.a.individual@example.test',
  externalOrganizationOwner: 'e2e.c.organization_owner@example.test',
  internalSuperAdmin: 'e2e.internal.super_admin@example.test',
  internalSupport: 'e2e.internal.support@example.test',
  internalOpsReadonly: 'e2e.internal.ops_readonly@example.test',
  internalCarrierManager: 'e2e.internal.carrier_manager@example.test',
  suspendedExternal: 'e2e.c.suspended@example.test',
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
    b2c: routeMap.get('b2c.login'),
    b2b: routeMap.get('b2b.login'),
    admin: routeMap.get('admin.login'),
  };

  const fallbacks = {
    b2c: ['/b2c/login', '/login'],
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

async function expectForbiddenInternalPage(page, path) {
  const response = await page.goto(path);
  expect(response).not.toBeNull();
  expect(response.status()).toBe(403);
  await expect(page.locator('body')).toContainText('هذه الصفحة ليست ضمن دورك الحالي');
  await expect(page.locator('body')).not.toContainText('Internal Server Error');
}

async function openPortalLogin(page, portal) {
  const loginPath = resolveLoginPath(portal);
  const response = await page.goto(loginPath);
  expect(response).not.toBeNull();
  expect(response.status()).toBeLessThan(500);

  const emailField = page.locator('input[name="email"]');
  const passwordField = page.locator('input[name="password"]');

  if ((await emailField.count()) === 0 || (await passwordField.count()) === 0) {
    const selector = {
      b2c: 'a.entry-card.entry-card--b2c',
      b2b: 'a.entry-card.entry-card--b2b',
      admin: 'a.entry-card.entry-card--admin',
    }[portal];

    await expect(page.locator(selector)).toBeVisible();
    await page.locator(selector).click();
  }

  await expect(page.locator('input[name="email"]')).toBeVisible();
  await expect(page.locator('input[name="password"]')).toBeVisible();
}

async function loginWith(page, portal, email, password = PASSWORD) {
  await openPortalLogin(page, portal);
  await page.fill('input[name="email"]', email);
  await page.fill('input[name="password"]', password);

  const submit = page.locator('button[type="submit"]');
  await expect(submit).toBeVisible();
  await submit.click();
  await page.waitForLoadState('networkidle');
}

test.describe.configure({ mode: 'serial' });

test('login pages do not expose seeded credentials', async ({ page }) => {
  const pages = [
    ['b2c', USERS.externalIndividualOwner],
    ['b2b', USERS.externalOrganizationOwner],
    ['admin', USERS.internalSuperAdmin],
  ];

  for (const [portal, email] of pages) {
    await openPortalLogin(page, portal);
    await expect(page.locator('body')).not.toContainText(email);
    await expect(page.locator('body')).not.toContainText(PASSWORD);
  }
});

test('external individual user can login and reach B2C home', async ({ page }) => {
  await loginWith(page, 'b2c', USERS.externalIndividualOwner);

  await expect(page).toHaveURL(/\/b2c\/dashboard/);
  await expect(page.locator('.external-sidebar-badge')).toContainText('حساب فردي');
});

test('external organization owner can login and reach B2B home', async ({ page }) => {
  await loginWith(page, 'b2b', USERS.externalOrganizationOwner);

  await expect(page).toHaveURL(/\/b2b\/dashboard/);
  await expect(page.locator('a[href$="/b2b/shipments"]').first()).toBeVisible();
});

test('internal super_admin can login and open admin UI page', async ({ page }) => {
  await loginWith(page, 'admin', USERS.internalSuperAdmin);

  await expect(page).toHaveURL(/\/admin$/);
  await expect(page.locator(routeLinkSelector('admin.index', '/admin')).first()).toBeVisible();
  await expect(page.locator(routeLinkSelector('admin.tenant-context', '/admin/tenant-context')).first()).toBeVisible();
  await expect(page.locator(routeLinkSelector('admin.users', '/admin/users')).first()).toBeVisible();
  await expect(page.locator(routeLinkSelector('admin.roles', '/admin/roles')).first()).toBeVisible();
  await expect(page.locator(routeLinkSelector('admin.reports', '/admin/reports')).first()).toBeVisible();

  const adminResponse = await page.goto('/admin');
  expect(adminResponse).not.toBeNull();
  expect(adminResponse.status()).toBe(200);

  const tenantContextResponse = await page.goto(resolveRoutePath('admin.tenant-context', '/admin/tenant-context'));
  expect(tenantContextResponse).not.toBeNull();
  expect(tenantContextResponse.status()).toBe(200);
});

test('internal support can login and see only the support-aligned internal workspace', async ({ page }) => {
  await loginWith(page, 'admin', USERS.internalSupport);

  await expect(page).toHaveURL(/\/internal$/);
  await expect(page.locator('body')).toContainText('الدعم');
  await expect(page.locator(routeLinkSelector('internal.home', '/internal')).first()).toBeVisible();
  await expect(page.locator(routeLinkSelector('admin.index', '/admin'))).toHaveCount(0);
  await expect(page.locator(routeLinkSelector('admin.tenant-context', '/admin/tenant-context'))).toHaveCount(0);
  await expect(page.locator(routeLinkSelector('admin.users', '/admin/users'))).toHaveCount(0);
  await expect(page.locator(routeLinkSelector('admin.roles', '/admin/roles'))).toHaveCount(0);
  await expect(page.locator(routeLinkSelector('admin.reports', '/admin/reports'))).toHaveCount(0);
  await expect(page.locator(routeLinkSelector('internal.tenant-context', '/internal/tenant-context'))).toHaveCount(0);
  await expect(page.locator(routeLinkSelector('internal.smtp-settings.edit', '/internal/smtp-settings'))).toHaveCount(0);

  await expectForbiddenInternalPage(page, '/admin');
  await expectForbiddenInternalPage(page, resolveRoutePath('internal.smtp-settings.edit', '/internal/smtp-settings'));
});

test('internal ops_readonly can login and stay in the readonly internal workspace', async ({ page }) => {
  await loginWith(page, 'admin', USERS.internalOpsReadonly);

  await expect(page).toHaveURL(/\/internal$/);
  await expect(page.locator('body')).toContainText('التشغيل للقراءة فقط');
  await expect(page.locator(routeLinkSelector('internal.home', '/internal')).first()).toBeVisible();
  await expect(page.locator(routeLinkSelector('admin.index', '/admin'))).toHaveCount(0);
  await expect(page.locator(routeLinkSelector('admin.tenant-context', '/admin/tenant-context'))).toHaveCount(0);
  await expect(page.locator(routeLinkSelector('admin.users', '/admin/users'))).toHaveCount(0);
  await expect(page.locator(routeLinkSelector('admin.roles', '/admin/roles'))).toHaveCount(0);
  await expect(page.locator(routeLinkSelector('admin.reports', '/admin/reports'))).toHaveCount(0);
  await expect(page.locator(routeLinkSelector('internal.tenant-context', '/internal/tenant-context'))).toHaveCount(0);
  await expect(page.locator(routeLinkSelector('internal.smtp-settings.edit', '/internal/smtp-settings'))).toHaveCount(0);

  await expectForbiddenInternalPage(page, '/admin');
  await expectForbiddenInternalPage(page, resolveRoutePath('internal.smtp-settings.edit', '/internal/smtp-settings'));
});

test('internal carrier_manager can login and reach smtp settings without admin navigation', async ({ page }) => {
  await loginWith(page, 'admin', USERS.internalCarrierManager);

  await expect(page).toHaveURL(/\/internal$/);
  await expect(page.locator('body')).toContainText('إدارة الناقلين');
  await expect(page.locator(routeLinkSelector('internal.home', '/internal')).first()).toBeVisible();
  await expect(page.locator(routeLinkSelector('internal.smtp-settings.edit', '/internal/smtp-settings')).first()).toBeVisible();
  await expect(page.locator(routeLinkSelector('admin.index', '/admin'))).toHaveCount(0);
  await expect(page.locator(routeLinkSelector('admin.tenant-context', '/admin/tenant-context'))).toHaveCount(0);
  await expect(page.locator(routeLinkSelector('admin.users', '/admin/users'))).toHaveCount(0);
  await expect(page.locator(routeLinkSelector('admin.roles', '/admin/roles'))).toHaveCount(0);
  await expect(page.locator(routeLinkSelector('admin.reports', '/admin/reports'))).toHaveCount(0);
  await expect(page.locator(routeLinkSelector('internal.tenant-context', '/internal/tenant-context'))).toHaveCount(0);

  const smtpResponse = await page.goto(resolveRoutePath('internal.smtp-settings.edit', '/internal/smtp-settings'));
  expect(smtpResponse).not.toBeNull();
  expect(smtpResponse.status()).toBe(200);
  await expect(page.locator('body')).toContainText('SMTP');
  await expectForbiddenInternalPage(page, '/admin');
});

test('suspended external user cannot login', async ({ page }) => {
  await openPortalLogin(page, 'b2b');
  await page.fill('input[name="email"]', USERS.suspendedExternal);
  await page.fill('input[name="password"]', PASSWORD);
  await page.locator('button[type="submit"]').click();
  await page.waitForLoadState('networkidle');

  await expect(page).toHaveURL(/\/b2b\/login/);
  await expect(page.locator('.login-errors')).toBeVisible();
});

test('external user cannot access internal admin UI', async ({ page }) => {
  await loginWith(page, 'b2c', USERS.externalIndividualOwner);
  await expect(page).toHaveURL(/\/b2c\/dashboard/);

  const response = await page.goto('/admin');
  expect(response).not.toBeNull();
  expect(response.status()).toBe(403);
  await expect(page.locator('a[href$="/b2c/dashboard"]')).toBeVisible();
});
