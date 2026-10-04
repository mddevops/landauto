import type { ReactNode } from 'react';
import { ButtonStyleContext, designStyle } from '@/blocks/design';
import type { DesignTokens } from '@/blocks/design';

export function SiteTheme({
    tokens,
    children,
}: {
    tokens: DesignTokens;
    children: ReactNode;
}) {
    return (
        <ButtonStyleContext value={tokens.button_style}>
            <div style={designStyle(tokens)}>{children}</div>
        </ButtonStyleContext>
    );
}
