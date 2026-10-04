import { test, expect } from '@playwright/test';

async function register(context, displayName) {
    const page = await context.newPage();
    await page.goto('/auth');
    await page.getByLabel('نام نمایشی').fill(displayName);
    await page.waitForTimeout(1100);
    await page.getByRole('button', { name: 'ورود به تالار گفت‌وگو' }).click();
    await expect(page).toHaveURL('/');
    await expect(page.locator('#realtime-status')).toHaveText('متصل', { timeout: 15_000 });
    return page;
}

test('two temporary users exchange a message without executing HTML', async ({ browser }) => {
    const firstContext = await browser.newContext();
    const secondContext = await browser.newContext();

    const first = await register(firstContext, 'کاربر اول');
    const second = await register(secondContext, 'کاربر دوم');

    await expect(first.locator('#online-users-list')).toContainText('کاربر دوم');
    await expect(second.locator('#online-users-list')).toContainText('کاربر اول');

    const payload = '<img src=x onerror="window.__LarvelCXss=1"> سلام';
    await first.locator('#input-message').fill(payload);
    await first.getByRole('button', { name: 'ارسال پیام' }).click();

    await expect(first.locator('#chat-list')).toContainText(payload);
    await expect(second.locator('#chat-list')).toContainText(payload);
    await expect(second.locator('#chat-list img[src="x"]')).toHaveCount(0);
    await expect.poll(() => second.evaluate(() => window.__LarvelCXss ?? 0)).toBe(0);

    await firstContext.close();
    await secondContext.close();
});
