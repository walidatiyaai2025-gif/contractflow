import { chromium } from 'playwright';
import fs from 'node:fs';
import path from 'node:path';

const baseUrl = process.env.SC_QA_BASE_URL || 'http://127.0.0.1:8889';
const username = process.env.SC_QA_USER || 'visual-admin';
const password = process.env.SC_QA_PASSWORD || 'VisualQa-Only-2026!';
const locale = process.env.SC_QA_LOCALE || 'ar';
const scope = process.env.SC_QA_SCOPE || 'lead';
const widths = (process.env.SC_QA_WIDTHS || (locale === 'ar' ? '390,768,1440' : '1440'))
  .split(',').map(v => Number(v.trim())).filter(Number.isFinite);
const outRoot = process.env.SC_QA_OUTPUT || 'visual-qa-artifacts';
const sourceSha = process.env.GITHUB_SHA || 'local';

const screens = [
  ['SC-001', 'lead', '/wp-admin/admin.php?page=safecontracts', 'REF_003_WordPress_Dashboard.png'],
  ['SC-002', 'lead', '/wp-admin/admin.php?page=safecontracts&safecontracts_group=contracts', 'REF_002_WordPress_Plugin_Masterboard_DesignSystem.png'],
  ['SC-003', 'lead', '/wp-admin/admin.php?page=safecontracts&safecontracts_group=finance', 'REF_002_WordPress_Plugin_Masterboard_DesignSystem.png'],
  ['SC-004', 'lead', '/wp-admin/admin.php?page=safecontracts&safecontracts_group=operations', 'REF_002_WordPress_Plugin_Masterboard_DesignSystem.png'],
  ['SC-005', 'lead', '/wp-admin/admin.php?page=safecontracts&safecontracts_group=notifications', 'REF_002_WordPress_Plugin_Masterboard_DesignSystem.png'],
  ['SC-006', 'lead', '/wp-admin/admin.php?page=safecontracts&safecontracts_group=access', 'REF_002_WordPress_Plugin_Masterboard_DesignSystem.png'],
  ['SC-007', 'lead', '/wp-admin/admin.php?page=safecontracts&safecontracts_group=system', 'REF_002_WordPress_Plugin_Masterboard_DesignSystem.png'],
  ['SC-008', 'lead', '/wp-admin/admin.php?page=safecontracts&safecontracts_group=help', 'REF_002_WordPress_Plugin_Masterboard_DesignSystem.png'],
  ['SC-009', 'lead', '/wp-admin/admin.php?page=safecontracts&safecontracts_group=other', 'REF_002_WordPress_Plugin_Masterboard_DesignSystem.png'],
  ['SC-010', 'lead', '/wp-admin/admin.php?page=safecontracts-settings', 'REF_002_WordPress_Plugin_Masterboard_DesignSystem.png'],
  ['SC-011', 'lead', '/wp-admin/admin.php?page=safecontracts-runtime-inspector', 'REF_002_WordPress_Plugin_Masterboard_DesignSystem.png'],
  ['SC-012', 'lead', '/wp-admin/admin.php?page=safecontracts-migration-recovery', 'REF_002_WordPress_Plugin_Masterboard_DesignSystem.png'],
  ['SC-013', 'worker1', '/wp-admin/admin.php?page=safecontracts-customers', 'REF_004_WordPress_Customers.png'],
  ['SC-014', 'worker1', '/wp-admin/admin.php?page=safecontracts-suppliers', 'REF_001_Premium_Module_Masterboard.png'],
  ['SC-015', 'worker1', '/wp-admin/admin.php?page=safecontracts-contracts', 'REF_001_Premium_Module_Masterboard.png'],
  ['SC-016', 'worker1', '/wp-admin/admin.php?page=safecontracts-archive', 'REF_002_WordPress_Plugin_Masterboard_DesignSystem.png'],
  ['SC-017', 'worker2', '/wp-admin/admin.php?page=safecontracts-payments', 'REF_005_WordPress_Payments.png'],
  ['SC-018', 'worker2', '/wp-admin/admin.php?page=safecontracts-collections', 'REF_005_WordPress_Payments.png'],
  ['SC-019', 'worker2', '/wp-admin/admin.php?page=safecontracts-followups', 'REF_001_Premium_Module_Masterboard.png'],
  ['SC-020', 'worker2', '/wp-admin/admin.php?page=safecontracts-finance', 'REF_001_Premium_Module_Masterboard.png'],
  ['SC-021', 'worker2', '/wp-admin/admin.php?page=safecontracts-reports', 'REF_001_Premium_Module_Masterboard.png'],
  ['SC-022', 'worker2', '/wp-admin/admin.php?page=safecontracts-imports', 'REF_002_WordPress_Plugin_Masterboard_DesignSystem.png'],
  ['SC-023', 'worker2', '/wp-admin/admin.php?page=safecontracts-payment-methods', 'REF_005_WordPress_Payments.png'],
  ['SC-024', 'worker3', '/wp-admin/admin.php?page=safecontracts-notification-center', 'REF_001_Premium_Module_Masterboard.png'],
  ['SC-025', 'worker3', '/wp-admin/admin.php?page=safecontracts-notifications', 'REF_006_WordPress_Notification_Settings.png'],
  ['SC-026', 'worker3', '/wp-admin/admin.php?page=safecontracts-notification-schedule', 'REF_006_WordPress_Notification_Settings.png'],
  ['SC-027', 'worker3', '/wp-admin/admin.php?page=safecontracts-notification-settings', 'REF_006_WordPress_Notification_Settings.png'],
  ['SC-028', 'worker3', '/wp-admin/admin.php?page=safecontracts-email-settings', 'REF_006_WordPress_Notification_Settings.png'],
  ['SC-029', 'worker3', '/wp-admin/admin.php?page=safecontracts-active-users', 'REF_007_WordPress_Active_Users.png'],
  ['SC-030', 'worker3', '/wp-admin/admin.php?page=safecontracts-users-roles', 'REF_007_WordPress_Active_Users.png'],
  ['SC-031', 'worker3', '/wp-admin/admin.php?page=safecontracts-firebase-settings', 'REF_002_WordPress_Plugin_Masterboard_DesignSystem.png'],
  ['SC-032', 'worker3', '/wp-admin/admin.php?page=safecontracts-mobile-configuration', 'REF_002_WordPress_Plugin_Masterboard_DesignSystem.png'],
  ['SC-033', 'worker3', '/wp-admin/admin.php?page=safecontracts-translations', 'REF_002_WordPress_Plugin_Masterboard_DesignSystem.png'],
  ['SC-034', 'worker3', '/wp-admin/admin.php?page=safecontracts-user-guide', 'REF_002_WordPress_Plugin_Masterboard_DesignSystem.png'],
];

