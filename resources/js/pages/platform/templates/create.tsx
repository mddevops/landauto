import { Head } from '@inertiajs/react';
import type { Choice } from '@/components/platform/form-fields';
import { TemplateCreateForm } from '@/components/template-authoring/template-create-form';
import { create, index, store } from '@/routes/platform/templates';

export default function CreatePlatformTemplate({
    siteTypes,
}: {
    siteTypes: Choice[];
}) {
    return (
        <>
            <Head title="Новый шаблон" />
            <TemplateCreateForm
                title="Новый официальный шаблон"
                description="Шаблон будет принадлежать платформе Landflow. Страницы и блоки вы соберёте в дизайнере после создания."
                action={store.form()}
                cancelHref={index()}
                siteTypes={siteTypes}
            />
        </>
    );
}

CreatePlatformTemplate.layout = {
    breadcrumbs: [
        { title: 'Шаблоны Landflow', href: index() },
        { title: 'Новый шаблон', href: create() },
    ],
};
