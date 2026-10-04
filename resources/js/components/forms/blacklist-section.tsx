import { router, useForm } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import type { FormEvent } from 'react';
import type { Choice } from '@/components/platform/form-fields';
import { SelectField, TextField } from '@/components/platform/form-fields';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
} from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';
import { destroy, store } from '@/routes/sites/blacklist';

export type BlacklistRow = {
    public_id: string;
    type: string;
    type_label: string;
    value: string;
    reason: string | null;
    expires_at: string | null;
    active: boolean;
};

const dateFormat = new Intl.DateTimeFormat('ru-RU', { dateStyle: 'medium' });

export function BlacklistSection({
    sitePublicId,
    scope,
    title,
    description,
    entries,
    types,
}: {
    sitePublicId: string;
    scope: 'site' | 'workspace';
    title: string;
    description: string;
    entries: BlacklistRow[];
    types: Choice[];
}) {
    const prefix = `blacklist-${scope}`;
    const form = useForm({
        scope,
        type: 'phone',
        value: '',
        reason: '',
        expires_in_days: '',
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        form.submit(store(sitePublicId), {
            preserveScroll: true,
            onSuccess: () => form.reset('value', 'reason', 'expires_in_days'),
        });
    }

    return (
        <Card>
            <CardHeader>
                <h2 className="leading-none font-semibold">{title}</h2>
                <CardDescription>{description}</CardDescription>
            </CardHeader>
            <CardContent className="flex flex-col gap-4">
                <form
                    onSubmit={submit}
                    className="grid gap-4 sm:grid-cols-2 lg:grid-cols-[10rem_minmax(0,1fr)_minmax(0,1fr)_8rem_auto] lg:items-end"
                    aria-label={`Добавить: ${title}`}
                >
                    <SelectField
                        id={`${prefix}-type`}
                        label="Тип"
                        choices={types}
                        value={form.data.type}
                        onChange={(event) =>
                            form.setData('type', event.target.value)
                        }
                        error={form.errors.type}
                    />
                    <TextField
                        id={`${prefix}-value`}
                        label="Значение"
                        value={form.data.value}
                        onChange={(event) =>
                            form.setData('value', event.target.value)
                        }
                        maxLength={64}
                        required
                        error={form.errors.value}
                    />
                    <TextField
                        id={`${prefix}-reason`}
                        label="Причина"
                        value={form.data.reason}
                        onChange={(event) =>
                            form.setData('reason', event.target.value)
                        }
                        maxLength={255}
                        error={form.errors.reason}
                    />
                    <TextField
                        id={`${prefix}-days`}
                        label="Срок, дней"
                        hint="Пусто — бессрочно."
                        type="number"
                        inputMode="numeric"
                        min={1}
                        max={3650}
                        value={form.data.expires_in_days}
                        onChange={(event) =>
                            form.setData('expires_in_days', event.target.value)
                        }
                        error={form.errors.expires_in_days}
                    />
                    <Button type="submit" disabled={form.processing}>
                        {form.processing && <Spinner />}
                        Добавить
                    </Button>
                </form>
                {entries.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        Записей нет.
                    </p>
                ) : (
                    <ul className="flex flex-col divide-y rounded-md border">
                        {entries.map((entry) => (
                            <li
                                key={entry.public_id}
                                className="flex flex-wrap items-center gap-3 p-3 text-sm"
                            >
                                <div className="min-w-0 flex-1">
                                    <p className="font-medium break-all">
                                        {`${entry.type_label}: ${entry.value}`}
                                    </p>
                                    <p className="text-muted-foreground">
                                        {[
                                            entry.reason,
                                            entry.expires_at
                                                ? `до ${dateFormat.format(new Date(entry.expires_at))}`
                                                : 'бессрочно',
                                        ]
                                            .filter(Boolean)
                                            .join(' · ')}
                                    </p>
                                </div>
                                {!entry.active && (
                                    <Badge variant="secondary">Истекла</Badge>
                                )}
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label={`Удалить запись ${entry.value}`}
                                    onClick={() =>
                                        router.delete(
                                            destroy.url({
                                                site: sitePublicId,
                                                entry: entry.public_id,
                                            }),
                                            { preserveScroll: true },
                                        )
                                    }
                                >
                                    <Trash2 aria-hidden="true" />
                                </Button>
                            </li>
                        ))}
                    </ul>
                )}
            </CardContent>
        </Card>
    );
}
