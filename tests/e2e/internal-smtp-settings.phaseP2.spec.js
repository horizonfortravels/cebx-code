const { createServer } = require('node:net');
const { execSync } = require('node:child_process');
const { mkdirSync } = require('node:fs');
const path = require('node:path');
const { test, expect } = require('@playwright/test');

const PASSWORD = process.env.E2E_USER_PASSWORD || 'Password123!';
const SMTP_USERNAME = 'mailer@example.test';
const SMTP_PASSWORD = 'AppPassword123!';
const SCREENSHOT_DATE = new Date().toISOString().slice(0, 10).replace(/-/g, '');
const SCREENSHOT_DIR = path.join(process.cwd(), 'docs', 'browser_e2e_internal_smtp_screenshots', SCREENSHOT_DATE);

const USERS = {
  internalSuperAdmin: 'e2e.internal.super_admin@example.test',
  externalOrganizationOwner: 'e2e.c.organization_owner@example.test',
};

function ensureScreenshotDir() {
  mkdirSync(SCREENSHOT_DIR, { recursive: true });
}

async function captureScreenshot(page, fileName) {
  ensureScreenshotDir();
  await page.screenshot({
    path: path.join(SCREENSHOT_DIR, fileName),
    fullPage: true,
  });
}

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
    server,
    port: server.address().port,
    messages,
    async close() {
      await new Promise((resolve, reject) => server.close((error) => (error ? reject(error) : resolve())));
    },
  };
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

async function openPortalLogin(page, portal) {
  const response = await page.goto(resolveLoginPath(portal));
  expect(response).not.toBeNull();
  expect(response.status()).toBeLessThan(500);

  await expect(page.locator('input[name="email"]')).toBeVisible();
  await expect(page.locator('input[name="password"]')).toBeVisible();
}

async function loginWith(page, portal, email) {
  await openPortalLogin(page, portal);
  await page.fill('input[name="email"]', email);
  await page.fill('input[name="password"]', PASSWORD);
  await page.locator('button[type="submit"]').click();
  await page.waitForLoadState('networkidle');
}

async function expectSuccessToast(page) {
  const toast = page.locator('.toast-container .toast.toast-success');
  await expect(toast).toBeVisible();

  return toast;
}

async function assertSecretsAreMasked(page) {
  const usernameInput = page.locator('input[name="smtp_username"]');
  const passwordInput = page.locator('input[name="smtp_password"]');

  await expect(usernameInput).toHaveValue('');
  await expect(passwordInput).toHaveValue('');

  const usernamePlaceholder = await usernameInput.getAttribute('placeholder');
  const passwordPlaceholder = await passwordInput.getAttribute('placeholder');

  expect(usernamePlaceholder).toBeTruthy();
  expect(usernamePlaceholder).not.toContain(SMTP_USERNAME);
  expect(passwordPlaceholder).toBeTruthy();
  expect(passwordPlaceholder).toContain('*');
  expect(passwordPlaceholder).not.toContain(SMTP_PASSWORD);

  await expect(page.locator('body')).not.toContainText(SMTP_USERNAME);
  await expect(page.locator('body')).not.toContainText(SMTP_PASSWORD);
}

test.describe.configure({ mode: 'serial' });

test('internal SMTP settings save and test-send work while external access is denied', async ({ browser, page }) => {
  const smtpSink = await startSmtpSink();

  try {
    await loginWith(page, 'admin', USERS.internalSuperAdmin);
    await page.goto('/internal/smtp-settings');

    await expect(page).toHaveURL(/\/internal\/smtp-settings/);
    await expect(page.locator('h1')).toContainText('SMTP');
    await captureScreenshot(page, '01-internal-smtp-settings.png');

    await page.locator('input[name="enabled"]').check();
    await page.fill('input[name="host"]', '127.0.0.1');
    await page.fill('input[name="port"]', String(smtpSink.port));
    await page.selectOption('select[name="encryption"]', 'none');
    await page.fill('input[name="smtp_username"]', SMTP_USERNAME);
    await page.fill('input[name="smtp_password"]', SMTP_PASSWORD);
    await page.fill('input[name="from_name"]', 'CBEX Ops');
    await page.fill('input[name="from_address"]', 'ops@example.test');
    await page.fill('input[name="reply_to_name"]', 'Support');
    await page.fill('input[name="reply_to_address"]', 'support@example.test');
    await page.fill('input[name="timeout"]', '15');
    await page.locator('form[action$="/internal/smtp-settings"] button[type="submit"]').click();
    await page.waitForLoadState('networkidle');

    await expect(page).toHaveURL(/\/internal\/smtp-settings/);
    await expectSuccessToast(page);
    await assertSecretsAreMasked(page);
    await captureScreenshot(page, '02-settings-saved-and-masked.png');

    await page.locator('form[action$="/internal/smtp-settings/test-connection"] button[type="submit"]').click();
    await page.waitForLoadState('networkidle');
    await expectSuccessToast(page);

    await page.fill('input[name="destination"]', 'probe@example.test');
    await page.locator('form[action$="/internal/smtp-settings/test-email"] button[type="submit"]').click();
    await page.waitForLoadState('networkidle');

    await expectSuccessToast(page);
    await assertSecretsAreMasked(page);
    await expect.poll(() => smtpSink.messages.length).toBeGreaterThan(0);

    const delivered = smtpSink.messages[smtpSink.messages.length - 1];
    expect(delivered).toContain('probe@example.test');
    expect(delivered).toContain('CBEX SMTP Test');
    await captureScreenshot(page, '03-test-email-success.png');

    const externalContext = await browser.newContext();

    try {
      const externalPage = await externalContext.newPage();
      await loginWith(externalPage, 'b2b', USERS.externalOrganizationOwner);
      const denied = await externalPage.goto('/internal/smtp-settings');

      expect(denied).not.toBeNull();
      expect(denied.status()).toBe(403);
      await expect(externalPage.locator('input[name="host"]')).toHaveCount(0);
      await expect(externalPage.locator('body')).not.toContainText('RuntimeException');
      await expect(externalPage.locator('body')).not.toContainText('Stack trace');
      await captureScreenshot(externalPage, '04-external-access-denied.png');
    } finally {
      await externalContext.close();
    }
  } finally {
    await smtpSink.close();
  }
});
