import type { ComponentType } from 'react';
import { ChatSelectionBlock } from '@/blocks/chat-selection-block';
import {
    BenefitsBlock,
    ContactsBlock,
    CtaBlock,
    FooterBlock,
    HeaderBlock,
    HeroBlock,
} from '@/blocks/official-blocks';
import { QuizBlock } from '@/blocks/quiz-block';
import type { BlockRendererProps } from '@/blocks/state';
import { VehicleCardBlock, VehicleGridBlock } from '@/blocks/vehicle-blocks';
import {
    VehicleCharacteristicsBlock,
    VehicleEquipmentBlock,
    VehicleGalleryBlock,
    VehicleOffersBlock,
} from '@/blocks/vehicle-detail-blocks';

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
    'vehicle-gallery': VehicleGalleryBlock,
    'vehicle-offers': VehicleOffersBlock,
    'vehicle-characteristics': VehicleCharacteristicsBlock,
    'vehicle-equipment': VehicleEquipmentBlock,
    quiz: QuizBlock,
    'chat-selection': ChatSelectionBlock,
};

export function blockRenderer(
    slug: string,
): ComponentType<BlockRendererProps> | null {
    return Object.hasOwn(officialBlockRenderers, slug)
        ? officialBlockRenderers[slug]
        : null;
}
