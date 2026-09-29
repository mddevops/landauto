/// <reference types="node" />
import type { Page, TestInfo } from '@playwright/test';
import path from 'node:path';

// Review screenshots: test-results/screenshots/<area>/<name>--<project>.png.
// test-results/ is cleared at the start of every run, so screenshots never come from a stale run.
// They are evidence for visual review, not pixel baselines.
// Use `fullPage: false` for open overlays (sheets, dialogs): fixed layers only cover the viewport.
export async function captureScreenshot(
    page: Page,
    testInfo: TestInfo,
    area: string,
    name: string,
    { fullPage = true }: { fullPage?: boolean } = {},
): Promise<void> {
    await page.evaluate(async () => {
        await document.fonts.ready;
    });

    const file = path.join(
        testInfo.project.outputDir,
        'screenshots',
        area,
        `${name}--${testInfo.project.name}.png`,
    );

    await page.screenshot({
        path: file,
        fullPage,
        animations: 'disabled',
    });
    await testInfo.attach(`${area}/${name}`, {
        path: file,
        contentType: 'image/png',
    });
}
