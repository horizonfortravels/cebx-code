const fs = require('node:fs');
const path = require('node:path');
const { test, expect } = require('@playwright/test');

const PASSWORD = process.env.E2E_USER_PASSWORD || 'Password123!';
const B2B_OWNER = 'e2e.c.organization_owner@example.test';
const SCREENSHOT_DATE = '20260329';
const SCREENSHOT_DIR = path.resolve(process.cwd(), `docs/browser_e2e_public_tracking_screenshots/${SCREENSHOT_DATE}`);
const REPORT_PATH = path.resolve(process.cwd(), 'docs/browser_e2e_public_tracking_report.md');

test.describe.configure({ mode: 'serial' });
test.setTimeout(15 * 60 * 1000);

function ensureArtifactDir() {
  fs.mkdirSync(SCREENSHOT_DIR, { recursive: true });
}

async function loginB2b(page) {
  await page.goto('/b2b/login');
  await expect(page.locator('#login-email')).toBeVisible();
  await expect(page.locator('#login-password')).toBeVisible();

  await page.locator('#login-email').fill(B2B_OWNER);
  await page.locator('#login-password').fill(PASSWORD);
  await page.locator('form.login-form button[type="submit"]').click();
  await page.waitForLoadState('networkidle');

  await expect(page).toHaveURL(/\/b2b\/dashboard/);
}

