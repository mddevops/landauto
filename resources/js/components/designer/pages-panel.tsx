import { Form, Link } from '@inertiajs/react';
import {
    ChevronDown,
    FileText,
    FolderTree,
    House,
    Pencil,
    Plus,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import type { SectionPageRole } from '@/blocks/catalog';
import type {
    DesignerPage,
    DesignerPageRoutes,
} from '@/components/designer/types';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
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
import type { RouteFormDefinition } from '@/wayfinder';

type SectionNav = {
    public_id: string;
    title: string;
    prefix: string;
    root: boolean;
    enabled: boolean;
    source?: 'site' | 'stock';
    pages: Partial<Record<SectionPageRole, string>>;
};

type PagesPanelProps = {
    routes: DesignerPageRoutes;
    pages: DesignerPage[];
    currentPageId: string;
    canEdit: boolean;
    canAddPages: boolean;
    /** @deprecated Prefer `sections`; kept for Template Designer / single-section callers. */
    catalog?: SectionNav | null;
    sections?: SectionNav[];
};

const catalogRoles: SectionPageRole[] = ['index', 'mark', 'item'];
const stockRoles: SectionPageRole[] = ['index', 'mark', 'model', 'item'];

const catalogExamples: Record<SectionPageRole, string> = {
    index: '',
    mark: '/kia',
    model: '/kia',
    item: '/kia/rio-iv-restyling',
};

const stockExamples: Record<SectionPageRole, string> = {
    index: '',
    mark: '/kia',
    model: '/kia/rio',
    item: '/kia/rio/4564',
};

function examplePath(
    section: { prefix: string; root: boolean; source?: 'site' | 'stock' },
    role: SectionPageRole | null | undefined,
): string {
    if (section.root) {
        return '/rio-iv-restyling';
    }

    const examples =
        section.source === 'stock' ? stockExamples : catalogExamples;

    return `/${section.prefix}${role ? examples[role] : ''}`;
}

function sectionPagesFor(
    section: SectionNav,
    pages: DesignerPage[],
): DesignerPage[] {
    const roles = (section.source === 'stock' ? stockRoles : catalogRoles).filter(
        (role) => Boolean(section.pages[role]),
    );
    const byId = new Map(pages.map((page) => [page.public_id, page]));

    return roles
        .map((role) => byId.get(section.pages[role] ?? ''))
        .filter((page): page is DesignerPage => page !== undefined);
}

type DialogState =
    | { mode: 'create' }
    | { mode: 'edit'; page: DesignerPage }
    | { mode: 'delete'; page: DesignerPage }
    | null;

const linkClass =
    'flex min-w-0 flex-1 items-center gap-2 rounded-md px-2 py-1.5 text-sm transition-colors hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none';

export function PagesPanel({
    routes,
    pages,
    currentPageId,
    canEdit,
    canAddPages,
    catalog = null,
    sections,
}: PagesPanelProps) {
    const [dialog, setDialog] = useState<DialogState>(null);
    const close = () => setDialog(null);
    const regularPages = pages.filter((page) => !page.section_role);
    const navSections = sections ?? (catalog ? [catalog] : []);
    const [openSections, setOpenSections] = useState<Record<string, boolean>>(
        () => {
            const initial: Record<string, boolean> = {};

            for (const section of navSections) {
                const sectionPages = sectionPagesFor(section, pages);
                initial[section.public_id] = sectionPages.some(
                    (page) => page.public_id === currentPageId,
                );
            }

            return initial;
        },
    );

    return (
        <div className="flex flex-col gap-3">
            <ul className="flex flex-col gap-1">
                {regularPages.map((page) => (
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
                                linkClass,
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
                {navSections.map((section) => {
                    const sectionPages = sectionPagesFor(section, pages);

                    if (sectionPages.length === 0) {
                        return null;
                    }

                    const open = openSections[section.public_id] ?? false;
                    const listId = `designer-section-pages-${section.public_id}`;
                    const headingId = `designer-section-${section.public_id}`;

                    return (
                        <li key={section.public_id}>
                            <section
                                aria-labelledby={headingId}
                                className="flex flex-col gap-1"
                            >
                                <h2 id={headingId}>
                                    <button
                                        type="button"
                                        aria-expanded={open}
                                        aria-controls={listId}
                                        onClick={() =>
                                            setOpenSections((current) => ({
                                                ...current,
                                                [section.public_id]:
                                                    !current[section.public_id],
                                            }))
                                        }
                                        className={cn(linkClass, 'w-full')}
                                    >
                                        <FolderTree
                                            className="size-4 shrink-0"
                                            aria-hidden="true"
                                        />
                                        <span className="flex-1 truncate text-left">
                                            {section.title}
                                            {!section.enabled && (
                                                <span className="text-muted-foreground">
                                                    {' '}
                                                    (выключен)
                                                </span>
                                            )}
                                        </span>
                                        <ChevronDown
                                            aria-hidden="true"
                                            className={cn(
                                                'size-4 shrink-0 text-muted-foreground transition-transform',
                                                !open && '-rotate-90',
                                            )}
                                        />
                                    </button>
                                </h2>
                                {open && (
                                    <ul
                                        id={listId}
                                        className="ml-4 flex flex-col gap-1 border-l pl-2"
                                    >
                                        {sectionPages.map((page) => (
                                            <li
                                                key={page.public_id}
                                                className="flex items-center gap-1"
                                            >
                                                <Link
                                                    href={routes.href(
                                                        page.public_id,
                                                    )}
                                                    aria-current={
                                                        page.public_id ===
                                                        currentPageId
                                                            ? 'page'
                                                            : undefined
                                                    }
                                                    className={cn(
                                                        linkClass,
                                                        'flex-col items-start gap-0',
                                                        page.public_id ===
                                                            currentPageId &&
                                                            'bg-muted font-medium',
                                                    )}
                                                >
                                                    <span className="max-w-full truncate">
                                                        {page.title}
                                                    </span>
                                                    <span className="max-w-full truncate font-mono text-xs font-normal text-muted-foreground">
                                                        {examplePath(
                                                            section,
                                                            page.section_role,
                                                        )}
                                                    </span>
                                                </Link>
                                                {canEdit && (
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="icon"
                                                        className="size-8"
                                                        aria-label={`Изменить страницу «${page.title}»`}
                                                        onClick={() =>
                                                            setDialog({
                                                                mode: 'edit',
                                                                page,
                                                            })
                                                        }
                                                    >
                                                        <Pencil aria-hidden="true" />
                                                    </Button>
                                                )}
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </section>
                        </li>
                    );
                })}
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
                    {!page?.is_home && !page?.section_role && (
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
