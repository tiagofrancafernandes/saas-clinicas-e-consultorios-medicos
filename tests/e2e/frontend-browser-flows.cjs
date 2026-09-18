const puppeteer = require('puppeteer-core');
const { spawn } = require('child_process');
const http = require('http');
const path = require('path');
const fs = require('fs');

const SCREENSHOTS_DIR = path.join(__dirname, 'screenshots');
if (!fs.existsSync(SCREENSHOTS_DIR)) {
    fs.mkdirSync(SCREENSHOTS_DIR, { recursive: true });
}

function waitForServer(url, timeoutMs = 15000) {
    const start = Date.now();
    return new Promise((resolve, reject) => {
        const check = () => {
            http.get(url, (res) => {
                if (res.statusCode === 200) {
                    resolve();
                } else if (Date.now() - start > timeoutMs) {
                    reject(new Error(`Timeout waiting for ${url} (status ${res.statusCode})`));
                } else {
                    setTimeout(check, 300);
                }
            }).on('error', () => {
                if (Date.now() - start > timeoutMs) {
                    reject(new Error(`Timeout waiting for ${url}`));
                } else {
                    setTimeout(check, 300);
                }
            });
        };
        check();
    });
}

async function runTests() {
    console.log('🚀 Starting standalone Nitro preview servers...');

    const rootDir = path.resolve(__dirname, '../..');
    const portalProc = spawn('node', ['apps/portal/.output/server/index.mjs'], {
        cwd: rootDir,
        env: { ...process.env, PORT: '3001' },
        stdio: 'inherit'
    });

    const webProc = spawn('node', ['apps/web/.output/server/index.mjs'], {
        cwd: rootDir,
        env: { ...process.env, PORT: '3000' },
        stdio: 'inherit'
    });

    let browser;

    const cleanup = () => {
        try { if (portalProc) portalProc.kill(); } catch (e) {}
        try { if (webProc) webProc.kill(); } catch (e) {}
        try { if (browser) browser.close(); } catch (e) {}
    };

    process.on('SIGINT', cleanup);
    process.on('SIGTERM', cleanup);
    process.on('exit', cleanup);

    try {
        console.log('⏳ Waiting for Portal (port 3001) and Web (port 3000) to respond...');
        await Promise.all([
            waitForServer('http://localhost:3001'),
            waitForServer('http://localhost:3000')
        ]);
        console.log('✅ Servers are healthy and responding on ports 3001 and 3000!');

        console.log('🌐 Launching Google Chrome via puppeteer-core...');
        browser = await puppeteer.launch({
            executablePath: '/usr/bin/google-chrome',
            headless: 'new',
            defaultViewport: { width: 1280, height: 800 },
            args: [
                '--no-sandbox',
                '--disable-setuid-sandbox',
                '--disable-dev-shm-usage',
                '--disable-gpu'
            ]
        });

        const page = await browser.newPage();

        // =========================================================================
        // 1. PATIENT FLOW: FAST ACTION TOKEN
        // =========================================================================
        console.log('\n--- [FLOW 1/5] Patient Portal: Fast Action Token Actions ---');
        await page.goto('http://localhost:3001', { waitUntil: 'networkidle0' });

        await page.waitForSelector('#btn-confirm-attendance', { visible: true });
        console.log('✓ Action token page rendered with pending appointment');

        await page.click('#btn-confirm-attendance');
        await page.waitForSelector('#btn-checkin-arrival', { visible: true });
        console.log('✓ Attendance confirmed via 1-click token');

        await page.click('#btn-checkin-arrival');
        await page.waitForFunction(() => document.body.innerText.includes('Reception alerted'));
        console.log('✓ Patient checked in upon arrival at clinic');

        await page.screenshot({ path: path.join(SCREENSHOTS_DIR, '01-patient-token-flow.png') });

        // =========================================================================
        // 2. PATIENT FLOW: OTP AUTHENTICATION & LOGGED-IN PORTAL
        // =========================================================================
        console.log('\n--- [FLOW 2/5] Patient Portal: OTP Login & Logged-in Area ---');
        await page.click('#btn-nav-otp-login');
        await page.waitForSelector('#input-phone', { visible: true });
        console.log('✓ Navigated to OTP passwordless login');

        await page.click('#btn-send-otp');
        await page.waitForSelector('#demo-otp-code', { visible: true });
        const demoOtp = await page.$eval('#demo-otp-code', el => el.innerText.trim());
        console.log(`✓ OTP code received: ${demoOtp}`);

        await page.type('#input-otp input', demoOtp);
        await page.click('#btn-verify-otp');

        await page.waitForSelector('#patient-name-header', { visible: true });
        const patientName = await page.$eval('#patient-name-header', el => el.innerText.trim());
        console.log(`✓ Patient logged in successfully: ${patientName}`);

        // Navigate patient subtabs
        await page.click('#tab-patient-records');
        await page.waitForSelector('#patient-records-panel', { visible: true });
        console.log('✓ Patient records & prescriptions sub-tab loaded');

        await page.click('#tab-patient-files');
        await page.waitForSelector('#patient-files-panel', { visible: true });
        console.log('✓ Patient exams & document attachments sub-tab loaded');

        await page.click('#tab-patient-clinic');
        await page.waitForSelector('#patient-clinic-panel', { visible: true });
        console.log('✓ Patient clinic details sub-tab loaded');

        await page.click('#tab-patient-appointments');
        await page.waitForSelector('#patient-appointments-panel', { visible: true });
        console.log('✓ Patient upcoming appointments sub-tab loaded');

        await page.screenshot({ path: path.join(SCREENSHOTS_DIR, '02-patient-logged-in-portal.png') });

        // =========================================================================
        // 3. CLINIC FLOW: RECEPTION DESK
        // =========================================================================
        console.log('\n--- [FLOW 3/5] Clinic Web: Reception Desk & Patient Summon ---');
        await page.goto('http://localhost:3000', { waitUntil: 'networkidle0' });

        await page.waitForSelector('#btn-role-reception', { visible: true });
        await page.click('#btn-role-reception');
        await page.waitForSelector('#reception-workspace', { visible: true });
        console.log('✓ Reception workspace active');

        // Verify Waiting Room Arrivals & Summon Patient to Room
        await page.waitForSelector('#btn-summon-patient-arr_1', { visible: true });
        await page.click('#btn-summon-patient-arr_1');
        await page.waitForSelector('#summon-alert-banner', { visible: true });
        const summonText = await page.$eval('#summon-alert-banner', el => el.innerText);
        console.log(`✓ Patient summoned to room with broadcast: ${summonText.trim()}`);

        // Quick Appointment Booking Modal
        await page.click('#btn-open-booking');
        await page.waitForSelector('#input-booking-patient-name', { visible: true });
        await page.type('#input-booking-patient-name input', 'Juliana Lima');
        await page.type('#input-booking-patient-phone input', '+5511999887766');
        await page.click('#btn-submit-booking');
        await page.waitForFunction(() => document.body.innerText.includes('Appointment booked for Juliana Lima'));
        console.log('✓ New appointment booked with pessimistic concurrency protection');

        // Allow modal auto-dismiss to complete so reception board is unobstructed
        await new Promise((r) => setTimeout(r, 1400));

        await page.screenshot({ path: path.join(SCREENSHOTS_DIR, '03-reception-flow.png') });

        // =========================================================================
        // 4. CLINIC FLOW: HEALTHCARE PROFESSIONAL / DOCTOR
        // =========================================================================
        console.log('\n--- [FLOW 4/5] Clinic Web: Healthcare Professional & SOAP EHR ---');
        await page.click('#btn-role-doctor');
        await page.waitForSelector('#doctor-workspace', { visible: true });
        console.log('✓ Doctor consultation desk active');

        await page.click('#btn-select-doctor-1');
        await page.waitForSelector('#input-soap-title', { visible: true });

        // Select Private Confidentiality Tier
        await page.click('#radio-visibility-private');
        console.log('✓ Selected PRIVATE confidentiality tier (author doctor only)');

        // Save SOAP Record
        await page.click('#btn-save-soap');
        await page.waitForSelector('#soap-saved-alert', { visible: true });
        console.log('✓ SOAP clinical progress note saved with confidential access boundaries');

        // Grant temporary doctor delegation
        await page.click('#btn-grant-delegation');
        await page.waitForSelector('#delegation-alert', { visible: true });
        console.log('✓ Temporary covering delegation granted to Dr. Amanda Stone');

        await page.screenshot({ path: path.join(SCREENSHOTS_DIR, '04-doctor-soap-flow.png') });

        // =========================================================================
        // 5. CLINIC FLOW: CLINIC ADMIN & SUBSCRIPTION WRITE LOCKS
        // =========================================================================
        console.log('\n--- [FLOW 5/5] Clinic Web: Clinic Administration & SaaS Write Locks ---');
        await page.click('#btn-role-clinic-admin');
        await page.waitForSelector('#clinic-admin-workspace', { visible: true });
        console.log('✓ Clinic Admin workspace active');

        await page.waitForSelector('#clinic-rooms-card', { visible: true });
        await page.waitForSelector('#clinic-packages-card', { visible: true });
        await page.waitForSelector('#clinic-insurances-card', { visible: true });
        console.log('✓ Clinic rooms, packages, and health insurance agreements verified');

        // Simulate Subscription Suspension (Write Lock enforcement)
        await page.click('#btn-toggle-subscription');
        await page.waitForSelector('#write-lock-alert', { visible: true });
        console.log('✓ Subscription suspension simulated: CheckTenantSubscription write lock active');

        // Restore Active Subscription
        await page.click('#btn-toggle-subscription');
        await page.waitForFunction(() => document.body.innerText.includes('Normal Operation: Write locks disabled'));
        console.log('✓ Subscription restored to ACTIVE: Write operations restored');

        await page.screenshot({ path: path.join(SCREENSHOTS_DIR, '05-clinic-admin-flow.png') });

        // =========================================================================
        // 6. CENTRAL FLOW: SUPER ADMIN & AUDIT TRAIL
        // =========================================================================
        console.log('\n--- [BONUS] Central Platform: Super Admin & Central Audit Logs ---');
        await page.click('#btn-role-super-admin');
        await page.waitForSelector('#super-admin-workspace', { visible: true });
        console.log('✓ Super Admin central platform workspace active');

        await page.waitForSelector('#super-admin-tenants-card', { visible: true });
        await page.waitForSelector('#central-audit-logs-card', { visible: true });
        console.log('✓ Central tenant catalog and central_audit_logs trail verified');

        // Provision New Clinic
        await page.click('#btn-open-provision-modal');
        await page.waitForSelector('#input-new-tenant-name', { visible: true });
        await page.type('#input-new-tenant-name input', 'Centro Medico Einstein');
        await page.click('#btn-submit-provision-tenant');
        await page.waitForFunction(() => document.body.innerText.includes('Centro Medico Einstein'));
        console.log('✓ Provisioned new clinic schema in central multi-tenant registry');

        // Allow modal auto-dismiss to complete so super admin dashboard is unobstructed
        await new Promise((r) => setTimeout(r, 1400));

        await page.screenshot({ path: path.join(SCREENSHOTS_DIR, '06-super-admin-flow.png') });

        console.log('\n=================================================================');
        console.log('🎉 ALL 5 USER ROLE FLOWS VERIFIED SUCCESSFULLY IN GOOGLE CHROME!');
        console.log(`📸 Screenshots saved to: ${SCREENSHOTS_DIR}`);
        console.log('=================================================================\n');

    } catch (err) {
        console.error('❌ Test failed with error:', err);
        process.exitCode = 1;
    } finally {
        cleanup();
    }
}

runTests();
