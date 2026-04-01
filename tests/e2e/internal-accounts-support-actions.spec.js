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

async function openAccountDetail(page, accountName) {
  await expect(page.locator(routeLinkSelector('internal.accounts.index', '/internal/accounts')).first()).toBeVisible();
  await page.locator(routeLinkSelector('internal.accounts.index', '/internal/accounts')).first().click();
  await page.waitForLoadState('networkidle');
  await page.getByRole('link', { name: accountName }).click();
  await page.waitForLoadState('networkidle');
}

test.describe.configure({ mode: 'serial' });

test('internal super_admin can trigger password reset and resend a safe organization invitation', async ({ page }) => {
  await loginWith(page, 'admin', USERS.internalSuperAdmin);
  await openAccountDetail(page, 'E2E Account C');

  await expect(page.locator('[data-testid="account-verification-status-card"]')).toBeVisible();
  await expect(page.locator('[data-testid="account-support-actions-card"]')).toBeVisible();
  await expect(page.locator('[data-testid="account-password-reset-button"]')).toBeVisible();

  await page.locator('[data-testid="account-password-reset-button"]').click();
  await page.waitForLoadState('networkidle');
  await expect(page.locator('body')).toContainText('تم إرسال رابط إعادة تعيين كلمة المرور');

  await expect(page.locator('[data-testid="organization-pending-invitations-card"]')).toBeVisible();
  await expect(page.locator('[data-testid="pending-invitation-resend-button"]').first()).toBeVisible();
  await page.locator('[data-testid="pending-invitation-resend-button"]').first().click();
  await page.waitForLoadState('networkidle');
  await expect(page.locator('body')).toContainText('تمت إعادة إرسال دعوة العضو بنجاح');
  await expect(page.getByRole('button', { name: 'إعادة إرسال التحقق' })).toHaveCount(0);
});

test('internal support can trigger password reset but cannot see lifecycle controls', async ({ page }) => {
  await loginWith(page, 'admin', USERS.internalSupport);
  await openAccountDetail(page, 'E2E Account C');

  await expect(page.locator('[data-testid="account-verification-status-card"]')).toBeVisible();
  await expect(page.locator('[data-testid="account-password-reset-button"]')).toBeVisible();
  await expect(page.locator('body')).not.toContainText('تحرير الحساب');
  await expect(page.locator('body')).not.toContainText('تفعيل الحساب');
  await expect(page.locator('body')).not.toContainText('تعليق الحساب');

  await page.locator('[data-testid="account-password-reset-button"]').click();
  await page.waitForLoadState('networkidle');
  await expect(page.locator('body')).toContainText('تم إرسال رابط إعادة تعيين كلمة المرور');
  await expect(page.locator('[data-testid="pending-invitation-resend-button"]').first()).toBeVisible();

  await page.goto(resolveRoutePath('internal.accounts.index', '/internal/accounts'));
  await page.getByRole('link', { name: 'E2E Account A' }).click();
  await page.waitForLoadState('networkidle');
  await expect(page.locator('[data-testid="organization-pending-invitations-card"]')).toHaveCount(0);
});

test('external organization user is denied from the internal external accounts center', async ({ page }) => {
  await loginWith(page, 'b2b', USERS.externalOrganizationOwner);

  const response = await page.goto(resolveRoutePath('internal.accounts.index', '/internal/accounts'));
  expect(response).not.toBeNull();
  expect(response.status()).toBe(403);
  await expect(page.locator('body')).toContainText('هذه الصفحة مخصصة لفريق التشغيل الداخلي في المنصة');
  await expect(page.locator('body')).not.toContainText('حسابات العملاء');
  await expect(page.locator('body')).not.toContainText('Internal Server Error');
});
