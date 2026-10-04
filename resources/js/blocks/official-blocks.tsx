import type { ReactNode } from 'react';
import { actionHref, actionPopupId } from '@/blocks/actions';
import { useButtonStyle } from '@/blocks/design';
import { useBlockRenderContext } from '@/blocks/render-context';
import { useTriggerContext } from '@/blocks/trigger-context';
import type { BlockRendererProps, BlockState } from '@/blocks/state';
import { flag, group, items, text } from '@/blocks/state';
import { cn } from '@/lib/utils';

export function Container({
    children,
    className,
}: {
    children: ReactNode;
    className?: string;
}) {
    return (
        <div
            className={cn(
                'mx-auto w-full max-w-(--lf-container) px-6',
                className,
            )}
        >
            {children}
        </div>
    );
}

function ActionLink({
    action,
    className,
    children,
}: {
    action: unknown;
    className?: string;
    children: ReactNode;
}) {
    const context = useBlockRenderContext();
    const trigger = useTriggerContext();
    const href = actionHref(action, context);
    const popupId = actionPopupId(action);
    const open = context.openPopup;

    if (popupId !== null && open !== null && context.hasPopup(popupId)) {
        return (
            <button
                type="button"
                aria-haspopup="dialog"
                className={className}
                onClick={(event) => open(popupId, trigger, event.currentTarget)}
            >
                {children}
            </button>
        );
    }

    return href ? (
        <a
            href={href}
            className={className}
            {...(href.startsWith('http')
                ? { target: '_blank', rel: 'noopener noreferrer' }
                : {})}
        >
            {children}
        </a>
    ) : (
        <span className={className}>{children}</span>
    );
}

export function ButtonPreview({
    button,
    variant = 'primary',
}: {
    button: BlockState;
    variant?: 'primary' | 'secondary' | 'inverted';
}) {
    const outline = useButtonStyle() === 'outline';
    const label = text(button, 'label');

    if (label === null) {
        return null;
    }

    return (
        <ActionLink
            action={button.action}
            className={cn(
                'inline-flex items-center rounded-(--lf-radius) border-2 px-5 py-2 text-sm font-medium',
                variant === 'primary' &&
                    (outline
                        ? 'border-(--lf-primary) text-(--lf-primary)'
                        : 'border-(--lf-primary) bg-(--lf-primary) text-(--lf-on-primary)'),
                variant === 'secondary' &&
                    'border-neutral-300 bg-white text-neutral-900',
                variant === 'inverted' &&
                    (outline
                        ? 'border-(--lf-on-primary) text-(--lf-on-primary)'
                        : 'border-(--lf-on-primary) bg-(--lf-on-primary) text-(--lf-primary)'),
            )}
        >
            {label}
        </ActionLink>
    );
}

function useImage(state: BlockState, key: string): string | null {
    const { assetUrl } = useBlockRenderContext();
    const id = text(state, key);

    return id === null ? null : assetUrl(id);
}

export function HeaderBlock({ state }: BlockRendererProps) {
    const phone = flag(state, 'show_phone', true) ? text(state, 'phone') : null;
    const menu = items(state, 'menu');
    const logo = useImage(state, 'logo');

    return (
        <header className="border-b border-neutral-200 bg-white text-neutral-900">
            <Container className="flex flex-wrap items-center gap-x-8 gap-y-3 py-4">
                <span className="flex items-center gap-3 text-lg font-semibold">
                    {logo && (
                        <img
                            src={logo}
                            alt={text(state, 'logo_text') ? '' : 'Логотип'}
                            className="h-10 w-auto max-w-40 object-contain"
                        />
                    )}
                    {text(state, 'logo_text')}
                </span>
                {menu.length > 0 && (
                    <nav aria-label="Меню сайта">
                        <ul className="flex flex-wrap gap-x-6 gap-y-2 text-sm">
                            {menu.map((item) => (
                                <li key={item.id}>
                                    <ActionLink
                                        action={item.action}
                                        className="hover:underline"
                                    >
                                        {text(item, 'label')}
                                    </ActionLink>
                                </li>
                            ))}
                        </ul>
                    </nav>
                )}
                <div className="ml-auto flex items-center gap-4">
                    {phone && (
                        <span className="text-sm font-medium">{phone}</span>
                    )}
                    <ButtonPreview button={group(state, 'button')} />
                </div>
            </Container>
        </header>
    );
}

export function HeroBlock({ state }: BlockRendererProps) {
    const centered = state.align === 'center';
    const image = useImage(state, 'image');

    return (
        <section
            className={cn(
                'relative isolate overflow-hidden py-20',
                image
                    ? 'bg-neutral-900 text-white'
                    : 'bg-neutral-100 text-neutral-900',
            )}
        >
            {image && (
                <>
                    <img
                        src={image}
                        alt=""
                        className="absolute inset-0 -z-10 size-full object-cover"
                    />
                    <div className="absolute inset-0 -z-10 bg-black/55" />
                </>
            )}
            <Container
                className={cn(
                    'flex flex-col gap-5',
                    centered && 'items-center text-center',
                )}
            >
                {text(state, 'eyebrow') && (
                    <p
                        className={cn(
                            'text-sm font-medium tracking-wide uppercase',
                            image ? 'text-white/80' : 'text-(--lf-secondary)',
                        )}
                    >
                        {text(state, 'eyebrow')}
                    </p>
                )}
                <h2 className="max-w-3xl text-4xl font-bold break-words sm:text-5xl">
                    {text(state, 'title')}
                </h2>
                {text(state, 'subtitle') && (
                    <p
                        className={cn(
                            'max-w-2xl text-lg whitespace-pre-line',
                            image ? 'text-white/85' : 'text-neutral-600',
                        )}
                    >
                        {text(state, 'subtitle')}
                    </p>
                )}
                <div className="flex flex-wrap gap-3">
                    <ButtonPreview button={group(state, 'primary_button')} />
                    <ButtonPreview
                        button={group(state, 'secondary_button')}
                        variant="secondary"
                    />
                </div>
            </Container>
        </section>
    );
}

