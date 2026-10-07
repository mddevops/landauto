import { Head } from '@inertiajs/react';
import { BlockCreateForm } from '@/components/block-authoring/block-create-form';
import { create, index, store } from '@/routes/platform/blocks';

export default function CreatePlatformBlock() {
    return (
        <>
            <Head title="Новый официальный блок" />
            <BlockCreateForm
                title="Новый официальный блок"
                description="Блок будет принадлежать платформе Landflow. Пока у него нет версии, клиенты его не видят."
                action={store.form()}
                cancelHref={index()}
                submitLabel="Создать официальный блок"
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
