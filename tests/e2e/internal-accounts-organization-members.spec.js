const { execSync } = require('node:child_process');
const { test, expect } = require('@playwright/test');

const PASSWORD = process.env.E2E_USER_PASSWORD || 'Password123!';

const USERS = {
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

function resolveLoginPath() {
  return routeMap.get('admin.login') || '/admin/login';
}

function resolveRoutePath(routeName, fallbackPath) {
  return routeMap.get(routeName) || fallbackPath;
}

function routeLinkSelector(routeName, fallbackPath) {
  return `a[href$="${resolveRoutePath(routeName, fallbackPath)}"]`;
}

async function loginWith(page, email, password = PASSWORD) {
  const response = await page.goto(resolveLoginPath());
  expect(response).not.toBeNull();
  expect(response.status()).toBeLessThan(500);

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

test('super_admin can manage organization members from the internal accounts center', async ({ page }) => {
  const inviteEmail = `i2d-browser-${Date.now()}@example.test`;

  await loginWith(page, USERS.internalSuperAdmin);
  await openAccountDetail(page, 'E2E Account C');

  await expect(page.locator('[data-testid="organization-members-card"]')).toBeVisible();
  await expect(page.locator('[data-testid="organization-member-invite-form"]')).toBeVisible();

  await page.fill('input[name="name"]', 'I2D Browser Invite');
  await page.fill('input[name="email"]', inviteEmail);
  await page.locator('[data-testid="organization-member-role-select"]').selectOption({ index: 0 });
  await page.locator('[data-testid="organization-member-invite-submit"]').click();
  await page.waitForLoadState('networkidle');
  await expect(page.locator('body')).toContainText('تم إرسال دعوة العضو بنجاح');

  const staffRow = page.locator('[data-testid="organization-member-row"]').filter({ hasText: 'e2e.c.staff@example.test' });
  await expect(staffRow).toBeVisible();
  await staffRow.locator('[data-testid="organization-member-deactivate-button"]').click();
  await page.waitForLoadState('networkidle');
  await expect(page.locator('body')).toContainText('تم تعطيل عضو المنظمة');

  const disabledRow = page.locator('[data-testid="organization-member-row"]').filter({ hasText: 'e2e.c.staff@example.test' });
  await disabledRow.locator('[data-testid="organization-member-reactivate-button"]').click();
  await page.waitForLoadState('networkidle');
  await expect(page.locator('body')).toContainText('تمت إعادة تفعيل عضو المنظمة');
});

test('support can view members and resend invitations only, and individuals show no member UI', async ({ page }) => {
  await loginWith(page, USERS.internalSupport);
  await openAccountDetail(page, 'E2E Account C');

  await expect(page.locator('[data-testid="organization-members-card"]')).toBeVisible();
  await expect(page.locator('[data-testid="organization-member-invite-form"]')).toHaveCount(0);
  await expect(page.locator('[data-testid="organization-member-deactivate-button"]')).toHaveCount(0);
  await expect(page.locator('[data-testid="organization-member-reactivate-button"]')).toHaveCount(0);
  await expect(page.locator('[data-testid="pending-invitation-resend-button"]').first()).toBeVisible();

  await page.locator('[data-testid="pending-invitation-resend-button"]').first().click();
  await page.waitForLoadState('networkidle');
  await expect(page.locator('body')).toContainText('تمت إعادة إرسال دعوة العضو بنجاح');

  await page.goto(resolveRoutePath('internal.accounts.index', '/internal/accounts'));
  await page.getByRole('link', { name: 'E2E Account A' }).click();
  await page.waitForLoadState('networkidle');
  await expect(page.locator('[data-testid="organization-members-card"]')).toHaveCount(0);
  await expect(page.locator('[data-testid="organization-member-invite-form"]')).toHaveCount(0);
});
