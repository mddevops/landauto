import { Head } from '@inertiajs/react';
import { BlockStudio } from '@/components/block-studio/block-studio';
import type { Choice } from '@/components/platform/form-fields';
import { dashboard } from '@/routes/developer';
import { draft as draftRoute, index, update } from '@/routes/developer/blocks';
import type { AuthoringBlockDetail, BlockDraft } from '@/types/blocks';

export default function DeveloperBlockStudio({
    block,
    draft,
    categories,
    sourceMaxBytes,
}: {
    block: AuthoringBlockDetail;
    draft: BlockDraft;
    categories: Choice[];
    sourceMaxBytes: number;
}) {
    return (
        <>
            <Head title={`${block.name} — Студия блоков`} />
            <BlockStudio
                block={block}
                draft={draft}
                categories={categories}
                sourceMaxBytes={sourceMaxBytes}
                metadataAction={update.form(block.public_id)}
                draftUrl={draftRoute.url(block.public_id)}
            />
        </>
    );
}

DeveloperBlockStudio.layout = {
    breadcrumbs: [
        { title: 'Студия', href: dashboard() },
        { title: 'Блоки', href: index() },
    ],
};
