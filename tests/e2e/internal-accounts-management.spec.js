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

test.describe.configure({ mode: 'serial' });

test('internal super_admin can create edit and change external account lifecycle from the accounts center', async ({ page }) => {
  const suffix = Date.now();
  const individualName = `I2B Individual ${suffix}`;
  const organizationName = `I2B Organization ${suffix}`;

  await loginWith(page, 'admin', USERS.internalSuperAdmin);

  await expect(page.locator(routeLinkSelector('internal.accounts.index', '/internal/accounts')).first()).toBeVisible();
  await page.locator(routeLinkSelector('internal.accounts.index', '/internal/accounts')).first().click();
  await page.waitForLoadState('networkidle');

  await page.getByRole('link', { name: 'إضافة حساب عميل' }).click();
  await page.waitForLoadState('networkidle');

  await page.fill('input[name="account_name"]', individualName);
  await page.selectOption('select[name="account_type"]', 'individual');
  await page.fill('input[name="owner_name"]', 'I2B Individual Owner');
  await page.fill('input[name="owner_email"]', `i2b-individual-${suffix}@example.test`);
  await page.fill('input[name="owner_phone"]', '+966500100001');
  await page.fill('input[name="language"]', 'en');
  await page.fill('input[name="currency"]', 'USD');
  await page.fill('input[name="timezone"]', 'UTC');
  await page.getByRole('button', { name: 'إنشاء الحساب' }).click();
  await page.waitForLoadState('networkidle');

  await expect(page.locator('body')).toContainText(individualName);
  await expect(page.locator('body')).toContainText('تحرير الحساب');
  await expect(page.locator('body')).toContainText('تفعيل الحساب');

  await page.getByRole('link', { name: 'تحرير الحساب' }).click();
  await page.waitForLoadState('networkidle');
  await page.fill('input[name="name"]', `${individualName} Edited`);
  await page.fill('input[name="contact_email"]', `support-${suffix}@example.test`);
  await page.getByRole('button', { name: 'حفظ التعديلات' }).click();
  await page.waitForLoadState('networkidle');
  await expect(page.locator('body')).toContainText(`${individualName} Edited`);

  await page.getByRole('button', { name: 'تفعيل الحساب' }).click();
  await page.waitForLoadState('networkidle');
  await expect(page.locator('body')).toContainText('تم تفعيل الحساب');
  await expect(page.locator('body')).toContainText('تعليق الحساب');

  await page.getByRole('button', { name: 'تعليق الحساب' }).click();
  await page.waitForLoadState('networkidle');
  await expect(page.locator('body')).toContainText('تم تعليق الحساب');
  await expect(page.locator('body')).toContainText('رفع التعليق');

  await page.getByRole('button', { name: 'رفع التعليق' }).click();
  await page.waitForLoadState('networkidle');
  await expect(page.locator('body')).toContainText('تم رفع تعليق الحساب');
  await expect(page.locator('body')).toContainText('إلغاء التفعيل');

  await page.getByRole('button', { name: 'إلغاء التفعيل' }).click();
  await page.waitForLoadState('networkidle');
  await expect(page.locator('body')).toContainText('تم إلغاء تفعيل الحساب');

  await page.goto(resolveRoutePath('internal.accounts.create', '/internal/accounts/create'));
  await page.fill('input[name="account_name"]', organizationName);
  await page.selectOption('select[name="account_type"]', 'organization');
  await page.fill('input[name="owner_name"]', 'I2B Organization Owner');
  await page.fill('input[name="owner_email"]', `i2b-organization-${suffix}@example.test`);
  await page.fill('input[name="legal_name"]', `${organizationName} LLC`);
  await page.fill('input[name="trade_name"]', `${organizationName} Trade`);
  await page.fill('input[name="registration_number"]', `CR-${suffix}`);
  await page.fill('input[name="industry"]', 'logistics');
  await page.selectOption('select[name="company_size"]', 'medium');
  await page.fill('input[name="org_city"]', 'Riyadh');
  await page.getByRole('button', { name: 'إنشاء الحساب' }).click();
  await page.waitForLoadState('networkidle');

  await expect(page.locator('body')).toContainText('ملخص المؤسسة');
  await expect(page.locator('body')).toContainText(`${organizationName} LLC`);

  await page.getByRole('link', { name: 'تحرير الحساب' }).click();
  await page.waitForLoadState('networkidle');
  await page.fill('input[name="trade_name"]', `${organizationName} Updated`);
  await page.getByRole('button', { name: 'حفظ التعديلات' }).click();
  await page.waitForLoadState('networkidle');
  await expect(page.locator('body')).toContainText(`${organizationName} Updated`);
});

test('internal support can read accounts but cannot see create edit or lifecycle controls', async ({ page }) => {
  await loginWith(page, 'admin', USERS.internalSupport);

  await expect(page.locator(routeLinkSelector('internal.accounts.index', '/internal/accounts')).first()).toBeVisible();
  await page.locator(routeLinkSelector('internal.accounts.index', '/internal/accounts')).first().click();
  await page.waitForLoadState('networkidle');

  await expect(page.locator('body')).not.toContainText('إضافة حساب عميل');
  await page.getByRole('link', { name: 'E2E Account A' }).click();
  await page.waitForLoadState('networkidle');
  await expect(page.locator('body')).not.toContainText('تحرير الحساب');
  await expect(page.locator('body')).not.toContainText('تفعيل الحساب');
  await expect(page.locator('body')).not.toContainText('تعليق الحساب');
});

test('external organization user is denied from internal account management routes', async ({ page }) => {
  await loginWith(page, 'b2b', USERS.externalOrganizationOwner);

  const response = await page.goto(resolveRoutePath('internal.accounts.create', '/internal/accounts/create'));
  expect(response).not.toBeNull();
  expect(response.status()).toBe(403);
  await expect(page.locator('body')).toContainText('ظ‡ط°ظ‡ ط§ظ„طµظپط­ط© ظ…ط®طµطµط© ظ„ظپط±ظٹظ‚ ط§ظ„طھط´ط؛ظٹظ„ ط§ظ„ط¯ط§ط®ظ„ظٹ ظپظٹ ط§ظ„ظ…ظ†طµط©');
  await expect(page.locator('body')).not.toContainText('إنشاء الحساب');
  await expect(page.locator('body')).not.toContainText('Internal Server Error');
});