async function createIssuedShipment(page) {
  await page.goto('/b2b/shipments/create');
  await page.waitForLoadState('networkidle');

  const main = page.getByRole('main');
  const shipmentForm = main.locator('form[action$="/b2b/shipments"]').first();

  await expect(shipmentForm).toBeVisible();
  await expect(shipmentForm.locator('button[type="submit"]')).toBeVisible();

  await shipmentForm.locator('input[name="sender_name"]').fill('PT SENDER SECRET');
  await shipmentForm.locator('input[name="sender_company"]').fill('PT Sender Co');
  await shipmentForm.locator('input[name="sender_phone"]').fill('+14015550101');
  await shipmentForm.locator('input[name="sender_email"]').fill('sender.secret@example.test');
  await shipmentForm.locator('input[name="sender_address_1"]').fill('1 Market Street');
  await shipmentForm.locator('input[name="sender_address_2"]').fill('Suite 10');
  await shipmentForm.locator('input[name="sender_city"]').fill('Providence');
  await shipmentForm.locator('input[name="sender_state"]').fill('RI');
  await shipmentForm.locator('input[name="sender_postal_code"]').fill('02903');
  await shipmentForm.locator('input[name="sender_country"]').fill('US');
  await shipmentForm.locator('input[name="recipient_name"]').fill('PT RECIPIENT SECRET');
  await shipmentForm.locator('input[name="recipient_company"]').fill('PT Recipient Co');
  await shipmentForm.locator('input[name="recipient_phone"]').fill('+12125550123');
  await shipmentForm.locator('input[name="recipient_email"]').fill('recipient.secret@example.test');
  await shipmentForm.locator('input[name="recipient_address_1"]').fill('350 5th Ave');
  await shipmentForm.locator('input[name="recipient_address_2"]').fill('Floor 20');
  await shipmentForm.locator('input[name="recipient_city"]').fill('New York');
  await shipmentForm.locator('input[name="recipient_state"]').fill('NY');
  await shipmentForm.locator('input[name="recipient_postal_code"]').fill('10118');
  await shipmentForm.locator('input[name="recipient_country"]').fill('US');
  await shipmentForm.locator('input[name="parcels[0][weight]"]').fill('1.5');
  await shipmentForm.locator('input[name="parcels[0][length]"]').fill('20');
  await shipmentForm.locator('input[name="parcels[0][width]"]').fill('15');
  await shipmentForm.locator('input[name="parcels[0][height]"]').fill('10');

  await shipmentForm.locator('button[type="submit"]').click();
  await page.waitForLoadState('networkidle');
  await expect(page).toHaveURL(/\/b2b\/shipments\/create\?draft=/);

  const createUrl = new URL(page.url());
  const draftId = createUrl.searchParams.get('draft');
  if (!draftId) {
    throw new Error(`Could not extract draft id from URL: ${page.url()}`);
  }

  const referenceMatch = (await page.locator('body').innerText()).match(/SHP-[A-Z0-9-]+/);
  const reference = referenceMatch ? referenceMatch[0] : '';

  const offersLink = page.locator(`a[href*="/shipments/${draftId}/offers"]`).first();
  await expect(offersLink).toBeVisible();
  await offersLink.click();
  await page.waitForLoadState('networkidle');

  await expect(page).toHaveURL(new RegExp(`/b2b/shipments/${draftId}/offers`));
  await expect(page.getByRole('heading', { name: 'مقارنة عروض الشحن', exact: true })).toBeVisible();

  const fetchOffers = page.locator('form[action*="/offers/fetch"] button[type="submit"]').first();
  await expect(fetchOffers).toBeVisible();
  await fetchOffers.click();
  await page.waitForLoadState('networkidle');

  const selectOffer = page.locator('form[action*="/offers/select"] button[type="submit"]').first();
  await expect(selectOffer).toBeVisible();
  await selectOffer.click();
  await page.waitForLoadState('networkidle');

  await expect(page).toHaveURL(new RegExp(`/b2b/shipments/${draftId}/declaration`));

  const declarationForm = page.locator('form[action*="/declaration"]').first();
  await expect(declarationForm).toBeVisible();
  await declarationForm.locator('input[name="contains_dangerous_goods"][value="no"]').check();
  await declarationForm.locator('input[name="accept_disclaimer"]').check();
  await declarationForm.locator('button[type="submit"]').click();
  await page.waitForLoadState('networkidle');

  const completionLink = page.locator('[data-testid="shipment-completion-link"]');
  await expect(completionLink).toBeVisible();
  await completionLink.click();
  await page.waitForLoadState('networkidle');
  await expect(page).toHaveURL(new RegExp(`/b2b/shipments/${draftId}$`));

  const walletPreflight = page.locator('[data-testid="wallet-preflight-button"]');
  await expect(walletPreflight).toBeVisible();
  await walletPreflight.click();
  await page.waitForLoadState('networkidle');

  const issueButton = page.locator('[data-testid="carrier-issue-button"]');
  await expect(issueButton).toBeVisible();
  await issueButton.click();
  await page.waitForLoadState('networkidle');

  const privateMain = page.getByRole('main');
  const publicTrackingLink = page.locator('[data-testid="public-tracking-link"]');
  const privateTimelineCard = privateMain.locator('.card').filter({
    has: page.locator('.card-title span', { hasText: 'التسلسل الزمني' }),
  }).first();
  const notificationsCard = privateMain.locator('.card').filter({
    has: page.locator('.card-title span', { hasText: 'الإشعارات المرتبطة بالشحنة' }),
  }).first();

  await expect(publicTrackingLink).toBeVisible({ timeout: 180000 });
  await expect(page.locator('[data-testid="shipment-notifications-link"]')).toBeVisible();
  await expect(page.getByRole('heading', { name: 'الحالة الزمنية للشحنة', exact: true })).toBeVisible();
  await expect(privateTimelineCard).toBeVisible();
  await expect(notificationsCard).toBeVisible();

  const href = await publicTrackingLink.getAttribute('href');
  if (!href || !href.includes('/track/')) {
    throw new Error(`Unexpected public tracking link: ${href}`);
  }

  const bodyText = await page.locator('body').innerText();
  const trackingMatch = bodyText.match(/\b\d{12}\b/);
  const trackingNumber = trackingMatch ? trackingMatch[0] : '';

  return {
    draftId,
    privateUrl: page.url(),
    publicUrl: href,
    reference,
    trackingNumber,
  };
}

