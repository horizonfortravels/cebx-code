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

async function openAccountsCenter(page) {
  await expect(page.locator(routeLinkSelector('internal.accounts.index', '/internal/accounts')).first()).toBeVisible();
  await page.locator(routeLinkSelector('internal.accounts.index', '/internal/accounts')).first().click();
  await page.waitForLoadState('networkidle');
  await expect(page).toHaveURL(/\/internal\/accounts$/);
}

async function openAccountDetail(page, accountName) {
  await page.getByRole('link', { name: accountName }).click();
  await page.waitForLoadState('networkidle');
}

async function submitLifecycleAction(page, action) {
  const form = page.locator(`form[action$="/${action}"]`).first();
  await expect(form).toBeVisible();
  await form.locator('input[name="note"]').fill(`browser-${action}-${Date.now()}`);
  await form.locator('button[type="submit"]').click();
  await page.waitForLoadState('networkidle');
}

async function expectOrganizationMemberAdminState(page) {
  const membersCard = page.locator('[data-testid="organization-members-card"]');
  const inviteCard = page.locator('[data-testid="organization-member-invite-card"]');
  const inviteForm = inviteCard.locator('[data-testid="organization-member-invite-form"]');

  await expect(membersCard).toBeVisible();
  await expect(inviteCard).toBeVisible();

  if (await inviteForm.count()) {
    await expect(inviteForm).toBeVisible();
    await expect(inviteCard.locator('[data-testid="organization-member-role-select"]')).toBeVisible();

    return;
  }

  await expect(inviteCard.locator('.empty-state')).toBeVisible();
}

test.describe.configure({ mode: 'serial' });

test('internal super_admin can create edit and change external account lifecycle from the accounts center', async ({ page }) => {
  const suffix = Date.now();
  const individualName = `I2B Individual ${suffix}`;
  const organizationName = `I2B Organization ${suffix}`;

  await loginWith(page, 'admin', USERS.internalSuperAdmin);
  await openAccountsCenter(page);

  await page.goto(resolveRoutePath('internal.accounts.create', '/internal/accounts/create'));
  await expect(page.locator('form[action$="/internal/accounts"]')).toBeVisible();

  await page.fill('input[name="account_name"]', individualName);
  await page.selectOption('select[name="account_type"]', 'individual');
  await page.fill('input[name="owner_name"]', 'I2B Individual Owner');
  await page.fill('input[name="owner_email"]', `i2b-individual-${suffix}@example.test`);
  await page.fill('input[name="owner_phone"]', '+966500100001');
  await page.fill('input[name="language"]', 'en');
  await page.fill('input[name="currency"]', 'USD');
  await page.fill('input[name="timezone"]', 'UTC');
  await page.locator('form[action$="/internal/accounts"] button[type="submit"]').click();
  await page.waitForLoadState('networkidle');

  await expect(page.locator('body')).toContainText(individualName);
  await expect(page.locator('a[href$="/edit"]')).toBeVisible();
  await expect(page.locator('form[action$="/activate"]')).toBeVisible();

  await page.locator('a[href$="/edit"]').first().click();
  await page.waitForLoadState('networkidle');
  await expect(page.locator('form[action*="/internal/accounts/"] input[name="name"]')).toBeVisible();
  await page.fill('input[name="name"]', `${individualName} Edited`);
  await page.fill('input[name="contact_email"]', `support-${suffix}@example.test`);
  await page.locator('form[action*="/internal/accounts/"] button[type="submit"]').click();
  await page.waitForLoadState('networkidle');
  await expect(page.locator('body')).toContainText(`${individualName} Edited`);

  await submitLifecycleAction(page, 'activate');
  await expect(page.locator('form[action$="/suspend"]')).toBeVisible();
  await expect(page.locator('form[action$="/activate"]')).toHaveCount(0);

  await submitLifecycleAction(page, 'suspend');
  await expect(page.locator('form[action$="/unsuspend"]')).toBeVisible();
  await expect(page.locator('form[action$="/suspend"]')).toHaveCount(0);

  await submitLifecycleAction(page, 'unsuspend');
  await expect(page.locator('form[action$="/suspend"]')).toBeVisible();
  await expect(page.locator('form[action$="/unsuspend"]')).toHaveCount(0);

  await submitLifecycleAction(page, 'deactivate');
  await expect(page.locator('form[action$="/activate"]')).toBeVisible();
  await expect(page.locator('form[action$="/deactivate"]')).toHaveCount(0);

  await page.goto(resolveRoutePath('internal.accounts.create', '/internal/accounts/create'));
  await expect(page.locator('form[action$="/internal/accounts"]')).toBeVisible();

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
  await page.locator('form[action$="/internal/accounts"] button[type="submit"]').click();
  await page.waitForLoadState('networkidle');

  await expect(page.locator('body')).toContainText(`${organizationName} LLC`);
  await expectOrganizationMemberAdminState(page);

  await page.locator('a[href$="/edit"]').first().click();
  await page.waitForLoadState('networkidle');
  await page.fill('input[name="trade_name"]', `${organizationName} Updated`);
  await page.locator('form[action*="/internal/accounts/"] button[type="submit"]').click();
  await page.waitForLoadState('networkidle');
  await expect(page.locator('body')).toContainText(`${organizationName} Updated`);
});

test('internal support can read accounts but cannot see create edit or lifecycle controls', async ({ page }) => {
  await loginWith(page, 'admin', USERS.internalSupport);
  await openAccountsCenter(page);

  await expect(page.locator(routeLinkSelector('internal.accounts.create', '/internal/accounts/create'))).toHaveCount(0);
  await openAccountDetail(page, 'E2E Account A');
  await expect(page.locator('a[href$="/edit"]')).toHaveCount(0);
  await expect(page.locator('input[name="note"]')).toHaveCount(0);
  await expect(page.locator('form[action$="/activate"]')).toHaveCount(0);
  await expect(page.locator('form[action$="/deactivate"]')).toHaveCount(0);
  await expect(page.locator('form[action$="/suspend"]')).toHaveCount(0);
  await expect(page.locator('form[action$="/unsuspend"]')).toHaveCount(0);
});

test('external organization user is denied from internal account management routes', async ({ page }) => {
  await loginWith(page, 'b2b', USERS.externalOrganizationOwner);

  const response = await page.goto(resolveRoutePath('internal.accounts.create', '/internal/accounts/create'));
  expect(response).not.toBeNull();
  expect(response.status()).toBe(403);
  await expect(page.locator('.panel')).toBeVisible();
  await expect(page.getByRole('heading', { level: 1, name: 'هذه الصفحة مخصصة لفريق التشغيل الداخلي في المنصة' })).toBeVisible();
  await expect(page.locator('.panel .meta')).toContainText('الحالة الحالية: 403');
  await expect(page.getByRole('link', { name: 'العودة إلى بوابة الأعمال' })).toHaveAttribute(
    'href',
    new RegExp(`${resolveRoutePath('b2b.dashboard', '/b2b/dashboard').replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}$`)
  );
  await expect(page.locator('input[name="account_name"]')).toHaveCount(0);
  await expect(page.locator('select[name="account_type"]')).toHaveCount(0);
  await expect(page.locator('form[action$="/internal/accounts"]')).toHaveCount(0);
  await expect(page.locator('body')).not.toContainText('Internal Server Error');
});
