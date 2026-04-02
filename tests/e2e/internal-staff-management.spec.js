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

async function openStaffCenter(page) {
  await expect(page.locator(routeLinkSelector('internal.staff.index', '/internal/staff')).first()).toBeVisible();
  await page.locator(routeLinkSelector('internal.staff.index', '/internal/staff')).first().click();
  await page.waitForLoadState('networkidle');
  await expect(page).toHaveURL(/\/internal\/staff$/);
}

async function expectCanonicalRoleOptions(page) {
  const values = await page.locator('select[name="role"] option').evaluateAll((options) => {
    return options.map((option) => option.value);
  });

  expect(values).toEqual(['super_admin', 'support', 'ops_readonly', 'carrier_manager']);
}

test.describe.configure({ mode: 'serial' });

test('internal super_admin can create internal staff users and reassign a canonical role', async ({ page }) => {
  const suffix = Date.now();
  const supportEmail = `i3b.support.${suffix}@example.test`;
  const carrierEmail = `i3b.carrier.${suffix}@example.test`;

  await loginWith(page, 'admin', USERS.internalSuperAdmin);
  await openStaffCenter(page);

  await expect(page.getByTestId('internal-staff-create-cta')).toBeVisible();
  await page.getByTestId('internal-staff-create-cta').click();
  await page.waitForLoadState('networkidle');
  await expect(page).toHaveURL(/\/internal\/staff\/create$/);
  await expectCanonicalRoleOptions(page);

  await page.fill('input[name="name"]', 'I3B Browser Support');
  await page.fill('input[name="email"]', supportEmail);
  await page.fill('input[name="locale"]', 'en');
  await page.fill('input[name="timezone"]', 'UTC');
  await page.selectOption('select[name="role"]', 'support');
  await page.fill('input[name="password"]', PASSWORD);
  await page.fill('input[name="password_confirmation"]', PASSWORD);
  await page.getByTestId('staff-create-submit').click();
  await page.waitForLoadState('networkidle');

  await expect(page).toHaveURL(/\/internal\/staff\/.+$/);
  await expect(page.locator('body')).toContainText('I3B Browser Support');
  await expect(page.locator('body')).toContainText(supportEmail);
  await expect(page.getByTestId('internal-staff-edit-cta')).toBeVisible();

  await page.goto(resolveRoutePath('internal.staff.create', '/internal/staff/create'));
  await page.waitForLoadState('networkidle');
  await page.fill('input[name="name"]', 'I3B Browser Carrier');
  await page.fill('input[name="email"]', carrierEmail);
  await page.fill('input[name="locale"]', 'en');
  await page.fill('input[name="timezone"]', 'UTC');
  await page.selectOption('select[name="role"]', 'carrier_manager');
  await page.fill('input[name="password"]', PASSWORD);
  await page.fill('input[name="password_confirmation"]', PASSWORD);
  await page.getByTestId('staff-create-submit').click();
  await page.waitForLoadState('networkidle');

  await expect(page.locator('body')).toContainText('I3B Browser Carrier');
  await expect(page.locator('body')).toContainText(carrierEmail);
  await page.getByTestId('internal-staff-edit-cta').click();
  await page.waitForLoadState('networkidle');

  await expect(page).toHaveURL(/\/internal\/staff\/.+\/edit$/);
  await expectCanonicalRoleOptions(page);
  await page.fill('input[name="name"]', 'I3B Browser Carrier Updated');
  await page.selectOption('select[name="role"]', 'support');
  await page.getByTestId('staff-update-submit').click();
  await page.waitForLoadState('networkidle');

  await expect(page.locator('body')).toContainText('I3B Browser Carrier Updated');
  await page.getByTestId('internal-staff-edit-cta').click();
  await page.waitForLoadState('networkidle');
  await expect(page.locator('select[name="role"]')).toHaveValue('support');
});

test('internal support can read staff but cannot see mutation controls', async ({ page }) => {
  await loginWith(page, 'admin', USERS.internalSupport);
  await openStaffCenter(page);

  await expect(page.getByTestId('internal-staff-create-cta')).toHaveCount(0);
  await page.getByRole('link', { name: 'E2E Internal Super Admin' }).click();
  await page.waitForLoadState('networkidle');
  await expect(page.locator('body')).toContainText('E2E Internal Super Admin');
  await expect(page.getByTestId('internal-staff-edit-cta')).toHaveCount(0);
  await expect(page.locator('a[href$="/edit"]')).toHaveCount(0);
  await expect(page.getByTestId('staff-canonical-role-key')).toContainText('super_admin');
  await expect(page.locator('body')).not.toContainText('finance');
  await expect(page.locator('body')).not.toContainText('integration_admin');
});

test('external organization user is denied from internal staff management routes', async ({ page }) => {
  await loginWith(page, 'b2b', USERS.externalOrganizationOwner);

  const response = await page.goto(resolveRoutePath('internal.staff.create', '/internal/staff/create'));
  expect(response).not.toBeNull();
  expect(response.status()).toBe(403);
  await expect(page.locator('.panel')).toBeVisible();
  await expect(page.getByRole('heading', { level: 1, name: 'هذه الصفحة مخصصة لفريق التشغيل الداخلي في المنصة' })).toBeVisible();
  await expect(page.locator('.panel .meta')).toContainText('الحالة الحالية: 403');
  await expect(page.getByRole('link', { name: 'العودة إلى بوابة الأعمال' })).toHaveAttribute(
    'href',
    new RegExp(`${resolveRoutePath('b2b.dashboard', '/b2b/dashboard').replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}$`)
  );
  await expect(page.locator('form[action$="/internal/staff"]')).toHaveCount(0);
  await expect(page.locator('body')).not.toContainText('Internal Server Error');
});
