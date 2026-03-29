const { createServer } = require('node:net');
const { execSync } = require('node:child_process');
const { test, expect } = require('@playwright/test');

const PASSWORD = process.env.E2E_USER_PASSWORD || 'Password123!';

const USERS = {
  internalSuperAdmin: 'e2e.internal.super_admin@example.test',
  externalOrganizationOwner: 'e2e.c.organization_owner@example.test',
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

test.describe.configure({ mode: 'serial' });

test('internal SMTP settings save and test-send work while external access is denied', async ({ page }) => {
  const smtpSink = await startSmtpSink();

  try {
    await loginWith(page, 'admin', USERS.internalSuperAdmin);
    await page.goto('/internal/smtp-settings');

    await expect(page).toHaveURL(/\/internal\/smtp-settings/);
    await expect(page.locator('h1')).toContainText('SMTP');

    await page.locator('input[name="enabled"]').check();
    await page.fill('input[name="host"]', '127.0.0.1');
    await page.fill('input[name="port"]', String(smtpSink.port));
    await page.selectOption('select[name="encryption"]', 'none');
    await page.fill('input[name="from_name"]', 'CBEX Ops');
    await page.fill('input[name="from_address"]', 'ops@example.test');
    await page.fill('input[name="timeout"]', '15');
    await page.getByRole('button', { name: 'حفظ الإعدادات' }).click();
    await page.waitForLoadState('networkidle');

    await expect(page.locator('body')).toContainText('SMTP');
    await expect(page.locator('body')).toContainText('تم حفظ');

    await page.getByRole('button', { name: 'اختبار الاتصال' }).click();
    await page.waitForLoadState('networkidle');
    await expect(page.locator('body')).toContainText('اختبار');

    await page.fill('input[name="destination"]', 'probe@example.test');
    await page.getByRole('button', { name: 'إرسال بريد تجريبي' }).click();
    await page.waitForLoadState('networkidle');

    await expect(page.locator('body')).toContainText('تم إرسال بريد تجريبي');
    await expect.poll(() => smtpSink.messages.length).toBeGreaterThan(0);

    const delivered = smtpSink.messages[smtpSink.messages.length - 1];
    expect(delivered).toContain('probe@example.test');
    expect(delivered).toContain('CBEX SMTP Test');

    await page.locator('form[action$="/logout"] button').click();
    await page.waitForLoadState('networkidle');

    await loginWith(page, 'b2b', USERS.externalOrganizationOwner);
    const denied = await page.goto('/internal/smtp-settings');
    expect(denied).not.toBeNull();
    expect(denied.status()).toBe(403);
    await expect(page.locator('body')).toContainText('هذه الصفحة مخصصة لفريق التشغيل الداخلي');
    await expect(page.locator('body')).not.toContainText('RuntimeException');
    await expect(page.locator('body')).not.toContainText('Stack trace');
  } finally {
    await smtpSink.close();
  }
});
