const { chromium } = require('playwright');

(async () => {
    const browser = await chromium.launch({ headless: true });
    const context = await browser.newContext();
    const page = await context.newPage();
    try {
        await page.goto('http://localhost/hatdog/public/login');
        await page.fill('input[name="User_ID"], input[name="username"], #User_ID, #username', 'NARD');
        await page.fill('input[name="Password"], input[name="password"], #Password, #password', '1234');
        await page.click('button[type="submit"]');
        await page.waitForTimeout(2000);
        console.log('After login URL:', page.url());

        await page.goto('http://localhost/hatdog/public/special/sales/sales-order');
        await page.waitForTimeout(2000);
        console.log('Sales Order page URL:', page.url());
        console.log('Title:', await page.title());

        const scripts = await page.evaluate(() => Array.from(document.querySelectorAll('script')).map(s => s.src));
        console.log('Loaded scripts:', scripts.filter(Boolean));
    } catch (e) {
        console.error(e);
    } finally {
        await browser.close();
    }
})();
