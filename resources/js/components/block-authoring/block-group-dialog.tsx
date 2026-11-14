import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent, ReactNode } from 'react';
import { SelectField, TextField } from '@/components/platform/form-fields';
import type { Choice } from '@/components/platform/form-fields';
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
import { store, update } from '@/routes/platform/blocks/groups';

export type BlockGroupValues = {
    public_id: string;
    name: string;
    kind: string;
};

export function BlockGroupDialog({
    title,
    group,
    kinds,
    trigger,
}: {
    title: string;
    group?: BlockGroupValues;
    kinds: Choice[];
    trigger: ReactNode;
}) {
    const [open, setOpen] = useState(false);
    const form = useForm({
        name: group?.name ?? '',
        kind: group?.kind ?? 'other',
    });
    const id = group?.public_id ?? 'new';

    function submit(event: FormEvent) {
        event.preventDefault();
        form.submit(group ? update(group.public_id) : store(), {
            preserveScroll: true,
            onSuccess: () => {
                setOpen(false);

                if (!group) {
                    form.reset();
                }
            },
        });
    }

    return (
        <Dialog
            open={open}
            onOpenChange={(value) => {
                setOpen(value);
                form.clearErrors();
            }}
        >
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    <DialogDescription>
                        Группа объединяет блоки одного назначения: например,
                        «Шапка» или «Подвал». Тип определяет, как блоки группы
                        показываются в дизайнере.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="grid gap-4">
                    <TextField
                        id={`block-group-name-${id}`}
                        label="Название"
                        maxLength={80}
                        autoComplete="off"
                        autoFocus
                        required
                        value={form.data.name}
                        onChange={(event) =>
                            form.setData('name', event.target.value)
                        }
                        error={form.errors.name}
                    />
                    <SelectField
                        id={`block-group-kind-${id}`}
                        label="Тип блоков"
                        choices={kinds}
                        required
                        value={form.data.kind}
                        onChange={(event) =>
                            form.setData('kind', event.target.value)
                        }
                        error={form.errors.kind}
                    />
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button type="button" variant="outline">
                                Отмена
                            </Button>
                        </DialogClose>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing && <Spinner />}
                            Сохранить
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
