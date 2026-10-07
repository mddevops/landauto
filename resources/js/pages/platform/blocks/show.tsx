import { Head } from '@inertiajs/react';
import { BlockStudio } from '@/components/block-studio/block-studio';
import type { BlockStudioPageProps } from '@/components/block-studio/block-studio';
import {
    draft as draftRoute,
    index,
    publish,
    update,
} from '@/routes/platform/blocks';

export default function PlatformBlockStudio(props: BlockStudioPageProps) {
    const id = props.block.public_id;

    return (
        <>
            <Head title={`${props.block.name} — Студия блоков`} />
            <BlockStudio
                {...props}
                metadataAction={update.form(id)}
                draftUrl={draftRoute.url(id)}
                publishUrl={publish.url(id)}
            />
        </>
    );
}

PlatformBlockStudio.layout = {
    breadcrumbs: [{ title: 'Блоки Landflow', href: index() }],
};
