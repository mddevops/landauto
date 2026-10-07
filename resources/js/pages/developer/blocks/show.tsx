import { Head } from '@inertiajs/react';
import { BlockEditor } from '@/components/block-authoring/block-editor';
import { dashboard } from '@/routes/developer';
import { index, update } from '@/routes/developer/blocks';
import type { AuthoringBlockDetail } from '@/types/blocks';

export default function DeveloperBlockEditor({
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

DeveloperBlockEditor.layout = {
    breadcrumbs: [
        { title: 'Панель разработчика', href: dashboard() },
        { title: 'Мои блоки', href: index() },
    ],
};
