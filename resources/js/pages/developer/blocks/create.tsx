import { Head } from '@inertiajs/react';
import { BlockCreateForm } from '@/components/block-authoring/block-create-form';
import type { Choice } from '@/components/platform/form-fields';
import { dashboard } from '@/routes/developer';
import { create, index, store } from '@/routes/developer/blocks';

export default function CreateDeveloperBlock({
    categories,
}: {
    categories: Choice[];
}) {
    return (
        <>
            <Head title="Новый блок" />
            <BlockCreateForm
                title="Новый блок"
                description="Блок будет принадлежать вашему профилю разработчика. Код и схему вы напишете в студии после создания."
                action={store.form()}
                cancelHref={index()}
                submitLabel="Создать блок"
                categories={categories}
            />
        </>
    );
}

CreateDeveloperBlock.layout = {
    breadcrumbs: [
        { title: 'Студия', href: dashboard() },
        { title: 'Блоки', href: index() },
        { title: 'Новый блок', href: create() },
    ],
};
