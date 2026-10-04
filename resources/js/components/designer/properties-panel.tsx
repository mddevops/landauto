import { isFieldVisible } from '@/blocks/schema';
import type { SchemaField } from '@/blocks/schema';
import type { BlockState } from '@/blocks/state';
import { ActionControl } from '@/components/designer/action-control';
import { ImageControl } from '@/components/designer/image-control';
import { RepeaterControl } from '@/components/designer/repeater-control';
import type { DesignerBlock } from '@/components/designer/types';
import InputError from '@/components/input-error';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type PropertiesPanelProps = {
    block: DesignerBlock;
    state: BlockState;
    errors: Record<string, string>;
    disabled: boolean;
    onChange: (state: BlockState) => void;
};

export function PropertiesPanel({
    block,
    state,
    errors,
    disabled,
    onChange,
}: PropertiesPanelProps) {
    return (
        <fieldset disabled={disabled} className="flex flex-col gap-4">
            <legend className="sr-only">{`Свойства блока «${block.name}»`}</legend>
            <FieldList
                fields={block.schema.fields}
                value={state}
                path="state"
                errors={errors}
                onChange={onChange}
            />
        </fieldset>
    );
}

export function FieldList({
    fields,
    value,
    path,
    errors,
    onChange,
}: {
    fields: SchemaField[];
    value: BlockState;
    path: string;
    errors: Record<string, string>;
    onChange: (value: BlockState) => void;
}) {
    return (
        <>
            {fields
                .filter((field) => isFieldVisible(field, value))
                .map((field) => (
                    <FieldControl
                        key={field.key}
                        field={field}
                        value={value[field.key]}
                        path={`${path}.${field.key}`}
                        errors={errors}
                        onChange={(next) =>
                            onChange({ ...value, [field.key]: next })
                        }
                    />
                ))}
        </>
    );
}

function FieldControl({
    field,
    value,
    path,
    errors,
    onChange,
}: {
    field: SchemaField;
    value: unknown;
    path: string;
    errors: Record<string, string>;
    onChange: (value: unknown) => void;
}) {
    const id = `field-${path.replaceAll('.', '-')}`;
    const error = errors[path];
    const describedBy =
        [field.help ? `${id}-help` : null, error ? `${id}-error` : null]
            .filter(Boolean)
            .join(' ') || undefined;
    const help = field.help ? (
        <p id={`${id}-help`} className="text-xs text-muted-foreground">
            {field.help}
        </p>
    ) : null;
    const errorMessage = <InputError id={`${id}-error`} message={error} />;
    const text = typeof value === 'string' ? value : '';

    switch (field.type) {
        case 'text':
            return (
                <div className="grid gap-1.5">
                    <Label htmlFor={id}>{field.label}</Label>
                    <Input
                        id={id}
                        value={text}
                        maxLength={field.max_length}
                        aria-invalid={Boolean(error)}
                        aria-describedby={describedBy}
                        onChange={(event) => onChange(event.target.value)}
                    />
                    {help}
                    {errorMessage}
                </div>
            );
        case 'textarea':
            return (
                <div className="grid gap-1.5">
                    <Label htmlFor={id}>{field.label}</Label>
                    <textarea
                        id={id}
                        value={text}
                        rows={3}
                        maxLength={field.max_length}
                        aria-invalid={Boolean(error)}
                        aria-describedby={describedBy}
                        onChange={(event) => onChange(event.target.value)}
                        className="min-h-20 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-invalid:border-destructive"
                    />
                    {help}
                    {errorMessage}
                </div>
            );
        case 'boolean':
            return (
                <div className="grid gap-1.5">
                    <div className="flex items-center gap-2">
                        <Checkbox
                            id={id}
                            checked={value === true}
                            aria-describedby={describedBy}
                            onCheckedChange={(checked) =>
                                onChange(checked === true)
                            }
                        />
                        <Label htmlFor={id}>{field.label}</Label>
                    </div>
                    {help}
                    {errorMessage}
                </div>
            );
        case 'select':
            return (
                <div className="grid gap-1.5">
                    <Label htmlFor={id}>{field.label}</Label>
                    <select
                        id={id}
                        value={text}
                        aria-invalid={Boolean(error)}
                        aria-describedby={describedBy}
                        onChange={(event) =>
                            onChange(event.target.value || null)
                        }
                        className="h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    >
                        {!field.required && (
                            <option value="">Не выбрано</option>
                        )}
                        {field.options?.map((option) => (
                            <option key={option.value} value={option.value}>
                                {option.label}
                            </option>
                        ))}
                    </select>
                    {help}
                    {errorMessage}
                </div>
            );
        case 'group':
            return (
                <fieldset className="grid gap-3 rounded-md border p-3">
                    <legend className="px-1 text-sm font-medium">
                        {field.label}
                    </legend>
                    <FieldList
                        fields={field.fields ?? []}
                        value={isState(value) ? value : {}}
                        path={path}
                        errors={errors}
                        onChange={onChange}
                    />
                    {errorMessage}
                </fieldset>
            );
        case 'image':
            return (
                <ImageControl
                    field={field}
                    value={value}
                    id={id}
                    error={error}
                    onChange={onChange}
                />
            );
        case 'action':
            return (
                <ActionControl
                    field={field}
                    value={value}
                    path={path}
                    id={id}
                    errors={errors}
                    onChange={onChange}
                />
            );
        case 'repeater':
            return (
                <RepeaterControl
                    field={field}
                    value={value}
                    path={path}
                    errors={errors}
                    onChange={onChange}
                />
            );
        default:
            return null;
    }
}

export function isState(value: unknown): value is BlockState {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
}
