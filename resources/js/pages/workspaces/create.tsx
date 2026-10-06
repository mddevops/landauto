import { Form, Head, Link } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { dashboard } from '@/routes';
import { create, store } from '@/routes/workspaces';

export default function CreateWorkspace({
    nameMaxLength,
}: {
    nameMaxLength: number;
}) {
    return (
        <>
            <Head title="Новое пространство" />
            <main className="flex min-w-0 flex-1 flex-col gap-6 p-4 sm:p-6">
                <header className="min-w-0 space-y-1">
                    <h1 className="text-2xl font-semibold tracking-tight sm:text-3xl">
                        Новое пространство
                    </h1>
                    <p className="max-w-2xl text-sm text-muted-foreground">
                        Пространство объединяет сайты, интеграции и настройки
                        одной компании или проекта. Вы станете его владельцем, и
                        оно откроется сразу после создания.
                    </p>
                </header>

                <Form
                    {...store.form()}
                    disableWhileProcessing
                    className="flex max-w-md flex-col gap-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="name">
                                    Название пространства
                                </Label>
                                <Input
                                    id="name"
                                    name="name"
                                    type="text"
                                    required
                                    maxLength={nameMaxLength}
                                    autoComplete="off"
                                    placeholder="Например, «АвтоГрупп Ростов»"
                                    aria-invalid={Boolean(errors.name)}
                                    aria-describedby={
                                        errors.name ? 'name-error' : undefined
                                    }
                                />
                                <InputError
                                    id="name-error"
                                    message={errors.name}
                                />
                            </div>

                            <div className="flex flex-col-reverse gap-3 sm:flex-row sm:items-center">
                                <Button variant="outline" asChild>
                                    <Link href={dashboard()}>Отмена</Link>
                                </Button>
                                <Button type="submit" disabled={processing}>
                                    {processing && <Spinner />}
                                    Создать пространство
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </main>
        </>
    );
}

CreateWorkspace.layout = {
    breadcrumbs: [
        { title: 'Все сайты', href: dashboard() },
        { title: 'Новое пространство', href: create() },
    ],
};
