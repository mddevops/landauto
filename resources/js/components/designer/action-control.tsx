import { actionTypes, isActionType } from '@/blocks/actions';
import type { SchemaField } from '@/blocks/schema';
import { useDesignerContext } from '@/components/designer/designer-context';
import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

const selectClass =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50';

const textTargets = {
    url: { label: 'Адрес ссылки', type: 'url', placeholder: 'https://' },
    phone: { label: 'Телефон', type: 'tel', placeholder: '+7 900 000-00-00' },
    email: { label: 'Email', type: 'email', placeholder: 'sales@example.ru' },
} as const;

export function ActionControl({
    field,
    value,
    path,
    id,
    errors,
    onChange,
}: {
    field: SchemaField;
    value: unknown;
    path: string;
    id: string;
    errors: Record<string, string>;
    onChange: (value: Record<string, string | null> | null) => void;
}) {
    const { pages, blocks, popups } = useDesignerContext();
    const action =
        typeof value === 'object' && value !== null
            ? (value as Record<string, unknown>)
            : null;
    const type = isActionType(action?.type) ? action.type : null;
    const targetKey = type ? actionTypes[type].target : null;
    const target =
        targetKey && typeof action?.[targetKey] === 'string'
            ? (action[targetKey] as string)
            : '';
    const targetId = `${id}-target`;
    const targetError = targetKey ? errors[`${path}.${targetKey}`] : undefined;
    const setTarget = (next: string) =>
        type && targetKey && onChange({ type, [targetKey]: next || null });

    return (
        <fieldset className="grid gap-2 rounded-md border p-3">
            <legend className="px-1 text-sm font-medium">{field.label}</legend>
            <Label htmlFor={id} className="sr-only">
                {`${field.label}: тип`}
            </Label>
            <select
                id={id}
                value={type ?? ''}
                aria-invalid={Boolean(errors[`${path}.type`] ?? errors[path])}
                onChange={(event) => {
                    const next = event.target.value;
                    onChange(
                        isActionType(next)
                            ? { type: next, [actionTypes[next].target]: null }
                            : null,
                    );
                }}
                className={selectClass}
            >
                <option value="">Без действия</option>
                {Object.entries(actionTypes).map(([key, option]) => (
                    <option key={key} value={key}>
                        {option.label}
                    </option>
                ))}
            </select>
            <InputError message={errors[`${path}.type`] ?? errors[path]} />

            {(targetKey === 'url' ||
                targetKey === 'phone' ||
                targetKey === 'email') && (
                <div className="grid gap-1.5">
                    <Label htmlFor={targetId}>
                        {textTargets[targetKey].label}
                    </Label>
                    <Input
                        id={targetId}
                        type={textTargets[targetKey].type}
                        value={target}
                        placeholder={textTargets[targetKey].placeholder}
                        maxLength={targetKey === 'url' ? 2048 : 254}
                        aria-invalid={Boolean(targetError)}
                        aria-describedby={
                            targetError ? `${targetId}-error` : undefined
                        }
                        onChange={(event) => setTarget(event.target.value)}
                    />
                </div>
            )}
            {targetKey === 'page' && (
                <div className="grid gap-1.5">
                    <Label htmlFor={targetId}>Страница</Label>
                    <select
                        id={targetId}
                        value={target}
                        aria-invalid={Boolean(targetError)}
                        onChange={(event) => setTarget(event.target.value)}
                        className={selectClass}
                    >
                        <option value="">Выберите страницу</option>
                        {pages.map((page) => (
                            <option key={page.public_id} value={page.public_id}>
                                {page.title}
                            </option>
                        ))}
                    </select>
                </div>
            )}
            {targetKey === 'block' && (
                <div className="grid gap-1.5">
                    <Label htmlFor={targetId}>Блок на странице</Label>
                    <select
                        id={targetId}
                        value={target}
                        aria-invalid={Boolean(targetError)}
                        onChange={(event) => setTarget(event.target.value)}
                        className={selectClass}
                    >
                        <option value="">Выберите блок</option>
                        {blocks.map((block, index) => (
                            <option
                                key={block.public_id}
                                value={block.public_id}
                            >
                                {`${index + 1}. ${block.name}`}
                            </option>
                        ))}
                    </select>
                </div>
            )}
            {targetKey === 'popup' && (
                <div className="grid gap-1.5">
                    <Label htmlFor={targetId}>Попап</Label>
                    <select
                        id={targetId}
                        value={target}
                        aria-invalid={Boolean(targetError)}
                        onChange={(event) => setTarget(event.target.value)}
                        className={selectClass}
                    >
                        <option value="">Выберите попап</option>
                        {popups.map((popup) => (
                            <option
                                key={popup.public_id}
                                value={popup.public_id}
                            >
                                {popup.name}
                            </option>
                        ))}
                    </select>
                    {popups.length === 0 && (
                        <p className="text-xs text-muted-foreground">
                            Активных попапов нет. Создайте попап в разделе
                            «Попапы».
                        </p>
                    )}
                </div>
            )}
            <InputError id={`${targetId}-error`} message={targetError} />
        </fieldset>
    );
}