const benefitColumns: Record<string, string> = {
    two: 'sm:grid-cols-2',
    three: 'sm:grid-cols-2 lg:grid-cols-3',
    four: 'sm:grid-cols-2 lg:grid-cols-4',
};

export function BenefitsBlock({ state }: BlockRendererProps) {
    const columns =
        typeof state.columns === 'string' && state.columns in benefitColumns
            ? benefitColumns[state.columns]
            : benefitColumns.three;

    return (
        <section className="bg-white py-16 text-neutral-900">
            <Container className="flex flex-col gap-8">
                <div className="flex flex-col gap-3">
                    <h2 className="text-3xl font-bold">
                        {text(state, 'title')}
                    </h2>
                    {text(state, 'subtitle') && (
                        <p className="max-w-2xl whitespace-pre-line text-neutral-600">
                            {text(state, 'subtitle')}
                        </p>
                    )}
                </div>
                <ul className={cn('grid gap-6', columns)}>
                    {items(state, 'items').map((item) => (
                        <li
                            key={item.id}
                            className="rounded-(--lf-radius) border border-t-4 border-neutral-200 border-t-(--lf-secondary) p-5"
                        >
                            <h3 className="font-semibold">
                                {text(item, 'title')}
                            </h3>
                            {text(item, 'text') && (
                                <p className="mt-2 text-sm whitespace-pre-line text-neutral-600">
                                    {text(item, 'text')}
                                </p>
                            )}
                        </li>
                    ))}
                </ul>
            </Container>
        </section>
    );
}

export function CtaBlock({ state }: BlockRendererProps) {
    const muted = state.style === 'muted';

    return (
        <section
            className={cn(
                'py-16',
                muted
                    ? 'bg-neutral-100 text-neutral-900'
                    : 'bg-(--lf-primary) text-(--lf-on-primary)',
            )}
        >
            <Container className="flex flex-col items-start gap-4">
                <h2 className="text-3xl font-bold">{text(state, 'title')}</h2>
                {text(state, 'text') && (
                    <p
                        className={cn(
                            'max-w-2xl whitespace-pre-line',
                            muted ? 'text-neutral-600' : 'opacity-85',
                        )}
                    >
                        {text(state, 'text')}
                    </p>
                )}
                <ButtonPreview
                    button={group(state, 'button')}
                    variant={muted ? 'primary' : 'inverted'}
                />
            </Container>
        </section>
    );
}

export function ContactsBlock({ state }: BlockRendererProps) {
    const rows = [
        { label: 'Адрес', value: text(state, 'address') },
        { label: 'Телефон', value: text(state, 'phone') },
        { label: 'Email', value: text(state, 'email') },
        { label: 'Режим работы', value: text(state, 'working_hours') },
    ].filter((row) => row.value !== null);

    return (
        <section className="bg-white py-16 text-neutral-900">
            <Container className="flex flex-col gap-6">
                <h2 className="text-3xl font-bold">{text(state, 'title')}</h2>
                {rows.length > 0 && (
                    <dl className="grid gap-6 sm:grid-cols-2">
                        {rows.map((row) => (
                            <div key={row.label}>
                                <dt className="text-sm text-neutral-500">
                                    {row.label}
                                </dt>
                                <dd className="mt-1 break-words whitespace-pre-line">
                                    {row.value}
                                </dd>
                            </div>
                        ))}
                    </dl>
                )}
            </Container>
        </section>
    );
}

export function FooterBlock({ state }: BlockRendererProps) {
    const links = items(state, 'links');

    return (
        <footer className="bg-neutral-950 py-10 text-sm text-neutral-300">
            <Container className="flex flex-col gap-4">
                {text(state, 'company_name') && (
                    <p className="text-base font-semibold text-white">
                        {text(state, 'company_name')}
                    </p>
                )}
                {text(state, 'text') && (
                    <p className="whitespace-pre-line">{text(state, 'text')}</p>
                )}
                {links.length > 0 && (
                    <ul className="flex flex-wrap gap-x-6 gap-y-2">
                        {links.map((link) => (
                            <li key={link.id}>
                                <ActionLink
                                    action={link.action}
                                    className="hover:text-white hover:underline"
                                >
                                    {text(link, 'label')}
                                </ActionLink>
                            </li>
                        ))}
                    </ul>
                )}
                {text(state, 'legal_notice') && (
                    <p className="text-xs whitespace-pre-line text-neutral-500">
                        {text(state, 'legal_notice')}
                    </p>
                )}
                {text(state, 'copyright') && (
                    <p className="text-xs text-neutral-500">
                        {text(state, 'copyright')}
                    </p>
                )}
            </Container>
        </footer>
    );
}
