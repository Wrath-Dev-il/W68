const { chromium } = require('playwright');

(async () => {
    const browser = await chromium.launch({ headless: true });
    const context = await browser.newContext();
    const page = await context.newPage();
    try {
        await page.goto('http://localhost/hatdog/public/login');
        console.log('Login Page HTML inputs:');
        const inputs = await page.evaluate(() => Array.from(document.querySelectorAll('input, button')).map(i => ({ id: i.id, name: i.name, type: i.type, text: i.innerText })));
        console.log(inputs);

        // Fill login
        await page.fill('#username', 'NARD');
        await page.fill('#password', '1234');
        await page.click('button[type="submit"]');
        await page.waitForTimeout(1500);
        console.log('After login URL:', page.url());

        // Now test navigating to sales-order?editNoteId=1
        await page.goto('http://localhost/hatdog/public/special/sales/sales-order?editNoteId=1');
        await page.waitForTimeout(2000);
        console.log('Sales Order page URL:', page.url());
        
        // Find all modals or buttons on Sales Order page
        const modals = await page.evaluate(() => Array.from(document.querySelectorAll('[id*="modal"]')).map(m => ({ id: m.id, class: m.className })));
        console.log('Modals on sales-order page:', modals);

        // Check loaded scripts
        const scripts = await page.evaluate(() => Array.from(document.querySelectorAll('script')).map(s => s.src));
        console.log('Scripts on sales-order page:', scripts.filter(Boolean));

        // Now test navigating to payments page
        await page.goto('http://localhost/hatdog/public/special/accounting/payments');
        await page.waitForTimeout(2000);
        console.log('Payments page URL:', page.url());
        const paymentsModals = await page.evaluate(() => Array.from(document.querySelectorAll('[id*="modal"]')).map(m => ({ id: m.id, class: m.className })));
        console.log('Modals on payments page:', paymentsModals);

    } catch (e) {
        console.error('Error:', e);
    } finally {
        await browser.close();
    }
})();
