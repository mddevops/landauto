import { useForm } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

/**
 * Reusable vehicle name and description (no prices or offers). `extra` carries the other
 * required fields of the update endpoint unchanged.
 */
export function VehicleTextForm({
    url,
    customName,
    customDescription,
    placeholder,
    extra,
    canEdit,
}: {
    url: string;
    customName: string | null;
    customDescription: string | null;
    placeholder: string;
    extra: Record<string, boolean | number>;
    canEdit: boolean;
}) {
    const form = useForm({
        custom_name: customName ?? '',
        custom_description: customDescription ?? '',
    });

    return (
        <form
            className="grid max-w-2xl gap-4"
            onSubmit={(event) => {
                event.preventDefault();
                form.transform((data) => ({
                    ...extra,
                    custom_name: data.custom_name.trim() || null,
                    custom_description: data.custom_description.trim() || null,
                }));
                form.patch(url, { preserveScroll: true });
            }}
        >
            <div className="grid gap-2">
                <Label htmlFor="vehicle-custom-name">Название</Label>
                <Input
                    id="vehicle-custom-name"
                    value={form.data.custom_name}
                    onChange={(event) =>
                        form.setData('custom_name', event.target.value)
                    }
                    maxLength={120}
                    placeholder={placeholder}
                    disabled={!canEdit}
                    aria-invalid={Boolean(form.errors.custom_name)}
                />
                <p className="text-xs text-muted-foreground">
                    Если не заполнено, используется название из каталога.
                </p>
                <InputError message={form.errors.custom_name} />
            </div>
            <div className="grid gap-2">
                <Label htmlFor="vehicle-custom-description">Описание</Label>
                <textarea
                    id="vehicle-custom-description"
                    value={form.data.custom_description}
                    onChange={(event) =>
                        form.setData('custom_description', event.target.value)
                    }
                    maxLength={2000}
                    rows={4}
                    disabled={!canEdit}
                    aria-invalid={Boolean(form.errors.custom_description)}
                    className="min-h-20 w-full min-w-0 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:opacity-50 aria-invalid:border-destructive"
                />
                <InputError message={form.errors.custom_description} />
            </div>
            {canEdit && (
                <div className="flex items-center gap-3">
                    <Button type="submit" disabled={form.processing}>
                        {form.processing && <Spinner />}
                        Сохранить
                    </Button>
                    {form.recentlySuccessful && (
                        <p className="text-sm text-muted-foreground">
                            Сохранено
                        </p>
                    )}
                </div>
            )}
        </form>
    );
}
