import { Form, Link, usePage } from '@inertiajs/react';
import { Plus, Store } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import {
    ListingPublicationAction,
    ListingStatusBadge,
    formatListingDate,
} from '@/components/marketplace/listing-status';
import {
    Field,
    SelectField,
    TextField,
} from '@/components/platform/form-fields';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import type {
    MarketplaceListingItem,
    MarketplaceProductOption,
    MarketplaceProductType,
    MarketplaceProductTypeOption,
} from '@/types/marketplace';
import type { RouteDefinition, RouteFormDefinition } from '@/wayfinder';

export const TITLE_MAX = 120;
export const SLUG_MAX = 80;
export const DESCRIPTION_MAX = 5000;

export const textareaClassName =
    'min-h-28 w-full min-w-0 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-invalid:border-destructive dark:bg-input/30';

type ListingIndexProps = {
    title: string;
    description: string;
    listings: MarketplaceListingItem[];
    products: MarketplaceProductOption[];
    productTypes: MarketplaceProductTypeOption[];
    emptyProductsText: Record<MarketplaceProductType, string>;
    storeAction: RouteFormDefinition<'post'>;
    showHref: (publicId: string) => RouteDefinition<'get'>;
    publishAction: (publicId: string) => RouteFormDefinition<'post'>;
    unpublishAction: (publicId: string) => RouteFormDefinition<'post'>;
};

export function ListingPageError() {
    const { errors } = usePage().props;
    const message = (errors as Record<string, string | undefined>).listing;

    return message ? (
        <p
            role="alert"
            className="rounded-xl border border-destructive/40 bg-destructive/5 p-4 text-sm text-destructive"
        >
            {message}
        </p>
    ) : null;
}

function CreateListingForm({
    products,
    productTypes,
    emptyProductsText,
    storeAction,
    onCancel,
}: Pick<
    ListingIndexProps,
    'products' | 'productTypes' | 'emptyProductsText' | 'storeAction'
> & { onCancel: () => void }) {
    const [type, setType] = useState<MarketplaceProductType>('block');
    const choices = products
        .filter((product) => product.product_type === type)
        .map((product) => ({
            value: product.public_id,
            label: product.has_published_version
                ? product.name
                : `${product.name} — нет опубликованной версии`,
        }));

    return (
        <section
            aria-labelledby="create-listing-title"
            className="w-full max-w-2xl rounded-xl border bg-card p-4 shadow-sm sm:p-6"
        >
            <h2 id="create-listing-title" className="mb-4 font-semibold">
                Новая карточка
            </h2>
            <Form
                {...storeAction}
                options={{ preserveScroll: true, preserveState: true }}
                disableWhileProcessing
                className="grid min-w-0 gap-4"
            >
                {({ processing, errors }) => (
                    <>
                        <fieldset className="grid gap-2">
                            <legend className="mb-1 text-sm font-medium">
                                Тип
                            </legend>
                            <div className="flex flex-wrap gap-4">
                                {productTypes.map((option) => (
                                    <div
                                        key={option.value}
                                        className="flex items-center gap-2"
                                    >
                                        <input
                                            id={`listing-type-${option.value}`}
                                            type="radio"
                                            name="product_type"
                                            value={option.value}
                                            checked={type === option.value}
                                            onChange={() =>
                                                setType(option.value)
                                            }
                                            className="size-4 accent-primary"
                                        />
                                        <Label
                                            htmlFor={`listing-type-${option.value}`}
                                        >
                                            {option.label}
                                        </Label>
                                    </div>
                                ))}
                            </div>
                            <InputError message={errors.product_type} />
                        </fieldset>
                        {choices.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                {emptyProductsText[type]}
                            </p>
                        ) : (
                            <SelectField
                                key={type}
                                id="listing-product"
                                name="product"
                                label="Продукт"
                                choices={choices}
                                emptyLabel="Выберите продукт"
                                required
                                error={errors.product}
                            />
                        )}
                        <TextField
                            id="listing-title"
                            name="title"
                            label="Название"
                            required
                            maxLength={TITLE_MAX}
                            autoComplete="off"
                            error={errors.title}
                        />
                        <TextField
                            id="listing-slug"
                            name="slug"
                            label="Slug"
                            required
                            minLength={3}
                            maxLength={SLUG_MAX}
                            autoComplete="off"
                            spellCheck={false}
                            hint="Строчные латинские буквы, цифры и дефисы, 3–80 символов. Адрес карточки в Marketplace, после создания не меняется."
                            error={errors.slug}
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
                                rows={4}
                                maxLength={DESCRIPTION_MAX}
                                aria-invalid={Boolean(errors.description)}
                                className={textareaClassName}
                            />
                        </Field>
                        <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={onCancel}
                            >
                                Отмена
                            </Button>
                            <Button
                                type="submit"
                                disabled={processing || choices.length === 0}
                            >
                                {processing && <Spinner />}
                                Создать карточку
                            </Button>
                        </div>
                    </>
                )}
            </Form>
        </section>
    );
}

