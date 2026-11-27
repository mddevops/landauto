import {
    Check,
    ChevronDown,
    Lock,
    LockOpen,
    Pencil,
    Trash2,
    X,
} from 'lucide-react';
import {
    Children,
    isValidElement,
    useEffect,
    useId,
    useRef,
    useState,
} from 'react';
import type {
    ButtonHTMLAttributes,
    InputHTMLAttributes,
    ReactElement,
    ReactNode,
} from 'react';
import { cn } from '@/lib/utils';

/**
 * Измеренные цвета редактора виджета. Подробности в docs/product/WIDGET_EDITOR.md.
 */
export const ct = {
    page: '#E7DFD5',
    line: '#CABCAA',
    segment: '#DCD3C7',
    muted: '#746A5D',
    dark: '#3F3932',
    accent: '#3DAFCC',
    accentOff: '#9ED7E5',
    text: '#1F282C',
    gray: '#8F9496',
    divider: '#EEEEEE',
    group: '#F0F0F0',
    disabled: '#B1B3B5',
    shadow: '#CFC8BF',
    footer: '#444B4C',
    brown: '#746A5D',
    selection: '#FF0000',
} as const;

export const fieldBox =
    'h-8 w-full border border-[#8F9496] bg-white px-2 text-sm text-[#1F282C] outline-none focus:border-[#3DAFCC] disabled:bg-[#F0F0F0] disabled:text-[#8F9496]';

export function CtLabel({
    children,
    htmlFor,
    muted = false,
    help,
    className,
}: {
    children: ReactNode;
    htmlFor?: string;
    muted?: boolean;
    help?: boolean;
    className?: string;
}) {
    return (
        <label
            htmlFor={htmlFor}
            className={cn(
                'flex items-center gap-1.5 text-sm leading-[18px]',
                muted ? 'text-[#746A5D]' : 'text-[#1F282C]',
                className,
            )}
        >
            {children}
            {help && <CtHelp />}
        </label>
    );
}

export function CtHelp() {
    return (
        <span
            aria-hidden="true"
            className="inline-flex size-3.5 items-center justify-center rounded-full bg-[#D4D4D4] text-[10px] leading-none font-bold text-white"
        >
            ?
        </span>
    );
}

export function CtField({
    label,
    htmlFor,
    help,
    children,
    className,
}: {
    label?: ReactNode;
    htmlFor?: string;
    help?: boolean;
    children: ReactNode;
    className?: string;
}) {
    return (
        <div className={cn('grid gap-1.5', className)}>
            {label && (
                <CtLabel htmlFor={htmlFor} help={help}>
                    {label}
                </CtLabel>
            )}
            {children}
        </div>
    );
}

export type CtOption = {
    value: string;
    label: string;
    icon?: ReactNode;
    disabled?: boolean;
};

