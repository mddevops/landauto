import type { ComponentType } from 'react';
import {
    BenefitsBlock,
    ContactsBlock,
    CtaBlock,
    FooterBlock,
    HeaderBlock,
    HeroBlock,
} from '@/blocks/official-blocks';
import type { BlockRendererProps } from '@/blocks/state';

/** Renderers of official Blocks, keyed by Block Definition slug. */
const officialBlockRenderers: Record<
    string,
    ComponentType<BlockRendererProps>
> = {
    header: HeaderBlock,
    hero: HeroBlock,
    benefits: BenefitsBlock,
    cta: CtaBlock,
    contacts: ContactsBlock,
    footer: FooterBlock,
};

export function blockRenderer(
    slug: string,
): ComponentType<BlockRendererProps> | null {
    return Object.hasOwn(officialBlockRenderers, slug)
        ? officialBlockRenderers[slug]
        : null;
}