export function ListingIndex({
    title,
    description,
    listings,
    products,
    productTypes,
    emptyProductsText,
    storeAction,
    showHref,
    publishAction,
    unpublishAction,
}: ListingIndexProps) {
    const { errors } = usePage().props;
    const [creating, setCreating] = useState(() =>
        ['product_type', 'product', 'title', 'slug', 'description'].some(
            (key) => key in errors,
        ),
    );

    return (
        <main className="flex min-w-0 flex-1 flex-col gap-6 p-4 sm:p-6">
            <header className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div className="min-w-0 space-y-1">
                    <h1 className="text-2xl font-semibold tracking-tight sm:text-3xl">
                        {title}
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        {description}
                    </p>
                </div>
                {!creating && (
                    <Button onClick={() => setCreating(true)}>
                        <Plus aria-hidden="true" />
                        Создать карточку
                    </Button>
                )}
            </header>

            <ListingPageError />

            {creating && (
                <CreateListingForm
                    products={products}
                    productTypes={productTypes}
                    emptyProductsText={emptyProductsText}
                    storeAction={storeAction}
                    onCancel={() => setCreating(false)}
                />
            )}

            {listings.length === 0 ? (
                <section className="flex flex-col items-center gap-3 rounded-xl border border-dashed p-8 text-center">
                    <Store
                        aria-hidden="true"
                        className="size-8 text-muted-foreground"
                    />
                    <p className="text-sm text-muted-foreground">
                        Карточек пока нет.
                    </p>
                </section>
            ) : (
                <ul
                    aria-label="Карточки Marketplace"
                    className="divide-y rounded-xl border bg-card shadow-sm"
                >
                    {listings.map((listing) => (
                        <li
                            key={listing.public_id}
                            data-testid="marketplace-listing"
                            className="flex min-w-0 flex-col gap-3 px-4 py-3 lg:flex-row lg:items-center lg:gap-4"
                        >
                            <div className="min-w-0 flex-1 space-y-1">
                                <p className="font-medium break-words">
                                    {listing.title}
                                </p>
                                <p className="text-xs break-words text-muted-foreground">
                                    {listing.product_type_label} «
                                    {listing.product_name}» · {listing.author}
                                </p>
                                <div className="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted-foreground">
                                    <ListingStatusBadge
                                        status={listing.status}
                                        label={listing.status_label}
                                    />
                                    {listing.access && (
                                        <Badge variant="outline">
                                            {listing.access.label}
                                        </Badge>
                                    )}
                                    {listing.published_at && (
                                        <span>
                                            Опубликовано{' '}
                                            {formatListingDate(
                                                listing.published_at,
                                            )}
                                        </span>
                                    )}
                                </div>
                            </div>
                            <div className="flex flex-wrap gap-2">
                                <Button size="sm" variant="secondary" asChild>
                                    <Link
                                        href={showHref(listing.public_id)}
                                        aria-label={`Открыть: ${listing.title}`}
                                    >
                                        Открыть
                                    </Link>
                                </Button>
                                <ListingPublicationAction
                                    status={listing.status}
                                    title={listing.title}
                                    publishAction={publishAction(
                                        listing.public_id,
                                    )}
                                    unpublishAction={unpublishAction(
                                        listing.public_id,
                                    )}
                                />
                            </div>
                        </li>
                    ))}
                </ul>
            )}
        </main>
    );
}
