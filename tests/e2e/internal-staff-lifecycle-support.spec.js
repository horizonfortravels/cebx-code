const { createServer } = require('node:net');
const { execSync } = require('node:child_process');
const { test, expect } = require('@playwright/test');

const PASSWORD = process.env.E2E_USER_PASSWORD || 'Password123!';

const USERS = {
  externalOrganizationOwner: 'e2e.c.organization_owner@example.test',
  internalCarrierManager: 'e2e.internal.carrier_manager@example.test',
  internalSuperAdmin: 'e2e.internal.super_admin@example.test',
  internalSupport: 'e2e.internal.support@example.test',
};

const SMTP_SETTINGS = {
  fromAddress: 'ops@example.test',
  fromName: 'CBEX Ops',
  host: '127.0.0.1',
  replyToAddress: 'support@example.test',
  replyToName: 'Support Desk',
  timeout: '15',
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

async function startSmtpSink() {
  const messages = [];

  const server = createServer((socket) => {
    let buffer = '';
    let dataMode = false;
    let messageBuffer = '';

    socket.write('220 localhost SMTP ready\r\n');

    socket.on('data', (chunk) => {
      buffer += chunk.toString('utf8');

      while (true) {
        if (dataMode) {
          const endOfData = buffer.indexOf('\r\n.\r\n');
          if (endOfData === -1) {
            break;
          }

          messageBuffer += buffer.slice(0, endOfData);
          messages.push(messageBuffer);
          messageBuffer = '';
          buffer = buffer.slice(endOfData + 5);
          dataMode = false;
          socket.write('250 2.0.0 queued\r\n');
          continue;
        }

        const lineEnd = buffer.indexOf('\r\n');
        if (lineEnd === -1) {
          break;
        }

        const line = buffer.slice(0, lineEnd);
        buffer = buffer.slice(lineEnd + 2);
        const upper = line.toUpperCase();

        if (upper.startsWith('EHLO') || upper.startsWith('HELO')) {
          socket.write('250-localhost\r\n250 OK\r\n');
          continue;
        }

        if (upper.startsWith('MAIL FROM') || upper.startsWith('RCPT TO') || upper.startsWith('RSET')) {
          socket.write('250 OK\r\n');
          continue;
        }

        if (upper === 'DATA') {
          dataMode = true;
          socket.write('354 End data with <CR><LF>.<CR><LF>\r\n');
          continue;
        }

        if (upper.startsWith('QUIT')) {
          socket.write('221 Bye\r\n');
          socket.end();
          break;
        }

        socket.write('250 OK\r\n');
      }
    });
  });

  await new Promise((resolve) => server.listen(0, '127.0.0.1', resolve));

  return {
    messages,
    port: server.address().port,
    async close() {
      await new Promise((resolve, reject) => server.close((error) => (error ? reject(error) : resolve())));
    },
  };
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

async function clickAndExpectSuccessToast(page, trigger, expectedText) {
  const toast = page.locator('.toast-container .toast.toast-success');

  await Promise.all([
    toast.waitFor({ state: 'visible' }),
    trigger.click(),
    page.waitForLoadState('networkidle'),
  ]);

  await expect(toast).toBeVisible();

  if (expectedText) {
    await expect(toast).toContainText(expectedText);
  }

  return toast;
}

async function submitAndWaitForReload(page, trigger) {
  await Promise.all([
    page.waitForNavigation({ waitUntil: 'domcontentloaded' }),
    trigger.click(),
  ]);

  await page.waitForLoadState('networkidle');
}

async function configureStoredSmtp(page, port) {
  await page.goto(resolveRoutePath('internal.smtp-settings.edit', '/internal/smtp-settings'));
  await page.waitForLoadState('networkidle');

  await expect(page).toHaveURL(/\/internal\/smtp-settings$/);
  await page.locator('input[name="enabled"]').check();
  await page.fill('input[name="host"]', SMTP_SETTINGS.host);
  await page.fill('input[name="port"]', String(port));
  await page.selectOption('select[name="encryption"]', 'none');
  await page.fill('input[name="from_name"]', SMTP_SETTINGS.fromName);
  await page.fill('input[name="from_address"]', SMTP_SETTINGS.fromAddress);
  await page.fill('input[name="reply_to_name"]', SMTP_SETTINGS.replyToName);
  await page.fill('input[name="reply_to_address"]', SMTP_SETTINGS.replyToAddress);
  await page.fill('input[name="timeout"]', SMTP_SETTINGS.timeout);
  await clickAndExpectSuccessToast(
    page,
    page.locator('form[action$="/internal/smtp-settings"] button[type="submit"]'),
  );
}

async function expectSmtpDelivery(smtpSink, baselineCount, expectedRecipient) {
  await expect.poll(() => smtpSink.messages.length).toBeGreaterThan(baselineCount);

  const delivered = smtpSink.messages[smtpSink.messages.length - 1];
  expect(delivered).toContain(expectedRecipient);
  expect(delivered).toContain('/reset-password/');

  return delivered;
}

async function openStaffDetail(page, staffName) {
  await expect(page.locator(routeLinkSelector('internal.staff.index', '/internal/staff')).first()).toBeVisible();
  await page.locator(routeLinkSelector('internal.staff.index', '/internal/staff')).first().click();
  await page.waitForLoadState('networkidle');
  await page.getByRole('link', { name: staffName }).click();
  await page.waitForLoadState('networkidle');
}

test.describe.configure({ mode: 'serial' });

let smtpSink;

test.beforeAll(async ({ browser }) => {
  smtpSink = await startSmtpSink();

  const page = await browser.newPage();

  try {
    await loginWith(page, 'admin', USERS.internalSuperAdmin);
    await configureStoredSmtp(page, smtpSink.port);
  } finally {
    await page.close();
  }
});

test.afterAll(async () => {
  if (smtpSink) {
    await smtpSink.close();
  }
});

test('internal super_admin can change internal staff lifecycle state and trigger password reset', async ({ page }) => {
  await loginWith(page, 'admin', USERS.internalSuperAdmin);
  await openStaffDetail(page, 'E2E Internal Carrier Manager');

  await expect(page.locator('[data-testid="internal-staff-lifecycle-card"]')).toBeVisible();
  await expect(page.locator('[data-testid="internal-staff-password-reset-button"]')).toBeVisible();

  await submitAndWaitForReload(page, page.locator('[data-testid="internal-staff-suspend-button"]'));
  await expect(page.locator('body')).toContainText('موقوف');
  await expect(page.locator('[data-testid="internal-staff-unsuspend-button"]')).toBeVisible();

  await submitAndWaitForReload(page, page.locator('[data-testid="internal-staff-unsuspend-button"]'));
  await expect(page.locator('body')).toContainText('نشط');
  await expect(page.locator('[data-testid="internal-staff-deactivate-button"]')).toBeVisible();

  await submitAndWaitForReload(page, page.locator('[data-testid="internal-staff-deactivate-button"]'));
  await expect(page.locator('body')).toContainText('معطل');
  await expect(page.locator('[data-testid="internal-staff-activate-button"]')).toBeVisible();

  await submitAndWaitForReload(page, page.locator('[data-testid="internal-staff-activate-button"]'));
  await expect(page.locator('body')).toContainText('نشط');

  const baselineMessages = smtpSink.messages.length;

  await submitAndWaitForReload(page, page.locator('[data-testid="internal-staff-password-reset-button"]'));
  await expect(page.locator('body')).not.toContainText('/reset-password/');
  await expect(page.locator('body')).not.toContainText('Internal Server Error');
  await expectSmtpDelivery(smtpSink, baselineMessages, USERS.internalCarrierManager);
});

test('internal support can open staff detail but cannot perform lifecycle mutation', async ({ page }) => {
  await loginWith(page, 'admin', USERS.internalSupport);
  await openStaffDetail(page, 'E2E Internal Super Admin');

  await expect(page.locator('body')).toContainText('E2E Internal Super Admin');
  await expect(page.locator('[data-testid="internal-staff-lifecycle-card"]')).toHaveCount(0);
  await expect(page.locator('[data-testid="internal-staff-password-reset-button"]')).toHaveCount(0);
  await expect(page.locator('[data-testid="internal-staff-activate-button"]')).toHaveCount(0);
  await expect(page.locator('[data-testid="internal-staff-deactivate-button"]')).toHaveCount(0);
  await expect(page.locator('[data-testid="internal-staff-suspend-button"]')).toHaveCount(0);
  await expect(page.locator('[data-testid="internal-staff-unsuspend-button"]')).toHaveCount(0);
  await expect(page.locator('body')).not.toContainText('Internal Server Error');
});

test('external organization user is denied from internal staff lifecycle surfaces', async ({ page }) => {
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
  await expect(page.locator('[data-testid="internal-staff-password-reset-button"]')).toHaveCount(0);
  await expect(page.locator('[data-testid="internal-staff-lifecycle-card"]')).toHaveCount(0);
  await expect(page.locator('body')).not.toContainText('Internal Server Error');
});
