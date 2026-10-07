import { Form, Link, useForm } from '@inertiajs/react';
import { FileText, House, Pencil, Plus, Search, Trash2 } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import type {
    DesignerPage,
    DesignerPageRoutes,
} from '@/components/designer/types';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import type { RouteDefinition, RouteFormDefinition } from '@/wayfinder';

type PagesPanelProps = {
    routes: DesignerPageRoutes;
    pages: DesignerPage[];
    currentPageId: string;
    canEdit: boolean;
    canAddPages: boolean;
    canEditSeo: boolean;
    canEditSeoIndexing: boolean;
};

type DialogState =
    | { mode: 'create' }
    | { mode: 'edit'; page: DesignerPage }
    | { mode: 'seo'; page: DesignerPage }
    | { mode: 'delete'; page: DesignerPage }
    | null;

export function PagesPanel({
    routes,
    pages,
    currentPageId,
    canEdit,
    canAddPages,
    canEditSeo,
    canEditSeoIndexing,
}: PagesPanelProps) {
    const [dialog, setDialog] = useState<DialogState>(null);
    const close = () => setDialog(null);

    return (
        <div className="flex flex-col gap-3">
            <ul className="flex flex-col gap-1">
                {pages.map((page) => (
                    <li
                        key={page.public_id}
                        className="flex items-center gap-1"
                    >
                        <Link
                            href={routes.href(page.public_id)}
                            aria-current={
                                page.public_id === currentPageId
                                    ? 'page'
                                    : undefined
                            }
                            className={cn(
                                'flex min-w-0 flex-1 items-center gap-2 rounded-md px-2 py-1.5 text-sm hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                                page.public_id === currentPageId &&
                                    'bg-muted font-medium',
                            )}
                        >
                            {page.is_home ? (
                                <House
                                    className="size-4 shrink-0"
                                    aria-hidden="true"
                                />
                            ) : (
                                <FileText
                                    className="size-4 shrink-0"
                                    aria-hidden="true"
                                />
                            )}
                            <span className="truncate">{page.title}</span>
                        </Link>
                        {canEditSeo && routes.seo && (
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                className="size-8"
                                aria-label={`SEO страницы «${page.title}»`}
                                onClick={() => setDialog({ mode: 'seo', page })}
                            >
                                <Search aria-hidden="true" />
                            </Button>
                        )}
                        {canEdit && (
                            <>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    className="size-8"
                                    aria-label={`Изменить страницу «${page.title}»`}
                                    onClick={() =>
                                        setDialog({ mode: 'edit', page })
                                    }
                                >
                                    <Pencil aria-hidden="true" />
                                </Button>
                                {canAddPages && !page.is_home && (
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        className="size-8"
                                        aria-label={`Удалить страницу «${page.title}»`}
                                        onClick={() =>
                                            setDialog({ mode: 'delete', page })
                                        }
                                    >
                                        <Trash2 aria-hidden="true" />
                                    </Button>
                                )}
                            </>
                        )}
                    </li>
                ))}
            </ul>

            {canAddPages && (
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={() => setDialog({ mode: 'create' })}
                >
                    <Plus aria-hidden="true" />
                    Добавить страницу
                </Button>
            )}

            <Dialog
                open={dialog !== null}
                onOpenChange={(open) => !open && close()}
            >
                <DialogContent>
                    {dialog?.mode === 'delete' ? (
                        <Form
                            {...routes.destroy(dialog.page.public_id)}
                            onSuccess={close}
                            className="flex flex-col gap-4"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <DialogHeader>
                                        <DialogTitle>
                                            Удалить страницу?
                                        </DialogTitle>
                                        <DialogDescription>
                                            {`Страница «${dialog.page.title}» и все её блоки будут удалены из черновика.`}
                                        </DialogDescription>
                                    </DialogHeader>
                                    <InputError message={errors.page} />
                                    <DialogFooter>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={close}
                                        >
                                            Отмена
                                        </Button>
                                        <Button
                                            type="submit"
                                            variant="destructive"
                                            disabled={processing}
                                        >
                                            Удалить
                                        </Button>
                                    </DialogFooter>
                                </>
                            )}
                        </Form>
                    ) : dialog?.mode === 'seo' && routes.seo ? (
                        <SeoForm
                            key={dialog.page.public_id}
                            action={routes.seo(dialog.page.public_id)}
                            page={dialog.page}
                            canEditIndexing={canEditSeoIndexing}
                            onDone={close}
                        />
                    ) : dialog ? (
                        <PageForm
                            key={
                                dialog.mode === 'edit'
                                    ? dialog.page.public_id
                                    : 'new'
                            }
                            action={
                                dialog.mode === 'edit'
                                    ? routes.update(dialog.page.public_id)
                                    : routes.store
                            }
                            page={dialog.mode === 'edit' ? dialog.page : null}
                            onDone={close}
                        />
                    ) : null}
                </DialogContent>
            </Dialog>
        </div>
    );
}

