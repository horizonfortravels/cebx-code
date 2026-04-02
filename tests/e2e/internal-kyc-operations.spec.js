const { execSync } = require('node:child_process');
const { test, expect } = require('@playwright/test');

const PASSWORD = process.env.E2E_USER_PASSWORD || 'Password123!';

const USERS = {
  externalOrganizationOwner: 'e2e.c.organization_owner@example.test',
  internalCarrierManager: 'e2e.internal.carrier_manager@example.test',
  internalOpsReadonly: 'e2e.internal.ops_readonly@example.test',
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

  const fallbacks = {
    admin: ['/admin/login', '/login'],
    b2b: ['/b2b/login', '/login'],
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

async function openKycQueue(page) {
  await expect(page.locator(routeLinkSelector('internal.kyc.index', '/internal/kyc')).first()).toBeVisible();
  await page.locator(routeLinkSelector('internal.kyc.index', '/internal/kyc')).first().click();
  await page.waitForLoadState('networkidle');
  await expect(page).toHaveURL(/\/internal\/kyc$/);
  await expect(page.locator('[data-testid="internal-kyc-table"]')).toBeVisible();
}

async function openCase(page, name) {
  await page.getByRole('link', { name }).click();
  await page.waitForLoadState('networkidle');
  await expect(page.locator('[data-testid="kyc-status-card"]')).toBeVisible();
}

async function submitDecisionAndExpectSuccess(page, trigger, expectedToastText) {
  const toast = page.locator('.toast-container .toast.toast-success');

  await Promise.all([
    page.waitForNavigation({ waitUntil: 'domcontentloaded' }),
    trigger.click(),
  ]);

  await page.waitForLoadState('networkidle');
  await expect(toast).toBeVisible();

  if (expectedToastText) {
    await expect(toast).toContainText(expectedToastText);
  }
}

test.describe.configure({ mode: 'serial' });

test('internal super_admin can approve a pending KYC case from the queue', async ({ page }) => {
  await loginWith(page, 'admin', USERS.internalSuperAdmin);
  await openKycQueue(page);

  await expect(page.locator('body')).toContainText('E2E Account A');
  await openCase(page, 'E2E Account A');

  await expect(page.locator('[data-testid="kyc-review-card"]')).toBeVisible();
  await expect(page.locator('[data-testid="kyc-operational-effects-card"]')).toBeVisible();
  await expect(page.locator('[data-testid="kyc-approve-button"]')).toBeVisible();
  await expect(page.locator('[data-testid="kyc-reject-button"]')).toBeVisible();
  await expect(page.locator('[data-testid="kyc-request-more-info-button"]')).toHaveCount(0);

  await page.locator('[data-testid="kyc-approve-form"] textarea[name="notes"]').fill('اعتماد تشغيلي من طابور KYC الداخلي.');
  await submitDecisionAndExpectSuccess(
    page,
    page.locator('[data-testid="kyc-approve-button"]'),
    'تم اعتماد حالة التحقق'
  );

  await expect(page.locator('[data-testid="kyc-current-status-label"]')).toContainText('مقبول');
  await expect(page.locator('[data-testid="kyc-approve-button"]')).toHaveCount(0);
  await expect(page.locator('[data-testid="kyc-next-action"]')).toBeVisible();
  await expect(page.locator('[data-testid="kyc-reject-button"]')).toHaveCount(0);
  await expect(page.locator('[data-testid="kyc-audit-card"]')).toContainText('تم اعتماد حالة التحقق');
  await expect(page.locator('body')).not.toContainText('Internal Server Error');
});

test('internal super_admin can reject another pending KYC case from the queue', async ({ page }) => {
  await loginWith(page, 'admin', USERS.internalSuperAdmin);
  await openKycQueue(page);

  await expect(page.locator('body')).toContainText('E2E Account B');
  await openCase(page, 'E2E Account B');

  await expect(page.locator('[data-testid="kyc-review-card"]')).toBeVisible();
  await expect(page.locator('[data-testid="kyc-operational-effects-card"]')).toBeVisible();
  await page.locator('[data-testid="kyc-reject-form"] input[name="reason"]').fill('الوثيقة المرفوعة لا تطابق بيانات الحساب الحالية.');
  await page.locator('[data-testid="kyc-reject-form"] textarea[name="notes"]').fill('يلزم رفع نسخة أوضح مع مطابقة الاسم.');

  await submitDecisionAndExpectSuccess(
    page,
    page.locator('[data-testid="kyc-reject-button"]'),
    'تم رفض حالة التحقق'
  );

  await expect(page.locator('[data-testid="kyc-current-status-label"]')).toContainText('مرفوض');
  await expect(page.locator('[data-testid="kyc-rejection-reason"]')).toContainText('الوثيقة المرفوعة لا تطابق بيانات الحساب الحالية.');
  await expect(page.locator('[data-testid="kyc-approve-button"]')).toHaveCount(0);
  await expect(page.locator('[data-testid="kyc-reject-button"]')).toHaveCount(0);
  await expect(page.locator('[data-testid="kyc-audit-card"]')).toContainText('تم رفض حالة التحقق');
  await expect(page.locator('body')).not.toContainText('Internal Server Error');
});

test('internal super_admin can review KYC documents and manage restriction overlays', async ({ page }) => {
  await loginWith(page, 'admin', USERS.internalSuperAdmin);
  await openKycQueue(page);

  await openCase(page, 'E2E Account C');
  await expect(page.locator('[data-testid="kyc-documents-card"]')).toBeVisible();
  await expect(page.locator('[data-testid="kyc-operational-effects-card"]')).toBeVisible();
  await expect(page.locator('[data-testid="kyc-blocked-shipments-count"]')).toContainText('2');
  await expect(page.locator('[data-testid="kyc-impacted-shipments-card"]')).toContainText('SHP-KYC-C-002');
  await expect(page.locator('[data-testid="kyc-document-item"]')).toHaveCount(2);
  await expect(page.locator('[data-testid="kyc-documents-card"]')).toContainText('e2e-account-c-cr.pdf');
  await expect(page.locator('body')).not.toContainText('kyc/e2e-account-c-cr.pdf');

  await expect(page.locator('[data-testid="kyc-restriction-management-card"]')).toBeVisible();

  await page.locator('[data-testid="kyc-shipping_limit-restriction-form"] input[name="quota_value"]').fill('25');
  await page.locator('[data-testid="kyc-shipping_limit-restriction-form"] textarea[name="note"]').fill('Temporary operational shipment cap.');
  await submitDecisionAndExpectSuccess(
    page,
    page.locator('[data-testid="kyc-shipping_limit-save-button"]'),
    'حد الشحن الكلي'
  );

  await expect(page.locator('[data-testid="kyc-restrictions-card"]')).toContainText('25');

  await page.locator('[data-testid="kyc-international_shipping-restriction-form"] textarea[name="note"]').fill('Keep international shipping blocked until the next internal review.');
  await submitDecisionAndExpectSuccess(
    page,
    page.locator('[data-testid="kyc-international_shipping-enable-button"]'),
    'تعليق الشحن الدولي'
  );

  await expect(page.locator('[data-testid="kyc-restrictions-card"]')).toContainText('تعليق الشحن الدولي');
  await expect(page.locator('body')).not.toContainText('Internal Server Error');
});

test('internal support can open the KYC queue and detail but cannot see mutation controls', async ({ page }) => {
  await loginWith(page, 'admin', USERS.internalSupport);
  await openKycQueue(page);

  await openCase(page, 'E2E Account C');
  await expect(page.locator('[data-testid="kyc-documents-card"]')).toBeVisible();
  await expect(page.locator('[data-testid="kyc-operational-effects-card"]')).toBeVisible();
  await expect(page.locator('[data-testid="kyc-blocked-shipments-count"]')).toContainText('2');
  await expect(page.locator('[data-testid="kyc-documents-card"]')).toContainText('e2e-account-c-cr.pdf');
  await expect(page.locator('body')).not.toContainText('kyc/e2e-account-c-cr.pdf');
  await expect(page.locator('[data-testid="kyc-review-card"]')).toHaveCount(0);
  await expect(page.locator('[data-testid="kyc-approve-button"]')).toHaveCount(0);
  await expect(page.locator('[data-testid="kyc-reject-button"]')).toHaveCount(0);
  await expect(page.locator('[data-testid="kyc-restriction-management-card"]')).toHaveCount(0);
  await expect(page.locator('body')).not.toContainText('Internal Server Error');
});

test('internal ops_readonly can open the KYC queue and detail but cannot see mutation controls', async ({ page }) => {
  await loginWith(page, 'admin', USERS.internalOpsReadonly);
  await openKycQueue(page);

  await openCase(page, 'E2E Account A');
  await expect(page.locator('[data-testid="kyc-documents-card"]')).toBeVisible();
  await expect(page.locator('[data-testid="kyc-operational-effects-card"]')).toBeVisible();
  await expect(page.locator('[data-testid="kyc-next-action"]')).toBeVisible();
  await expect(page.locator('[data-testid="kyc-documents-card"]')).toContainText('e2e-account-a-id.pdf');
  await expect(page.locator('body')).not.toContainText('kyc/e2e-account-a-id.pdf');
  await expect(page.locator('[data-testid="kyc-review-card"]')).toHaveCount(0);
  await expect(page.locator('[data-testid="kyc-approve-button"]')).toHaveCount(0);
  await expect(page.locator('[data-testid="kyc-reject-button"]')).toHaveCount(0);
  await expect(page.locator('[data-testid="kyc-restriction-management-card"]')).toHaveCount(0);
  await expect(page.locator('body')).not.toContainText('Internal Server Error');
});

test('external organization user is denied from the internal kyc routes', async ({ page }) => {
  await loginWith(page, 'b2b', USERS.externalOrganizationOwner);

  const response = await page.goto(resolveRoutePath('internal.kyc.index', '/internal/kyc'));
  expect(response).not.toBeNull();
  expect(response.status()).toBe(403);

  await expect(page.locator('.panel')).toBeVisible();
  await expect(page.getByRole('heading', { level: 1 })).toContainText('هذه الصفحة مخصصة لفريق التشغيل الداخلي في المنصة');
  await expect(page.locator('.panel .meta')).toContainText('الحالة الحالية: 403');
  await expect(page.locator('[data-testid="internal-kyc-table"]')).toHaveCount(0);
  await expect(page.locator('[data-testid="kyc-review-card"]')).toHaveCount(0);
  await expect(page.locator('[data-testid="kyc-restriction-management-card"]')).toHaveCount(0);
  await expect(page.locator('body')).not.toContainText('Internal Server Error');
});

test('internal carrier_manager is denied from the internal kyc routes', async ({ page }) => {
  await loginWith(page, 'admin', USERS.internalCarrierManager);

  const response = await page.goto(resolveRoutePath('internal.kyc.index', '/internal/kyc'));
  expect(response).not.toBeNull();
  expect(response.status()).toBe(403);

  await expect(page.locator('.panel')).toBeVisible();
  await expect(page.locator('.panel .meta')).toContainText('403');
  await expect(page.locator('[data-testid="internal-kyc-table"]')).toHaveCount(0);
  await expect(page.locator('[data-testid="kyc-review-card"]')).toHaveCount(0);
  await expect(page.locator('[data-testid="kyc-restriction-management-card"]')).toHaveCount(0);
  await expect(page.locator('body')).not.toContainText('Internal Server Error');
});
