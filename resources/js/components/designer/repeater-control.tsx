import { ArrowDown, ArrowUp, Copy, Plus, Trash2 } from 'lucide-react';
import type { ReactNode } from 'react';
import type { SchemaField } from '@/blocks/schema';
import type { BlockState, RepeaterItem } from '@/blocks/state';
import { ulid } from '@/blocks/ulid';
import { FieldList, isState } from '@/components/designer/properties-panel';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';

type RepeaterControlProps = {
    field: SchemaField;
    value: unknown;
    path: string;
    errors: Record<string, string>;
    onChange: (value: RepeaterItem[]) => void;
};

export function RepeaterControl({
    field,
    value,
    path,
    errors,
    onChange,
}: RepeaterControlProps) {
    const items = Array.isArray(value)
        ? value.filter(
              (item): item is RepeaterItem =>
                  isState(item) && typeof item.id === 'string',
          )
        : [];
    const maxItems = field.max_items ?? 0;
    const fields = field.fields ?? [];
    const canAdd = items.length < maxItems;

    const replace = (index: number, item: RepeaterItem) =>
        onChange(items.map((current, i) => (i === index ? item : current)));
    const moveItem = (index: number, offset: number) => {
        const next = [...items];
        const [item] = next.splice(index, 1);
        next.splice(index + offset, 0, item);
        onChange(next);
    };

    return (
        <fieldset className="grid gap-3 rounded-md border p-3">
            <legend className="px-1 text-sm font-medium">
                {`${field.label} (${items.length} из ${maxItems})`}
            </legend>
            {items.length === 0 && (
                <p className="text-sm text-muted-foreground">
                    Элементов пока нет.
                </p>
            )}
            <ol className="grid gap-3">
                {items.map((item, index) => {
                    const title = itemTitle(fields, item, index);

                    return (
                        <li
                            key={item.id}
                            className="grid gap-3 rounded-md bg-muted/50 p-3"
                        >
                            <div className="flex items-center gap-1">
                                <span className="min-w-0 flex-1 truncate text-sm font-medium">
                                    {title}
                                </span>
                                <ItemAction
                                    label={`Переместить «${title}» выше`}
                                    disabled={index === 0}
                                    onClick={() => moveItem(index, -1)}
                                >
                                    <ArrowUp />
                                </ItemAction>
                                <ItemAction
                                    label={`Переместить «${title}» ниже`}
                                    disabled={index === items.length - 1}
                                    onClick={() => moveItem(index, 1)}
                                >
                                    <ArrowDown />
                                </ItemAction>
                                <ItemAction
                                    label={`Дублировать «${title}»`}
                                    disabled={!canAdd}
                                    onClick={() => {
                                        const next = [...items];
                                        next.splice(index + 1, 0, {
                                            ...structuredClone(item),
                                            id: ulid(),
                                        });
                                        onChange(next);
                                    }}
                                >
                                    <Copy />
                                </ItemAction>
                                <ItemAction
                                    label={`Удалить «${title}»`}
                                    onClick={() =>
                                        onChange(
                                            items.filter((_, i) => i !== index),
                                        )
                                    }
                                >
                                    <Trash2 />
                                </ItemAction>
                            </div>
                            <FieldList
                                fields={fields}
                                value={item}
                                path={`${path}.${index}`}
                                errors={errors}
                                onChange={(next: BlockState) =>
                                    replace(index, { ...next, id: item.id })
                                }
                            />
                        </li>
                    );
                })}
            </ol>
            <Button
                type="button"
                variant="outline"
                size="sm"
                disabled={!canAdd}
                onClick={() =>
                    onChange([...items, { ...defaultsFor(fields), id: ulid() }])
                }
            >
                <Plus aria-hidden="true" />
                Добавить элемент
            </Button>
            <InputError message={errors[path]} />
        </fieldset>
    );
}

function ItemAction({
    label,
    disabled,
    onClick,
    children,
}: {
    label: string;
    disabled?: boolean;
    onClick: () => void;
    children: ReactNode;
}) {
    return (
        <Button
            type="button"
            variant="ghost"
            size="icon"
            className="size-7 [&_svg]:size-3.5"
            aria-label={label}
            title={label}
            disabled={disabled}
            onClick={onClick}
        >
            {children}
        </Button>
    );
}

function itemTitle(fields: SchemaField[], item: RepeaterItem, index: number) {
    const textField = fields.find((field) => field.type === 'text');
    const value = textField ? item[textField.key] : null;

    return typeof value === 'string' && value.trim() !== ''
        ? value
        : `Элемент ${index + 1}`;
}

function defaultsFor(fields: SchemaField[]): BlockState {
    const state: BlockState = {};

    for (const field of fields) {
        if (field.type === 'group') {
            state[field.key] = defaultsFor(field.fields ?? []);
        } else if (field.type === 'repeater') {
            state[field.key] = [];
        } else if (field.default !== undefined) {
            state[field.key] = field.default;
        }
    }

    return state;
}