function SeoForm({
    action,
    page,
    canEditIndexing,
    onDone,
}: {
    action: RouteDefinition<'patch'>;
    page: DesignerPage;
    canEditIndexing: boolean;
    onDone: () => void;
}) {
    const form = useForm({
        seo_title: page.seo.title ?? '',
        seo_description: page.seo.description ?? '',
        seo_noindex: page.seo.noindex,
    });

    function save(event: FormEvent) {
        event.preventDefault();
        form.transform((data) =>
            canEditIndexing
                ? data
                : {
                      seo_title: data.seo_title,
                      seo_description: data.seo_description,
                  },
        );
        form.submit(action, {
            preserveScroll: true,
            onSuccess: onDone,
        });
    }

    return (
        <form onSubmit={save} className="flex flex-col gap-4">
            <DialogHeader>
                <DialogTitle>{`SEO страницы «${page.title}»`}</DialogTitle>
                <DialogDescription>
                    Посетители и поисковики увидят изменения после публикации.
                </DialogDescription>
            </DialogHeader>
            <div className="grid gap-2">
                <Label htmlFor="seo-title">Заголовок для поисковиков</Label>
                <Input
                    id="seo-title"
                    value={form.data.seo_title}
                    onChange={(event) =>
                        form.setData('seo_title', event.target.value)
                    }
                    maxLength={120}
                    placeholder={page.title}
                    aria-invalid={Boolean(form.errors.seo_title)}
                    aria-describedby="seo-title-help"
                />
                <p
                    id="seo-title-help"
                    className="text-xs text-muted-foreground"
                >
                    Если оставить пустым, используется название страницы.
                </p>
                <InputError message={form.errors.seo_title} />
            </div>
            <div className="grid gap-2">
                <Label htmlFor="seo-description">
                    Описание для поисковиков
                </Label>
                <textarea
                    id="seo-description"
                    value={form.data.seo_description}
                    onChange={(event) =>
                        form.setData('seo_description', event.target.value)
                    }
                    maxLength={300}
                    rows={3}
                    aria-invalid={Boolean(form.errors.seo_description)}
                    className="min-h-20 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-invalid:border-destructive"
                />
                <InputError message={form.errors.seo_description} />
            </div>
            {canEditIndexing && (
                <div className="flex items-start gap-2">
                    <Checkbox
                        id="seo-noindex"
                        checked={form.data.seo_noindex}
                        onCheckedChange={(checked) =>
                            form.setData('seo_noindex', checked === true)
                        }
                    />
                    <Label htmlFor="seo-noindex" className="leading-snug">
                        Скрыть страницу от поисковых систем
                    </Label>
                </div>
            )}
            <DialogFooter>
                <Button type="button" variant="outline" onClick={onDone}>
                    Отмена
                </Button>
                <Button type="submit" disabled={form.processing}>
                    Сохранить
                </Button>
            </DialogFooter>
        </form>
    );
}

function PageForm({
    action,
    page,
    onDone,
}: {
    action: RouteFormDefinition<'post'>;
    page: DesignerPage | null;
    onDone: () => void;
}) {
    return (
        <Form {...action} onSuccess={onDone} className="flex flex-col gap-4">
            {({ processing, errors }) => (
                <>
                    <DialogHeader>
                        <DialogTitle>
                            {page ? 'Изменить страницу' : 'Новая страница'}
                        </DialogTitle>
                        <DialogDescription>
                            Изменения сохраняются в черновике.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="grid gap-2">
                        <Label htmlFor="page-title">Название страницы</Label>
                        <Input
                            id="page-title"
                            name="title"
                            required
                            maxLength={120}
                            defaultValue={page?.title}
                            aria-invalid={Boolean(errors.title)}
                            aria-describedby={
                                errors.title ? 'page-title-error' : undefined
                            }
                        />
                        <InputError
                            id="page-title-error"
                            message={errors.title}
                        />
                    </div>
                    {!page?.is_home && (
                        <div className="grid gap-2">
                            <Label htmlFor="page-slug">Адрес страницы</Label>
                            <Input
                                id="page-slug"
                                name="slug"
                                maxLength={100}
                                defaultValue={page?.slug}
                                aria-invalid={Boolean(errors.slug)}
                                aria-describedby="page-slug-help"
                            />
                            <p
                                id="page-slug-help"
                                className="text-xs text-muted-foreground"
                            >
                                Латинские буквы, цифры и дефисы. Оставьте
                                пустым, чтобы адрес создался из названия.
                            </p>
                            <InputError message={errors.slug} />
                        </div>
                    )}
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={onDone}
                        >
                            Отмена
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {page ? 'Сохранить' : 'Создать страницу'}
                        </Button>
                    </DialogFooter>
                </>
            )}
        </Form>
    );
}
