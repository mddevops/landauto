import { createContext, use } from 'react';
import type { CSSProperties } from 'react';

export type DesignTokens = {
    primary_color: string;
    secondary_color: string;
    font_family: 'sans' | 'serif';
    radius: 'none' | 'small' | 'medium' | 'large';
    container: 'narrow' | 'default' | 'wide';
    button_style: 'solid' | 'outline';
};

export const designChoices = {
    font_family: { sans: 'Без засечек', serif: 'С засечками' },
    radius: {
        none: 'Без скругления',
        small: 'Малое',
        medium: 'Среднее',
        large: 'Большое',
    },
    container: { narrow: 'Узкая', default: 'Обычная', wide: 'Широкая' },
    button_style: { solid: 'Заливка', outline: 'Контур' },
} as const;

const radii = {
    none: '0px',
    small: '0.25rem',
    medium: '0.5rem',
    large: '1rem',
};
const containers = { narrow: '56rem', default: '72rem', wide: '90rem' };
const fonts = {
    sans: 'ui-sans-serif, system-ui, sans-serif',
    serif: 'ui-serif, Georgia, Cambria, "Times New Roman", serif',
};

/** Readable text colour on top of a #rrggbb background (WCAG relative luminance). */
export function contrastText(hex: string): string {
    const channel = (offset: number) => {
        const value = parseInt(hex.slice(offset, offset + 2), 16) / 255;

        return value <= 0.03928
            ? value / 12.92
            : ((value + 0.055) / 1.055) ** 2.4;
    };
    const luminance =
        0.2126 * channel(1) + 0.7152 * channel(3) + 0.0722 * channel(5);

    return luminance > 0.179 ? '#171717' : '#ffffff';
}

export function designStyle(tokens: DesignTokens): CSSProperties {
    return {
        '--lf-primary': tokens.primary_color,
        '--lf-on-primary': contrastText(tokens.primary_color),
        '--lf-secondary': tokens.secondary_color,
        '--lf-radius': radii[tokens.radius],
        '--lf-container': containers[tokens.container],
        fontFamily: fonts[tokens.font_family],
    } as CSSProperties;
}

export const ButtonStyleContext =
    createContext<DesignTokens['button_style']>('solid');

export function useButtonStyle(): DesignTokens['button_style'] {
    return use(ButtonStyleContext);
}
