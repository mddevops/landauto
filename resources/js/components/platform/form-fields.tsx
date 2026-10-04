import type { ComponentProps, ReactNode } from 'react';
import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

export type Choice = { value: string; label: string };

export function NativeSelect({
    className,
    ...props
}: ComponentProps<'select'>) {
    return (
        <select
            className={cn(
                'h-9 w-full min-w-0 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-invalid:border-destructive dark:bg-input/30',
                className,
            )}
            {...props}
        />
    );
}

type FieldProps = {
    id: string;
    label: string;
    error?: string;
    hint?: string;
    children: ReactNode;
};

export function Field({ id, label, error, hint, children }: FieldProps) {
    return (
        <div className="grid gap-1.5">
            <Label htmlFor={id}>{label}</Label>
            {children}
            {hint && <p className="text-xs text-muted-foreground">{hint}</p>}
            <InputError id={`${id}-error`} message={error} />
        </div>
    );
}

type TextFieldProps = Omit<ComponentProps<typeof Input>, 'id'> & {
    id: string;
    label: string;
    error?: string;
    hint?: string;
};

export function TextField({
    id,
    label,
    error,
    hint,
    ...props
}: TextFieldProps) {
    return (
        <Field id={id} label={label} error={error} hint={hint}>
            <Input
                id={id}
                aria-invalid={Boolean(error)}
                aria-describedby={error ? `${id}-error` : undefined}
                {...props}
            />
        </Field>
    );
}

type SelectFieldProps = Omit<ComponentProps<'select'>, 'id'> & {
    id: string;
    label: string;
    choices: Choice[];
    error?: string;
    emptyLabel?: string;
};

export function SelectField({
    id,
    label,
    choices,
    error,
    emptyLabel,
    ...props
}: SelectFieldProps) {
    return (
        <Field id={id} label={label} error={error}>
            <NativeSelect
                id={id}
                aria-invalid={Boolean(error)}
                aria-describedby={error ? `${id}-error` : undefined}
                {...props}
            >
                {emptyLabel !== undefined && (
                    <option value="">{emptyLabel}</option>
                )}
                {choices.map((choice) => (
                    <option key={choice.value} value={choice.value}>
                        {choice.label}
                    </option>
                ))}
            </NativeSelect>
        </Field>
    );
}

export const statusChoices: Choice[] = [
    { value: '1', label: 'Активна' },
    { value: '0', label: 'Выключена' },
];
