import { Head } from '@inertiajs/react';
import { BlockList } from '@/components/block-authoring/block-list';
import { dashboard } from '@/routes/developer';
import { create, index, show } from '@/routes/developer/blocks';
import type { AuthoringBlock } from '@/types/blocks';

export default function DeveloperBlocks({
    blocks,
}: {
    blocks: AuthoringBlock[];
}) {
    return (
        <>
            <Head title="Блоки — Студия" />
            <BlockList
                title="Блоки"
                description="Блоки вашего профиля разработчика. Они пока не доступны клиентам и не используются на сайтах."
                blocks={blocks}
                createHref={create()}
                createLabel="Создать блок"
                emptyText="У вас пока нет блоков."
                emptyCta="Создать первый блок"
                showHref={(publicId) => show(publicId)}
            />
        </>
    );
}

DeveloperBlocks.layout = {
    breadcrumbs: [
        { title: 'Студия', href: dashboard() },
        { title: 'Блоки', href: index() },
    ],
};
