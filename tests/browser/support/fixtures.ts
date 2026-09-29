import { expect, test as base } from '@playwright/test';

type BrowserIssues = {
    consoleErrors: string[];
    failedRequests: string[];
};

// Every browser test fails on unexpected console errors, uncaught page errors
// and failed (4xx/5xx) HTTP responses. Do not filter these globally; a test that
// intentionally expects an error must handle it explicitly.
export const test = base.extend<{ browserIssues: BrowserIssues }>({
    browserIssues: [
        async ({ page }, use) => {
            const issues: BrowserIssues = {
                consoleErrors: [],
                failedRequests: [],
            };

            page.on('console', (message) => {
                if (message.type() === 'error') {
                    issues.consoleErrors.push(message.text());
                }
            });

            page.on('pageerror', (error) => {
                issues.consoleErrors.push(error.message);
            });

            page.on('response', (response) => {
                if (response.status() >= 400) {
                    issues.failedRequests.push(
                        `${response.status()} ${response.request().method()} ${response.url()}`,
                    );
                }
            });

            await use(issues);

            expect(
                issues.consoleErrors,
                'Unexpected browser console errors',
            ).toEqual([]);
            expect(
                issues.failedRequests,
                'Unexpected failed HTTP requests',
            ).toEqual([]);
        },
        { auto: true },
    ],
});

export { expect };
