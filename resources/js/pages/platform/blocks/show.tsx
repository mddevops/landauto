import { Head } from '@inertiajs/react';
import { BlockStudio } from '@/components/block-studio/block-studio';
import type { BlockStudioPageProps } from '@/components/block-studio/block-studio';
import {
    access,
    draft as draftRoute,
    index,
    publish,
    update,
} from '@/routes/platform/blocks';
import { show as showGroup } from '@/routes/platform/blocks/groups';
import versions from '@/routes/platform/blocks/versions';

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
                accessUrl={access.url(id)}
                copyLegacyVersionUrl={(version) =>
                    versions.draft.url({ block: id, version })
                }
            />
        </>
    );
}

PlatformBlockStudio.layout = (props: BlockStudioPageProps) => ({
    breadcrumbs: [
        { title: 'Блоки Landflow', href: index() },
        ...(props.block.group
            ? [
                  {
                      title: props.block.group_label,
                      href: showGroup(props.block.group),
                  },
              ]
            : []),
    ],
});
