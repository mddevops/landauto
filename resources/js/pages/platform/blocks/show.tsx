import { Head } from '@inertiajs/react';
import { BlockEditor } from '@/components/block-authoring/block-editor';
import { index, update } from '@/routes/platform/blocks';
import type { AuthoringBlockDetail } from '@/types/blocks';

export default function PlatformBlockEditor({
    block,
}: {
    block: AuthoringBlockDetail;
}) {
    return (
        <>
            <Head title="Редактор блока" />
            <BlockEditor block={block} action={update.form(block.public_id)} />
        </>
    );
}

PlatformBlockEditor.layout = {
    breadcrumbs: [{ title: 'Блоки Landflow', href: index() }],
};
