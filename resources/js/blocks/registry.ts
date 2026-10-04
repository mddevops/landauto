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
import { VehicleCardBlock, VehicleGridBlock } from '@/blocks/vehicle-blocks';

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
    'vehicle-card': VehicleCardBlock,
    'vehicle-grid': VehicleGridBlock,
};

export function blockRenderer(
    slug: string,
): ComponentType<BlockRendererProps> | null {
    return Object.hasOwn(officialBlockRenderers, slug)
        ? officialBlockRenderers[slug]
        : null;
}
