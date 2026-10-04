import { expect, test as base } from '@playwright/test';
import type { Page } from '@playwright/test';

type BrowserIssues = {
    consoleErrors: string[];
    failedRequests: string[];
    /**
     * Declares an intentionally provoked HTTP error (e.g. a rejected spam submission):
     * exactly `times` responses matching the status and URL fragment are expected, and
     * Chromium's URL-less "Failed to load resource" console message for that status is
     * tolerated.
     */
    expectFailedResponse: (
        status: number,
        urlPart: string,
        times?: number,
    ) => void;
};

// Every browser test fails on unexpected console errors, uncaught page errors
// and failed (4xx/5xx) HTTP responses in any page of the test context (including
// preview tabs). Do not filter these globally; a test that intentionally expects
// an error must declare it with `expectFailedResponse`.
export const test = base.extend<{ browserIssues: BrowserIssues }>({
    browserIssues: [
        async ({ page, context }, use) => {
            const expected: {
                status: number;
                urlPart: string;
                left: number;
            }[] = [];
            const issues: BrowserIssues = {
                consoleErrors: [],
                failedRequests: [],
                expectFailedResponse: (status, urlPart, times = 1) => {
                    expected.push({ status, urlPart, left: times });
                },
            };
            const watch = (target: Page) => {
                target.on('console', (message) => {
                    if (message.type() !== 'error') {
                        return;
                    }

                    const status = Number(
                        /status of (\d{3})/.exec(message.text())?.[1],
                    );

                    // Chromium's generic resource message has no URL; the response
                    // itself is matched (and counted) by URL below.
                    if (
                        message.text().startsWith('Failed to load resource') &&
                        expected.some((entry) => entry.status === status)
                    ) {
                        return;
                    }

                    issues.consoleErrors.push(message.text());
                });

                target.on('pageerror', (error) => {
                    issues.consoleErrors.push(error.message);
                });

                target.on('response', (response) => {
                    if (response.status() < 400) {
                        return;
                    }

                    const match = expected.find(
                        (entry) =>
                            entry.left > 0 &&
                            entry.status === response.status() &&
                            response.url().includes(entry.urlPart),
                    );

                    if (match) {
                        match.left--;

                        return;
                    }

                    issues.failedRequests.push(
                        `${response.status()} ${response.request().method()} ${response.url()}`,
                    );
                });

                target.on('requestfailed', (request) => {
                    const failure =
                        request.failure()?.errorText ?? 'unknown error';

                    // Chromium aborts an older document request when a newer navigation
                    // supersedes it; that expected cancellation is not a network failure.
                    if (
                        request.isNavigationRequest() &&
                        failure === 'net::ERR_ABORTED'
                    ) {
                        return;
                    }

                    issues.failedRequests.push(
                        `${failure} ${request.method()} ${request.url()}`,
                    );
                });
            };

            watch(page);
            context.on('page', watch);

            await use(issues);

            expect(
                issues.consoleErrors,
                'Unexpected browser console errors',
            ).toEqual([]);
            expect(
                issues.failedRequests,
                'Unexpected failed HTTP requests',
            ).toEqual([]);
            expect(
                expected.filter((entry) => entry.left > 0),
                'Declared failed responses that never happened',
            ).toEqual([]);
        },
        { auto: true },
    ],
});

export { expect };
