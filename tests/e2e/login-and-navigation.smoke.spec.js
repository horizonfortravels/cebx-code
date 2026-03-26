const { execSync } = require('node:child_process');
const { test, expect } = require('@playwright/test');

const PASSWORD = process.env.E2E_USER_PASSWORD || 'Password123!';

const USERS = {
  externalIndividualOwner: 'e2e.a.individual@example.test',
  externalOrganizationOwner: 'e2e.c.organization_owner@example.test',
  internalSuperAdmin: 'e2e.internal.super_admin@example.test',
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

async function openPortalLogin(page, portal) {
  const loginPath = resolveLoginPath(portal);
  const response = await page.goto(loginPath);
  expect(response).not.toBeNull();
  expect(response.status()).toBeLessThan(500);

  const emailField = page.locator('input[name="email"]');
  const passwordField = page.locator('input[name="password"]');

  if ((await emailField.count()) === 0 || (await passwordField.count()) === 0) {
    const selector = {
      b2c: 'a.portal-door.b2c',
      b2b: 'a.portal-door.b2b',
      admin: 'a.portal-door.admin',
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
  await expect(page.locator('.badge-b2c')).toBeVisible();
});

test('external organization owner can login and reach B2B home', async ({ page }) => {
  await loginWith(page, 'b2b', USERS.externalOrganizationOwner);

  await expect(page).toHaveURL(/\/b2b\/dashboard/);
  await expect(page.locator('a[href$="/b2b/shipments"]').first()).toBeVisible();
});

test('internal super_admin can login and open admin UI page', async ({ page }) => {
  await loginWith(page, 'admin', USERS.internalSuperAdmin);

  await expect(page).toHaveURL(/\/admin$/);
  await expect(page.locator('h1')).toContainText('الإدارة');

  const adminResponse = await page.goto('/admin');
  expect(adminResponse).not.toBeNull();
  expect(adminResponse.status()).toBe(200);
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