async function verifyPublicTracking({ browser, baseURL, publicUrl, reference, trackingNumber }) {
  const guestContext = await browser.newContext({ baseURL, locale: 'ar-SA' });
  const guestPage = await guestContext.newPage();

  const response = await guestPage.goto(publicUrl);
  expect(response).not.toBeNull();
  expect(response.status()).toBe(200);
  await guestPage.waitForLoadState('networkidle');

  const summaryPanel = guestPage.locator('section.content > .panel').nth(1);
  const timelinePanel = guestPage.locator('section.content > .panel').nth(2);
  const carrierMetric = summaryPanel.locator('.metric').filter({
    has: summaryPanel.getByText('الناقل', { exact: true }),
  }).locator('.metric-value');

  await expect(guestPage).toHaveURL(/\/track\//);
  await expect(guestPage.getByRole('heading', { name: 'يمكن تتبع هذه الشحنة دون تسجيل دخول', exact: true })).toBeVisible();
  await expect(timelinePanel.getByText('محطات التتبع', { exact: true })).toBeVisible();
  await expect(carrierMetric).toContainText('FedEx');

  const bodyText = await guestPage.locator('body').innerText();
  for (const required of ['التتبع العام', 'يمكن تتبع هذه الشحنة دون تسجيل دخول', 'تم التسليم', 'محطات التتبع']) {
    expect(bodyText).toContain(required);
  }

  for (const forbidden of [
    'PT SENDER SECRET',
    'sender.secret@example.test',
    'PT RECIPIENT SECRET',
    'recipient.secret@example.test',
    reference,
    trackingNumber,
    'Correlation:',
    'الإشعارات',
    'wallet',
  ].filter(Boolean)) {
    expect(bodyText).not.toContain(forbidden);
  }

  expect(bodyText).not.toMatch(/\b\d{12}\b/);
  await expect(guestPage.locator('body')).not.toContainText('تسجيل الدخول');

  const invalidPage = await guestContext.newPage();
  const invalidResponse = await invalidPage.goto('/track/NO-SUCH-PUBLIC-TOKEN');
  expect(invalidResponse).not.toBeNull();
  expect(invalidResponse.status()).toBe(404);
  await invalidPage.waitForLoadState('networkidle');

  await expect(invalidPage).toHaveURL(/\/track\/NO-SUCH-PUBLIC-TOKEN$/);
  await expect(invalidPage.getByRole('heading', { name: 'The requested page is not available.', exact: true })).toBeVisible();
  await expect(invalidPage.getByText('HTTP 404', { exact: true })).toBeVisible();

  const invalidBody = await invalidPage.locator('body').innerText();
  for (const forbidden of ['PT SENDER SECRET', 'PT RECIPIENT SECRET', reference, trackingNumber].filter(Boolean)) {
    expect(invalidBody).not.toContain(forbidden);
  }

  return { guestContext, guestPage, invalidPage };
}

function writeReport({ privateUrl, publicUrl, screenshots, reference, trackingNumber }) {
  const lines = [
    '# Phase 4E Public Tracking Browser Report',
    '',
    '- Result: PASS',
    '- Fixture strategy: normal funded B2B browser flow to issued shipment page, then guest public link',
    `- Private issued shipment page: ${privateUrl}`,
    `- Public tracking path: ${publicUrl.replace(/\/track\/.+$/, '/track/[redacted]')}`,
    `- Shipment reference checked for non-leakage: ${reference || '[not-detected]'}`,
    `- Full tracking number checked for non-leakage: ${trackingNumber || '[not-detected]'}`,
    '',
    '## Assertions',
    '- Public tracking page opened without login in a fresh guest browser context.',
    '- The guest page rendered the safe public subset only.',
    '- Canonical status and timeline were visible on the public page.',
    '- Invalid token path returned 404 without leaking shipment data.',
    '- The private issued shipment page still showed the public tracking link, timeline, and notifications surfaces.',
    '',
    '## Artifacts',
    ...screenshots.map((file) => `- ${file}`),
    '',
  ];

  fs.writeFileSync(REPORT_PATH, lines.join('\n'));
}

test('phase 4e public tracking browser spot-check', async ({ page, browser, baseURL }) => {
  ensureArtifactDir();

  await loginB2b(page);
  const fixture = await createIssuedShipment(page);

  await page.screenshot({
    path: path.join(SCREENSHOT_DIR, 'phase4e-private-issued-shipment.png'),
    fullPage: true,
  });

  const publicResult = await verifyPublicTracking({
    browser,
    baseURL: baseURL || process.env.E2E_BASE_URL || 'http://127.0.0.1:8010',
    publicUrl: fixture.publicUrl,
    reference: fixture.reference,
    trackingNumber: fixture.trackingNumber,
  });

  await publicResult.guestPage.screenshot({
    path: path.join(SCREENSHOT_DIR, 'phase4e-public-tracking-valid.png'),
    fullPage: true,
  });

  await publicResult.invalidPage.screenshot({
    path: path.join(SCREENSHOT_DIR, 'phase4e-public-tracking-invalid.png'),
    fullPage: true,
  });

  await publicResult.guestContext.close();

  writeReport({
    privateUrl: fixture.privateUrl,
    publicUrl: fixture.publicUrl,
    reference: fixture.reference,
    trackingNumber: fixture.trackingNumber,
    screenshots: [
      path.join(SCREENSHOT_DIR, 'phase4e-private-issued-shipment.png'),
      path.join(SCREENSHOT_DIR, 'phase4e-public-tracking-valid.png'),
      path.join(SCREENSHOT_DIR, 'phase4e-public-tracking-invalid.png'),
    ],
  });
});
