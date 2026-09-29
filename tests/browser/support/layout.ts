import { expect, type Page } from '@playwright/test';

// The document itself must not scroll horizontally; intended inner scroll areas are allowed.
export async function expectNoHorizontalOverflow(page: Page): Promise<void> {
    const { scrollWidth, clientWidth } = await page.evaluate(() => ({
        scrollWidth: document.documentElement.scrollWidth,
        clientWidth: document.documentElement.clientWidth,
    }));

    expect(scrollWidth, 'Document has horizontal overflow').toBeLessThanOrEqual(
        clientWidth,
    );
}

export function isMobileViewport(page: Page): boolean {
    // Matches MOBILE_BREAKPOINT in resources/js/hooks/use-mobile.tsx.
    return (page.viewportSize()?.width ?? 0) < 768;
}