export function CtSelect({
    id,
    value,
    options,
    onChange,
    placeholder = 'Выберите',
    ariaLabel,
    className,
    disabled = false,
}: {
    id?: string;
    value: string;
    options: CtOption[];
    onChange: (value: string) => void;
    placeholder?: string;
    ariaLabel?: string;
    className?: string;
    disabled?: boolean;
}) {
    const [open, setOpen] = useState(false);
    const root = useRef<HTMLDivElement>(null);
    const generated = useId();
    const listId = `${id ?? generated}-list`;
    const current = options.find((option) => option.value === value);

    useEffect(() => {
        if (!open) {
            return;
        }

        function close(event: MouseEvent) {
            if (!root.current?.contains(event.target as Node)) {
                setOpen(false);
            }
        }

        document.addEventListener('mousedown', close);

        return () => document.removeEventListener('mousedown', close);
    }, [open]);

    return (
        <div ref={root} className={cn('relative', className)}>
            <button
                type="button"
                id={id}
                role="combobox"
                aria-haspopup="listbox"
                aria-expanded={open}
                aria-controls={listId}
                aria-label={ariaLabel}
                disabled={disabled}
                className={cn(
                    fieldBox,
                    'flex items-center gap-2 text-left',
                    open && 'border-[#3DAFCC]',
                )}
                onClick={() => setOpen((state) => !state)}
                onKeyDown={(event) => {
                    if (event.key === 'Escape') {
                        setOpen(false);
                    }
                }}
            >
                {current?.icon}
                <span
                    className={cn(
                        'min-w-0 flex-1 truncate',
                        !current && 'text-[#8F9496]',
                    )}
                >
                    {current?.label ?? placeholder}
                </span>
                <ChevronDown
                    aria-hidden="true"
                    className={cn(
                        'size-4 shrink-0 text-[#1F282C] transition-transform',
                        open && 'rotate-180',
                    )}
                />
            </button>
            {open && (
                <ul
                    id={listId}
                    role="listbox"
                    className="absolute top-full right-0 left-0 z-30 max-h-60 overflow-y-auto border border-[#8F9496] bg-white py-1 shadow-md"
                >
                    {options.map((option) => (
                        <li
                            key={option.value}
                            role="option"
                            aria-selected={option.value === value}
                            aria-disabled={option.disabled}
                            className={cn(
                                'flex h-8 cursor-pointer items-center gap-2 px-2 text-sm text-[#1F282C] hover:bg-[#FFF6DD]',
                                option.disabled &&
                                    'cursor-not-allowed text-[#8F9496] hover:bg-transparent',
                            )}
                            onClick={() => {
                                if (option.disabled) {
                                    return;
                                }

                                onChange(option.value);
                                setOpen(false);
                            }}
                        >
                            {option.icon}
                            <span className="min-w-0 flex-1 truncate">
                                {option.label}
                            </span>
                            {option.value === value && (
                                <Check
                                    aria-hidden="true"
                                    className="size-4 shrink-0 text-[#3DAFCC]"
                                />
                            )}
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}

/**
 * Список <option> превращается в CtSelect, чтобы старые экраны не менялись по структуре.
 */
export function LabeledSelect({
    label,
    value,
    onChange,
    children,
    id,
    help,
    className,
}: {
    label: string;
    value: string;
    onChange: (value: string) => void;
    children: ReactNode;
    id?: string;
    help?: boolean;
    className?: string;
}) {
    const generated = useId();
    const controlId = id ?? generated;
    const options = Children.toArray(children)
        .filter(
            (
                child,
            ): child is ReactElement<{ value: string; children: ReactNode }> =>
                isValidElement(child),
        )
        .map((child) => ({
            value: String(child.props.value ?? ''),
            label: Children.toArray(child.props.children)
                .filter(
                    (part) =>
                        typeof part === 'string' || typeof part === 'number',
                )
                .join(''),
        }));

    return (
        <CtField
            label={label}
            htmlFor={controlId}
            help={help}
            className={className}
        >
            <CtSelect
                id={controlId}
                value={value}
                options={options}
                onChange={onChange}
                ariaLabel={label}
            />
        </CtField>
    );
}

export function CtNumber({
    id,
    value,
    onChange,
    min = 0,
    max = 100,
    suffix,
    ariaLabel,
    disabled = false,
    slider = true,
    className,
    badge,
    onBadge,
}: {
    id?: string;
    value: number;
    onChange: (value: number) => void;
    min?: number;
    max?: number;
    suffix?: string;
    ariaLabel?: string;
    disabled?: boolean;
    slider?: boolean;
    className?: string;
    badge?: string;
    onBadge?: () => void;
}) {
    const span = Math.max(1, max - min);
    const percent = Math.min(100, Math.max(0, ((value - min) / span) * 100));

    return (
        <div className={cn('relative', className)}>
            <input
                id={id}
                type="number"
                inputMode="numeric"
                min={min}
                max={max}
                aria-label={ariaLabel}
                disabled={disabled}
                value={Number.isFinite(value) ? value : 0}
                className={cn(
                    fieldBox,
                    '[appearance:textfield] pr-10 [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none',
                )}
                onChange={(event) => {
                    const next = Number(event.target.value);

                    onChange(
                        Number.isFinite(next)
                            ? Math.min(max, Math.max(min, next))
                            : min,
                    );
                }}
            />
            {suffix && !badge && (
                <span className="pointer-events-none absolute top-1/2 right-2 -translate-y-1/2 text-sm text-[#8F9496]">
                    {suffix}
                </span>
            )}
            {badge && (
                <button
                    type="button"
                    className="absolute top-1/2 right-1.5 -translate-y-1/2 rounded-sm bg-[#3DAFCC] px-1.5 py-0.5 text-xs text-white"
                    onClick={onBadge}
                >
                    {badge}
                </button>
            )}
            {slider && !disabled && (
                <>
                    <span
                        aria-hidden="true"
                        className="pointer-events-none absolute bottom-0 left-0 h-0.5 bg-[#3DAFCC]"
                        style={{ width: `${percent}%` }}
                    />
                    <span
                        aria-hidden="true"
                        className="pointer-events-none absolute bottom-0 size-2 -translate-x-1/2 translate-y-1/2 rounded-full bg-[#3DAFCC]"
                        style={{ left: `${percent}%` }}
                    />
                    <input
                        type="range"
                        tabIndex={-1}
                        aria-hidden="true"
                        min={min}
                        max={max}
                        value={Math.min(max, Math.max(min, value))}
                        className="absolute -bottom-1.5 left-0 h-3 w-full cursor-pointer opacity-0"
                        onChange={(event) =>
                            onChange(Number(event.target.value))
                        }
                    />
                </>
            )}
        </div>
    );
}

export function NumberField({
    label,
    value,
    onChange,
    min = 0,
    max = 100,
    suffix = 'px',
    badge,
    onBadge,
    help,
}: {
    label: string;
    value: number;
    onChange: (value: number) => void;
    min?: number;
    max?: number;
    suffix?: string;
    badge?: string;
    onBadge?: () => void;
    help?: boolean;
}) {
    const id = useId();

    return (
        <CtField label={label} htmlFor={id} help={help}>
            <CtNumber
                id={id}
                value={value}
                min={min}
                max={max}
                suffix={suffix}
                badge={badge}
                onBadge={onBadge}
                disabled={badge === 'Авто'}
                onChange={onChange}
            />
        </CtField>
    );
}

export function CtInput(props: InputHTMLAttributes<HTMLInputElement>) {
    return <input {...props} className={cn(fieldBox, props.className)} />;
}

export function CtTextarea({
    className,
    ...props
}: React.TextareaHTMLAttributes<HTMLTextAreaElement>) {
    return (
        <textarea
            {...props}
            className={cn(fieldBox, 'h-auto min-h-16 py-1.5', className)}
        />
    );
}

export function CtCheckbox({
    checked,
    onCheckedChange,
    disabled = false,
    label,
    hint,
    id,
    ariaLabel,
}: {
    checked: boolean;
    onCheckedChange?: (checked: boolean) => void;
    disabled?: boolean;
    label?: ReactNode;
    hint?: string;
    id?: string;
    ariaLabel?: string;
}) {
    const generated = useId();
    const controlId = id ?? generated;

    return (
        <div className="flex items-start gap-2">
            <button
                type="button"
                id={controlId}
                role="checkbox"
                aria-checked={checked}
                aria-label={ariaLabel}
                disabled={disabled}
                className={cn(
                    'mt-0.5 inline-flex size-3.5 shrink-0 items-center justify-center border',
                    checked && !disabled && 'border-[#3DAFCC] bg-[#3DAFCC]',
                    checked && disabled && 'border-[#B1B3B5] bg-[#FAFAFA]',
                    !checked && 'border-[#8F9496] bg-white',
                    !disabled && 'cursor-pointer',
                )}
                onClick={() => onCheckedChange?.(!checked)}
            >
                {checked && (
                    <Check
                        aria-hidden="true"
                        strokeWidth={3}
                        className={cn(
                            'size-2.5',
                            disabled ? 'text-[#B1B3B5]' : 'text-white',
                        )}
                    />
                )}
            </button>
            {(label || hint) && (
                <label
                    htmlFor={controlId}
                    className={cn(
                        'text-sm leading-[18px] text-[#1F282C]',
                        !disabled && 'cursor-pointer',
                    )}
                >
                    {label}
                    {hint && (
                        <span className="mt-0.5 block text-xs text-[#ADA7A0]">
                            {hint}
                        </span>
                    )}
                </label>
            )}
        </div>
    );
}

export function BlockToggle({
    label,
    checked,
    disabled,
    onChange,
    hint,
}: {
    label: string;
    checked: boolean;
    disabled?: boolean;
    onChange?: () => void;
    hint?: string;
}) {
    return (
        <CtCheckbox
            label={label}
            hint={hint}
            checked={checked}
            disabled={disabled}
            onCheckedChange={() => onChange?.()}
        />
    );
}

export function CtSwitch({
    checked,
    onChange,
    label,
}: {
    checked: boolean;
    onChange: (checked: boolean) => void;
    label: string;
}) {
    return (
        <button
            type="button"
            role="switch"
            aria-checked={checked}
            aria-label={label}
            className={cn(
                'relative h-5 w-9 shrink-0 rounded-full transition-colors',
                checked ? 'bg-[#3DAFCC]' : 'bg-[#CCC9C2]',
            )}
            onClick={() => onChange(!checked)}
        >
            <span
                className={cn(
                    'absolute top-0.5 size-4 rounded-full bg-white transition-all',
                    checked ? 'left-[18px]' : 'left-0.5',
                )}
            />
        </button>
    );
}

export function CtButton({
    tone = 'brown',
    icon,
    className,
    children,
    ...props
}: ButtonHTMLAttributes<HTMLButtonElement> & {
    tone?: 'brown' | 'sand' | 'accent' | 'ghost';
    icon?: ReactNode;
}) {
    return (
        <button
            type="button"
            {...props}
            className={cn(
                'inline-flex h-8 items-center gap-1.5 px-3 text-sm',
                tone === 'brown' &&
                    'bg-[#746A5D] text-white hover:bg-[#5C5449]',
                tone === 'sand' &&
                    'bg-[#E6DFD4] text-[#5C5449] hover:bg-[#DCD3C7]',
                tone === 'accent' &&
                    'bg-[#3DAFCC] text-white hover:bg-[#2D9CB8]',
                tone === 'ghost' && 'text-[#746A5D] hover:text-[#3F3932]',
                'disabled:cursor-not-allowed disabled:opacity-60',
                className,
            )}
        >
            {icon}
            {children}
        </button>
    );
}

export function CtColor({
    label,
    color,
    opacity,
    onColor,
    onOpacity,
    showOpacity = true,
}: {
    label: string;
    color: string;
    opacity?: number;
    onColor: (color: string) => void;
    onOpacity?: (opacity: number) => void;
    showOpacity?: boolean;
}) {
    const [draft, setDraft] = useState<string | null>(null);
    const hex = color.replace('#', '').toUpperCase();

    return (
        <div className="flex items-center gap-2">
            <label className="relative flex h-8 min-w-0 flex-1 items-center gap-1 border border-[#8F9496] bg-white pl-1 text-sm focus-within:border-[#3DAFCC]">
                <span
                    className="inline-block h-5 w-9 border border-black/10"
                    style={{ background: color }}
                />
                <input
                    type="color"
                    aria-label={label}
                    value={color}
                    className="absolute top-0 left-0 h-full w-12 cursor-pointer opacity-0"
                    onChange={(event) => onColor(event.target.value)}
                />
                <ChevronDown
                    aria-hidden="true"
                    className="size-3.5 shrink-0 text-[#8F9496]"
                />
                <span className="text-[#8F9496]">#</span>
                <input
                    aria-label={`${label}: HEX`}
                    value={draft ?? hex}
                    maxLength={6}
                    className="w-full min-w-0 bg-transparent text-[#1F282C] uppercase outline-none"
                    onChange={(event) => {
                        const value = event.target.value
                            .replace(/[^0-9a-fA-F]/g, '')
                            .toUpperCase();
                        setDraft(value);

                        if (value.length === 6) {
                            onColor(`#${value}`);
                        }
                    }}
                    onBlur={() => setDraft(null)}
                />
            </label>
            {showOpacity && opacity !== undefined && onOpacity && (
                <CtNumber
                    className="w-24 shrink-0"
                    value={opacity}
                    min={0}
                    max={100}
                    suffix="%"
                    ariaLabel={`${label}: непрозрачность`}
                    onChange={onOpacity}
                />
            )}
        </div>
    );
}

export function ColorRow({
    label,
    color,
    opacity,
    onColor,
    onOpacity,
}: {
    label: string;
    color: string;
    opacity: number;
    onColor: (color: string) => void;
    onOpacity: (opacity: number) => void;
}) {
    return (
        <CtField label={label}>
            <CtColor
                label={label}
                color={color}
                opacity={opacity}
                onColor={onColor}
                onOpacity={onOpacity}
            />
        </CtField>
    );
}

export function CtIconChoice<T extends string>({
    value,
    onChange,
    items,
    label,
}: {
    value: T;
    onChange: (value: T) => void;
    items: { value: T; label: string; icon: ReactNode }[];
    label: string;
}) {
    return (
        <div
            role="radiogroup"
            aria-label={label}
            className="flex h-9 items-center justify-between bg-[#F0F0F0] px-2"
        >
            {items.map((item) => (
                <button
                    key={item.value}
                    type="button"
                    role="radio"
                    aria-checked={value === item.value}
                    aria-label={item.label}
                    title={item.label}
                    className={cn(
                        'inline-flex size-7 items-center justify-center',
                        value === item.value
                            ? 'text-[#3DAFCC]'
                            : 'text-[#8F9496] hover:text-[#3F3932]',
                    )}
                    onClick={() => onChange(item.value)}
                >
                    {item.icon}
                </button>
            ))}
        </div>
    );
}

export function CornerIcon({
    corner,
}: {
    corner: 'top-left' | 'top-right' | 'bottom-left' | 'bottom-right';
}) {
    return (
        <svg viewBox="0 0 16 16" className="size-4" aria-hidden="true">
            <rect
                x="1.5"
                y="1.5"
                width="13"
                height="13"
                fill="none"
                stroke="currentColor"
                strokeWidth="1"
                opacity=".55"
            />
            <rect
                x={corner.endsWith('left') ? 1.5 : 7.5}
                y={corner.startsWith('top') ? 1.5 : 7.5}
                width="7"
                height="7"
                fill="currentColor"
            />
        </svg>
    );
}

export function RadiusIcon({ kind }: { kind: 'square' | 'soft' | 'round' }) {
    return (
        <svg viewBox="0 0 16 16" className="size-4" aria-hidden="true">
            <rect
                x="1.5"
                y="1.5"
                width="13"
                height="13"
                rx={kind === 'square' ? 0 : kind === 'soft' ? 3.5 : 6.5}
                fill="none"
                stroke="currentColor"
                strokeWidth="1.5"
            />
        </svg>
    );
}

export function CtSection({
    title,
    help,
    children,
    first = false,
    className,
}: {
    title?: string;
    help?: boolean;
    children: ReactNode;
    first?: boolean;
    className?: string;
}) {
    return (
        <section
            className={cn(
                'grid gap-4 py-5',
                !first && 'border-t border-[#EEEEEE]',
                className,
            )}
        >
            {title && (
                <h3 className="flex items-center gap-1.5 text-base font-semibold text-[#1F282C]">
                    {title}
                    {help && <CtHelp />}
                </h3>
            )}
            {children}
        </section>
    );
}

export function PanelHead({
    caption,
    title,
    switcher,
    onClose,
}: {
    caption: string;
    title: string;
    switcher?: ReactNode;
    onClose?: () => void;
}) {
    return (
        <div className="relative pb-3">
            <p className="text-[13px] leading-4 text-[#8F9496]">{caption}</p>
            <div className="mt-1 flex items-center">
                {switcher ?? (
                    <h2 className="text-base font-semibold text-[#1F282C]">
                        {title}
                    </h2>
                )}
                {onClose && (
                    <button
                        type="button"
                        aria-label="Закрыть настройки элемента"
                        className="ml-auto text-[#1F282C] hover:text-[#3DAFCC]"
                        onClick={onClose}
                    >
                        <X className="size-5" />
                    </button>
                )}
            </div>
        </div>
    );
}

export function ElementSwitcher({
    value,
    items,
    onChange,
}: {
    value: string;
    items: { value: string; label: string }[];
    onChange: (value: string) => void;
}) {
    const [open, setOpen] = useState(false);
    const root = useRef<HTMLDivElement>(null);
    const current = items.find((item) => item.value === value);

    useEffect(() => {
        if (!open) {
            return;
        }

        function close(event: MouseEvent) {
            if (!root.current?.contains(event.target as Node)) {
                setOpen(false);
            }
        }

        document.addEventListener('mousedown', close);

        return () => document.removeEventListener('mousedown', close);
    }, [open]);

    return (
        <div ref={root} className="relative">
            <button
                type="button"
                aria-haspopup="listbox"
                aria-expanded={open}
                className="flex items-center gap-1 text-base font-semibold text-[#1F282C]"
                onClick={() => setOpen((state) => !state)}
            >
                {current?.label}
                <ChevronDown className="size-4" aria-hidden="true" />
            </button>
            {open && (
                <ul
                    role="listbox"
                    className="absolute top-full left-[-6px] z-30 mt-1 max-h-[360px] w-[calc(100%+80px)] min-w-64 overflow-y-auto bg-white py-1 text-sm font-normal shadow-lg"
                >
                    {items.map((item) => (
                        <li
                            key={item.value}
                            role="option"
                            aria-selected={item.value === value}
                            className={cn(
                                'cursor-pointer px-1.5 py-1.5 text-[#1F282C] hover:bg-[#FFF6DD]',
                                item.value === value && 'bg-[#FFF6DD]',
                            )}
                            onClick={() => {
                                onChange(item.value);
                                setOpen(false);
                            }}
                        >
                            {item.label}
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}

export function LockToggle({
    locked,
    onChange,
}: {
    locked: boolean;
    onChange: (locked: boolean) => void;
}) {
    const Icon = locked ? Lock : LockOpen;

    return (
        <button
            type="button"
            aria-pressed={locked}
            aria-label="Сохранять пропорции"
            className="mt-6 inline-flex size-6 items-center justify-center text-[#3DAFCC]"
            onClick={() => onChange(!locked)}
        >
            <Icon className="size-4" aria-hidden="true" />
        </button>
    );
}

export function IconGhost({
    label,
    kind,
    onClick,
}: {
    label: string;
    kind: 'pencil' | 'trash';
    onClick?: () => void;
}) {
    const Icon = kind === 'pencil' ? Pencil : Trash2;

    return (
        <button
            type="button"
            aria-label={label}
            className={cn(
                'inline-flex size-6 items-center justify-center',
                kind === 'pencil'
                    ? 'text-[#3DAFCC] hover:text-[#2D9CB8]'
                    : 'text-[#C7C9CA] hover:text-[#8F9496]',
            )}
            onClick={onClick}
        >
            <Icon className="size-4" aria-hidden="true" />
        </button>
    );
}
