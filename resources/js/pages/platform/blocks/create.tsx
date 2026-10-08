import { Head } from '@inertiajs/react';
import { BlockCreateForm } from '@/components/block-authoring/block-create-form';
import type { Choice } from '@/components/platform/form-fields';
import { create, index, store } from '@/routes/platform/blocks';

export default function CreatePlatformBlock({
    categories,
}: {
    categories: Choice[];
}) {
    return (
        <>
            <Head title="Новый официальный блок" />
            <BlockCreateForm
                title="Новый официальный блок"
                description="Блок будет принадлежать платформе Landflow. Пока у него нет версии, клиенты его не видят."
                action={store.form()}
                cancelHref={index()}
                submitLabel="Создать официальный блок"
                categories={categories}
            />
        </>
    );
}

CreatePlatformBlock.layout = {
    breadcrumbs: [
        { title: 'Блоки Landflow', href: index() },
        { title: 'Новый официальный блок', href: create() },
    ],
};
