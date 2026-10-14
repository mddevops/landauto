/// <reference types="node" />
import { defineConfig, devices } from '@playwright/test';
import { memberStorageState } from './tests/browser/support/users';
import { viewports } from './tests/browser/support/viewports';

const port = 8200;
const baseURL = `http://127.0.0.1:${port}`;
export default defineConfig({
    testDir: './tests/browser',
    testMatch: '**/*.spec.ts',
    outputDir: './test-results',
    fullyParallel: false,
    // The E2E server is a single-threaded PHP server over one SQLite file.
    workers: 1,
    forbidOnly: !!process.env.CI,
    retries: 0,
    timeout: 30_000,
    expect: {
        timeout: 5_000,
    },
    reporter: [
        ['list'],
        ['html', { open: 'never', outputFolder: 'playwright-report' }],
    ],
    use: {
        ...devices['Desktop Chrome'],
        baseURL,
        headless: true,
        locale: 'ru-RU',
        timezoneId: 'Europe/Moscow',
        colorScheme: 'light',
        actionTimeout: 10_000,
        navigationTimeout: 15_000,
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
        video: 'off',
        contextOptions: {
            reducedMotion: 'reduce',
        },
        // Custom-domain specs use *.e2e.test hostnames; Chromium sends them to the local
        // E2E server instead of real DNS, bypassing any system proxy.
        launchOptions: {
            args: [
                '--host-resolver-rules=MAP *.e2e.test 127.0.0.1',
                '--proxy-server=direct://',
            ],
        },
    },
    projects: [
        {
            name: 'setup',
            testMatch: '**/*.setup.ts',
            use: { viewport: viewports.desktop },
        },
        {
            // Full browser suite.
            name: 'desktop',
            dependencies: ['setup'],
            use: {
                viewport: viewports.desktop,
                storageState: memberStorageState,
            },
        },
        {
            // Responsive smoke: only tests tagged @responsive.
            name: 'tablet',
            dependencies: ['setup'],
            grep: /@responsive/,
            use: {
                viewport: viewports.tablet,
                deviceScaleFactor: 1,
                isMobile: true,
                hasTouch: true,
                storageState: memberStorageState,
            },
        },
        {
            name: 'mobile',
            dependencies: ['setup'],
            grep: /@responsive/,
            use: {
                viewport: viewports.mobile,
                deviceScaleFactor: 1,
                isMobile: true,
                hasTouch: true,
                storageState: memberStorageState,
            },
        },
    ],
    // `npm run test:e2e` owns the isolated PHP process and always stops that exact PID.
    // Playwright must not wrap or tear down the server process on Windows.
    webServer: undefined,
});