const selected = screens.filter(([, owner]) => scope === 'all' || owner === scope);
if (!selected.length) throw new Error(`No visual QA screens selected for scope=${scope}`);

fs.mkdirSync(outRoot, { recursive: true });
const browser = await chromium.launch({ headless: true });
const context = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
const page = await context.newPage();

await page.goto(`${baseUrl}/wp-login.php`, { waitUntil: 'domcontentloaded' });
await page.fill('#user_login', username);
await page.fill('#user_pass', password);
await Promise.all([
  page.waitForNavigation({ waitUntil: 'domcontentloaded' }),
  page.click('#wp-submit'),
]);
if (page.url().includes('wp-login.php')) throw new Error('WordPress visual QA login failed.');

const summary = [];
for (const [screenId, owner, route, reference] of selected) {
  const screenDir = path.join(outRoot, screenId);
  fs.mkdirSync(screenDir, { recursive: true });
  const lockedRef = path.join('assets/design/plugin-redesign/reference', reference);
  if (!fs.existsSync(lockedRef)) throw new Error(`${screenId}: locked reference missing: ${lockedRef}`);
  fs.copyFileSync(lockedRef, path.join(screenDir, `LOCKED_${reference}`));

  for (const width of widths) {
    await page.setViewportSize({ width, height: width <= 430 ? 900 : 1000 });
    const response = await page.goto(`${baseUrl}${route}`, { waitUntil: 'networkidle' });
    if (!response || response.status() >= 400) throw new Error(`${screenId}: HTTP ${response?.status()} at ${route}`);
    if (page.url().includes('wp-login.php')) throw new Error(`${screenId}: authentication was lost.`);

    const runtime = await page.evaluate(() => ({
      title: document.title,
      direction: getComputedStyle(document.documentElement).direction || getComputedStyle(document.body).direction,
      scrollWidth: document.documentElement.scrollWidth,
      clientWidth: document.documentElement.clientWidth,
      hasWpDie: Boolean(document.querySelector('.wp-die-message')),
      text: document.body.innerText.slice(0, 500),
    }));
    if (runtime.hasWpDie) throw new Error(`${screenId}: WordPress wp_die rendered instead of the admin screen.`);
    if (locale === 'ar' && runtime.direction !== 'rtl') throw new Error(`${screenId}: expected Arabic RTL runtime, got ${runtime.direction}.`);
    if (locale !== 'ar' && runtime.direction !== 'ltr') throw new Error(`${screenId}: expected English LTR runtime, got ${runtime.direction}.`);
    if (runtime.scrollWidth > runtime.clientWidth + 4) {
      throw new Error(`${screenId}: document horizontal overflow ${runtime.scrollWidth}px > ${runtime.clientWidth}px at width ${width}.`);
    }

    const fileName = `runtime-${locale}-${width}.png`;
    await page.screenshot({ path: path.join(screenDir, fileName), fullPage: true });
    summary.push({ screenId, owner, reference, route, locale, width, direction: runtime.direction, sourceSha, screenshot: fileName });
  }

  const rows = summary.filter(row => row.screenId === screenId);
  const qa = [
    `# ${screenId} Visual QA Evidence`,
    '',
    `- Owner: ${owner}`,
    `- Locked reference: ${reference}`,
    `- Runtime route: \`${route}\``,
    `- Source SHA: \`${sourceSha}\``,
    `- Locale: ${locale}`,
    `- Direction: ${rows[0]?.direction ?? 'unknown'}`,
    `- Viewports: ${rows.map(row => row.width).join(', ')}`,
    '- Capture source: disposable real WordPress + MySQL + authenticated wp-admin + Chromium/Playwright.',
    '- Acceptance note: artifact capture is evidence, not automatic visual approval. Lead side-by-side review and functional disposition remain required.',
    '',
  ].join('\n');
  fs.writeFileSync(path.join(screenDir, `VISUAL_QA-${locale}.md`), qa);
}

fs.writeFileSync(path.join(outRoot, `manifest-${scope}-${locale}.json`), JSON.stringify(summary, null, 2));
await browser.close();
console.log(`Captured ${summary.length} real WordPress screenshots for ${selected.length} screens (${scope}, ${locale}).`);
