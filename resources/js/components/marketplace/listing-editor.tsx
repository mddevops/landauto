import { Form } from '@inertiajs/react';
import {
    DESCRIPTION_MAX,
    ListingPageError,
    TITLE_MAX,
    textareaClassName,
} from '@/components/marketplace/listing-index';
import {
    ListingPublicationAction,
    ListingStatusBadge,
    formatListingDate,
} from '@/components/marketplace/listing-status';
import { Field, TextField } from '@/components/platform/form-fields';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import type { MarketplaceListingDetail } from '@/types/marketplace';
import type { RouteFormDefinition } from '@/wayfinder';

type ListingEditorProps = {
    listing: MarketplaceListingDetail;
    updateAction: RouteFormDefinition<'post'>;
    publishAction: RouteFormDefinition<'post'>;
    unpublishAction: RouteFormDefinition<'post'>;
    accessHint: string;
};

function visibilityText(listing: MarketplaceListingDetail): string {
    if (listing.publicly_visible) {
        return 'Карточка опубликована и будет видна в Marketplace.';
    }

    return listing.status === 'published'
        ? 'Карточка опубликована, но сейчас не будет видна в Marketplace.'
        : 'Черновик не виден в Marketplace.';
}

export function ListingEditor({
    listing,
    updateAction,
    publishAction,
    unpublishAction,
    accessHint,
}: ListingEditorProps) {
    return (
        <main className="flex min-w-0 flex-1 flex-col gap-6 p-4 sm:p-6">
            <header className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div className="min-w-0 space-y-2">
                    <h1 className="text-2xl font-semibold tracking-tight break-words sm:text-3xl">
                        {listing.title}
                    </h1>
                    <div className="flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                        <ListingStatusBadge
                            status={listing.status}
                            label={listing.status_label}
                        />
                        <span>{visibilityText(listing)}</span>
                    </div>
                </div>
                <ListingPublicationAction
                    status={listing.status}
                    title={listing.title}
                    publishAction={publishAction}
                    unpublishAction={unpublishAction}
                    size="default"
                />
            </header>

            <ListingPageError />

            {listing.publication_denial && (
                <p
                    role="status"
                    className="rounded-xl border bg-muted/40 p-4 text-sm"
                >
                    {listing.publication_denial}
                </p>
            )}

            <div className="grid min-w-0 gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
                <section
                    aria-labelledby="listing-card-title"
                    className="min-w-0 rounded-xl border bg-card p-4 shadow-sm sm:p-6"
                >
                    <h2 id="listing-card-title" className="mb-4 font-semibold">
                        Карточка
                    </h2>
                    <Form
                        {...updateAction}
                        options={{ preserveScroll: true }}
                        disableWhileProcessing
                        className="grid min-w-0 gap-4"
                    >
                        {({ processing, errors }) => (
                            <>
                                <TextField
                                    id="listing-title"
                                    name="title"
                                    label="Название"
                                    required
                                    maxLength={TITLE_MAX}
                                    autoComplete="off"
                                    defaultValue={listing.title}
                                    error={errors.title}
                                />
                                <TextField
                                    id="listing-slug"
                                    label="Slug"
                                    value={listing.slug}
                                    readOnly
                                    hint="Адрес карточки в Marketplace, после создания не меняется."
                                />
                                <Field
                                    id="listing-description"
                                    label="Описание"
                                    hint="Обычный текст без HTML-разметки."
                                    error={errors.description}
                                >
                                    <textarea
                                        id="listing-description"
                                        name="description"
                                        rows={6}
                                        maxLength={DESCRIPTION_MAX}
                                        defaultValue={listing.description ?? ''}
                                        aria-invalid={Boolean(
                                            errors.description,
                                        )}
                                        className={textareaClassName}
                                    />
                                </Field>
                                <div className="flex justify-end">
                                    <Button type="submit" disabled={processing}>
                                        {processing && <Spinner />}
                                        Сохранить
                                    </Button>
                                </div>
                            </>
                        )}
                    </Form>
                </section>

                <aside
                    aria-labelledby="listing-product-title"
                    className="min-w-0 space-y-3 rounded-xl border bg-card p-4 text-sm shadow-sm"
                >
                    <h2 id="listing-product-title" className="font-semibold">
                        Продукт
                    </h2>
                    <dl className="grid gap-x-4 gap-y-2 sm:grid-cols-[auto_minmax(0,1fr)] lg:grid-cols-1">
                        <dt className="text-muted-foreground">Тип</dt>
                        <dd>{listing.product_type_label}</dd>
                        <dt className="text-muted-foreground">Продукт</dt>
                        <dd className="break-words">
                            {listing.product_name}{' '}
                            <code className="text-xs break-all text-muted-foreground">
                                {listing.product_slug}
                            </code>
                        </dd>
                        <dt className="text-muted-foreground">Автор</dt>
                        <dd className="break-words">{listing.author}</dd>
                        <dt className="text-muted-foreground">
                            Текущий режим доступа
                        </dt>
                        <dd className="break-words">
                            {listing.access?.label ?? '—'}
                            {listing.access?.detail && (
                                <span className="block text-muted-foreground">
                                    {listing.access.detail}
                                </span>
                            )}
                        </dd>
                        <dt className="text-muted-foreground">
                            Опубликованная версия
                        </dt>
                        <dd>
                            {listing.has_published_version ? 'Есть' : 'Нет'}
                        </dd>
                        <dt className="text-muted-foreground">
                            Статус в Marketplace
                        </dt>
                        <dd>
                            {listing.status_label}
                            {listing.published_at && (
                                <span className="block text-muted-foreground">
                                    с {formatListingDate(listing.published_at)}
                                </span>
                            )}
                        </dd>
                    </dl>
                    <p className="text-xs text-muted-foreground">
                        {accessHint}
                    </p>
                </aside>
            </div>
        </main>
    );
}
