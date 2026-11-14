import { useForm } from '@inertiajs/react';
import { Check, Copy } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

export type VehicleAddress = {
    slug: string;
    base_url: string | null;
    redirects: string[];
};

/**
 * «Адрес страницы» of the vehicle card in the catalog section. The previous address keeps
 * redirecting to the new one after publication.
 */
export function VehicleAddressForm({
    url,
    address,
    canEdit,
}: {
    url: string;
    address: VehicleAddress;
    canEdit: boolean;
}) {
    const form = useForm({ slug: address.slug });
    const [copied, setCopied] = useState(false);
    const fullUrl = address.base_url
        ? `${address.base_url}${address.slug}`
        : null;

    function save(event: FormEvent) {
        event.preventDefault();
        form.patch(url, { preserveScroll: true });
    }

    function copy() {
        if (fullUrl === null) {
            return;
        }

        void navigator.clipboard?.writeText(fullUrl).then(() => {
            setCopied(true);
            window.setTimeout(() => setCopied(false), 2000);
        });
    }

    return (
        <form
            onSubmit={save}
            aria-label="Адрес страницы"
            className="flex max-w-2xl flex-col gap-3 rounded-xl border p-4"
        >
            <div className="grid gap-2">
                <Label htmlFor="vehicle-slug">Адрес страницы</Label>
                <div className="flex min-w-0 items-stretch overflow-hidden rounded-md border shadow-xs focus-within:border-ring focus-within:ring-[3px] focus-within:ring-ring/50">
                    {address.base_url && (
                        <span className="hidden max-w-[60%] items-center truncate border-r bg-muted px-3 text-sm text-muted-foreground sm:flex">
                            {address.base_url}
                        </span>
                    )}
                    <input
                        id="vehicle-slug"
                        value={form.data.slug}
                        maxLength={100}
                        required
                        disabled={!canEdit}
                        autoComplete="off"
                        spellCheck={false}
                        onChange={(event) =>
                            form.setData('slug', event.target.value)
                        }
                        aria-invalid={Boolean(form.errors.slug)}
                        aria-describedby="vehicle-slug-help"
                        className="h-9 min-w-0 flex-1 bg-transparent px-3 font-mono text-sm outline-none"
                    />
                </div>
                <p
                    id="vehicle-slug-help"
                    className="text-xs text-muted-foreground"
                >
                    {address.base_url
                        ? 'Строчные латинские буквы, цифры и дефисы. Старый адрес будет перенаправлять на новый.'
                        : 'Адрес заработает, когда в «Разделах» сайта будет добавлен каталог автомобилей.'}
                </p>
                <InputError message={form.errors.slug} />
            </div>
            {address.redirects.length > 0 && (
                <div className="space-y-1 text-sm">
                    <p className="text-muted-foreground">
                        Старые адреса перенаправляют на текущий:
                    </p>
                    <ul className="flex flex-wrap gap-2">
                        {address.redirects.map((slug) => (
                            <li key={slug}>
                                <Badge variant="outline" className="font-mono">
                                    {slug}
                                </Badge>
                            </li>
                        ))}
                    </ul>
                </div>
            )}
            <div className="flex flex-wrap gap-2">
                {canEdit && (
                    <Button
                        type="submit"
                        disabled={
                            form.processing ||
                            form.data.slug.trim() === address.slug
                        }
                    >
                        {form.processing && <Spinner />}
                        Сохранить адрес
                    </Button>
                )}
                {fullUrl && (
                    <Button type="button" variant="outline" onClick={copy}>
                        {copied ? (
                            <Check aria-hidden="true" />
                        ) : (
                            <Copy aria-hidden="true" />
                        )}
                        {copied ? 'Скопировано' : 'Скопировать ссылку'}
                    </Button>
                )}
            </div>
        </form>
    );
}
