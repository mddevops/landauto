import { Form, Head } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import { Field, TextField } from '@/components/platform/form-fields';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import {
    index,
    reactivate,
    store,
    suspend,
} from '@/routes/platform/developers';

type Developer = {
    public_id: string;
    display_name: string;
    slug: string;
    email: string;
    status: 'active' | 'suspended';
    status_label: string;
    created_at: string | null;
};

type DevelopersProps = {
    developers: Developer[];
};

const textareaClass =
    'min-h-24 w-full min-w-0 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-invalid:border-destructive dark:bg-input/30';

function CreateDeveloperDialog() {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button>
                    <Plus aria-hidden="true" />
                    Добавить разработчика
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Новый профиль разработчика</DialogTitle>
                    <DialogDescription>
                        Профиль выдаётся существующему пользователю с
                        подтверждённым email. Он не даёт доступа к пространствам
                        и платформенным ролям.
                    </DialogDescription>
                </DialogHeader>
                <Form
                    {...store.form()}
                    options={{ preserveScroll: true }}
                    disableWhileProcessing
                    resetOnSuccess
                    onSuccess={() => setOpen(false)}
                    className="grid gap-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <TextField
                                id="developer-email"
                                name="email"
                                type="email"
                                label="Email пользователя"
                                required
                                maxLength={255}
                                autoComplete="off"
                                error={errors.email}
                            />
                            <TextField
                                id="developer-display_name"
                                name="display_name"
                                label="Название разработчика"
                                required
                                maxLength={100}
                                autoComplete="off"
                                error={errors.display_name}
                            />
                            <TextField
                                id="developer-slug"
                                name="slug"
                                label="Slug"
                                required
                                minLength={3}
                                maxLength={60}
                                autoComplete="off"
                                hint="Строчные латинские буквы, цифры и дефисы, 3–60 символов. Будет адресом страницы автора."
                                error={errors.slug}
                            />
                            <Field
                                id="developer-bio"
                                label="Описание (необязательно)"
                                error={errors.bio}
                            >
                                <textarea
                                    id="developer-bio"
                                    name="bio"
                                    maxLength={1000}
                                    className={textareaClass}
                                    aria-invalid={Boolean(errors.bio)}
                                    aria-describedby={
                                        errors.bio
                                            ? 'developer-bio-error'
                                            : undefined
                                    }
                                />
                            </Field>
                            <DialogFooter>
                                <DialogClose asChild>
                                    <Button type="button" variant="outline">
                                        Отмена
                                    </Button>
                                </DialogClose>
                                <Button type="submit" disabled={processing}>
                                    {processing && <Spinner />}
                                    Создать профиль разработчика
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function StatusAction({ developer }: { developer: Developer }) {
    const isActive = developer.status === 'active';
    const action = isActive
        ? suspend.form(developer.public_id)
        : reactivate.form(developer.public_id);

    return (
        <Form {...action} options={{ preserveScroll: true }}>
            {({ processing }) => (
                <Button
                    type="submit"
                    size="sm"
                    variant={isActive ? 'outline' : 'default'}
                    disabled={processing}
                    aria-label={`${isActive ? 'Приостановить' : 'Восстановить'} ${developer.display_name}`}
                >
                    {processing && <Spinner />}
                    {isActive ? 'Приостановить' : 'Восстановить'}
                </Button>
            )}
        </Form>
    );
}

export default function PlatformDevelopers({ developers }: DevelopersProps) {
    return (
        <>
            <Head title="Разработчики" />
            <main className="flex min-w-0 flex-1 flex-col gap-6 p-4 sm:p-6">
                <header className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div className="space-y-1">
                        <h1 className="text-2xl font-semibold tracking-tight sm:text-3xl">
                            Разработчики
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Профили разработчиков выдаёт только
                            суперадминистратор. Приостановка закрывает панель
                            разработчика, но не затрагивает пространства
                            пользователя.
                        </p>
                    </div>
                    <CreateDeveloperDialog />
                </header>

                {developers.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        Профилей разработчиков пока нет.
                    </p>
                ) : (
                    <ul
                        aria-label="Профили разработчиков"
                        className="divide-y rounded-xl border bg-card shadow-sm"
                    >
                        {developers.map((developer) => (
                            <li
                                key={developer.public_id}
                                data-testid="developer-row"
                                className="flex flex-col gap-3 px-4 py-3 sm:flex-row sm:items-center"
                            >
                                <div className="min-w-0 flex-1 space-y-0.5">
                                    <p className="font-medium break-words">
                                        {developer.display_name}
                                    </p>
                                    <p className="text-sm break-all text-muted-foreground">
                                        {developer.email}
                                    </p>
                                    <p className="text-xs break-all text-muted-foreground">
                                        <code>{developer.slug}</code>
                                    </p>
                                </div>
                                <div className="flex items-center gap-3">
                                    <Badge
                                        variant={
                                            developer.status === 'active'
                                                ? 'default'
                                                : 'secondary'
                                        }
                                    >
                                        {developer.status_label}
                                    </Badge>
                                    <StatusAction developer={developer} />
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </main>
        </>
    );
}

PlatformDevelopers.layout = {
    breadcrumbs: [{ title: 'Разработчики', href: index() }],
};
