import { Head } from '@inertiajs/react';
import { BlockCreateForm } from '@/components/block-authoring/block-create-form';
import { dashboard } from '@/routes/developer';
import { create, index, store } from '@/routes/developer/blocks';

export default function CreateDeveloperBlock() {
    return (
        <>
            <Head title="Новый блок" />
            <BlockCreateForm
                title="Новый блок"
                description="Блок будет принадлежать вашему профилю разработчика. Схему и версии можно будет добавить на следующих этапах."
                action={store.form()}
                cancelHref={index()}
                submitLabel="Создать блок"
            />
        </>
    );
}

CreateDeveloperBlock.layout = {
    breadcrumbs: [
        { title: 'Панель разработчика', href: dashboard() },
        { title: 'Мои блоки', href: index() },
        { title: 'Новый блок', href: create() },
    ],
};
