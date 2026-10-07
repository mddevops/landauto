import { Head } from '@inertiajs/react';
import { BlockList } from '@/components/block-authoring/block-list';
import { create, index, show } from '@/routes/platform/blocks';
import type { AuthoringBlock } from '@/types/blocks';

export default function PlatformBlocks({
    blocks,
}: {
    blocks: AuthoringBlock[];
}) {
    return (
        <>
            <Head title="Блоки Landflow" />
            <BlockList
                title="Блоки Landflow"
                description="Официальные блоки платформы. Клиентам в дизайнере доступны только блоки с опубликованной версией."
                blocks={blocks}
                createHref={create()}
                createLabel="Создать официальный блок"
                emptyText="Официальных блоков пока нет."
                emptyCta="Создать официальный блок"
                showHref={(publicId) => show(publicId)}
            />
        </>
    );
}

PlatformBlocks.layout = {
    breadcrumbs: [{ title: 'Блоки Landflow', href: index() }],
};
