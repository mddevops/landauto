// Baseline viewports from .cursor/rules/80-browser-qa.mdc.
export const viewports = {
    desktop: { width: 1440, height: 1000 },
    tablet: { width: 1024, height: 1366 },
    mobile: { width: 390, height: 844 },
} as const;

export type ViewportName = keyof typeof viewports;
