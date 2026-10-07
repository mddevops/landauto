import { Head } from '@inertiajs/react';
import { BlockStudio } from '@/components/block-studio/block-studio';
import type { BlockStudioPageProps } from '@/components/block-studio/block-studio';
import { dashboard } from '@/routes/developer';
import {
    access,
    draft as draftRoute,
    index,
    publish,
    update,
} from '@/routes/developer/blocks';

export default function DeveloperBlockStudio(props: BlockStudioPageProps) {
    const id = props.block.public_id;

    return (
        <>
            <Head title={`${props.block.name} — Студия блоков`} />
            <BlockStudio
                {...props}
                metadataAction={update.form(id)}
                draftUrl={draftRoute.url(id)}
                publishUrl={publish.url(id)}
                accessUrl={access.url(id)}
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
