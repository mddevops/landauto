import { Head } from '@inertiajs/react';
import type { Choice } from '@/components/platform/form-fields';
import { TemplateCreateForm } from '@/components/template-authoring/template-create-form';
import { dashboard } from '@/routes/developer';
import { create, index, store } from '@/routes/developer/templates';

export default function CreateDeveloperTemplate({
    siteTypes,
}: {
    siteTypes: Choice[];
}) {
    return (
        <>
            <Head title="Новый шаблон" />
            <TemplateCreateForm
                title="Новый шаблон"
                description="Шаблон будет принадлежать вашему профилю разработчика. Страницы и блоки вы соберёте в дизайнере после создания."
                action={store.form()}
                cancelHref={index()}
                siteTypes={siteTypes}
            />
        </>
    );
}

CreateDeveloperTemplate.layout = {
    breadcrumbs: [
        { title: 'Студия', href: dashboard() },
        { title: 'Шаблоны', href: index() },
        { title: 'Новый шаблон', href: create() },
    ],
};
