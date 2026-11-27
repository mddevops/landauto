import { Head, Link } from '@inertiajs/react';
import { Phone, SquareStack } from 'lucide-react';
import { useState } from 'react';
import type { DesignTokens } from '@/blocks/design';
import type { Choice } from '@/components/platform/form-fields';
import type { ManagedPopup } from '@/components/popups/popup-settings-dialog';
import { Button } from '@/components/ui/button';
import { ButtonStudio } from '@/components/widget/button-studio';
import { PopupStudio } from '@/components/widget/popup-studio';
import { dashboard } from '@/routes';
import { designer } from '@/routes/sites';
import type { FloatingLauncher } from '@/popups/launcher';

type WidgetIndexProps = {
    site: { public_id: string; name: string };
    design: DesignTokens;
    launcher: FloatingLauncher;
    popups: ManagedPopup[];
    forms: Choice[];
    choices: {
        sizes: Choice[];
        animations: Choice[];
    };
    can: { editPopups: boolean };
};

type Screen =
    | { kind: 'home' }
    | { kind: 'button' }
    | { kind: 'popup'; popup?: ManagedPopup };

export default function WidgetIndex({
    site,
    launcher,
    popups,
    forms,
    can,
}: WidgetIndexProps) {
    const [screen, setScreen] = useState<Screen>({ kind: 'home' });
    const home = () => setScreen({ kind: 'home' });

    if (can.editPopups && screen.kind === 'button') {
        return (
            <>
                <Head title={`Кнопка — ${site.name}`} />
                <ButtonStudio
                    sitePublicId={site.public_id}
                    launcher={launcher}
                    popups={popups}
                    onBack={home}
                />
            </>
        );
    }

    if (can.editPopups && screen.kind === 'popup') {
        return (
            <>
                <Head title={`Попап — ${site.name}`} />
                <PopupStudio
                    sitePublicId={site.public_id}
                    popup={screen.popup}
                    forms={forms}
                    onBack={home}
                />
            </>
        );
    }

    return (
        <>
            <Head title={`Виджет — ${site.name}`} />
            <main className="flex min-w-0 flex-1 flex-col gap-6 p-4 sm:p-6">
                <header className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div className="min-w-0 space-y-1">
                        <p className="truncate text-sm text-muted-foreground">
                            {site.name}
                        </p>
                        <h1 className="text-2xl font-semibold tracking-tight sm:text-3xl">
                            Виджет
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Кнопка на сайте и окно, которое она открывает.
                        </p>
                    </div>
                    <Button asChild variant="outline">
                        <Link href={designer(site.public_id)}>
                            Открыть дизайнер
                        </Link>
                    </Button>
                </header>

                <div className="grid gap-4 sm:grid-cols-2">
                    <button
                        type="button"
                        className="rounded-xl border bg-card p-5 text-left shadow-sm hover:border-neutral-400"
                        onClick={() =>
                            can.editPopups && setScreen({ kind: 'button' })
                        }
                        disabled={!can.editPopups}
                    >
                        <Phone className="size-5 text-[#3aa0c8]" aria-hidden />
                        <h2 className="mt-3 font-semibold">
                            {launcher.enabled
                                ? 'Изменить кнопку'
                                : 'Создать кнопку'}
                        </h2>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Круглая кнопка на десктопе и на телефоне: цвет,
                            положение и попап, который она открывает.
                        </p>
                    </button>
                    <button
                        type="button"
                        className="rounded-xl border bg-card p-5 text-left shadow-sm hover:border-neutral-400"
                        onClick={() =>
                            can.editPopups && setScreen({ kind: 'popup' })
                        }
                        disabled={!can.editPopups}
                    >
                        <SquareStack
                            className="size-5 text-[#3aa0c8]"
                            aria-hidden
                        />
                        <h2 className="mt-3 font-semibold">Создать попап</h2>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Окно заявки, подтверждение и условия показа.
                        </p>
                    </button>
                </div>

                {popups.length > 0 && (
                    <ul className="grid gap-3">
                        {popups.map((popup) => (
                            <li key={popup.public_id}>
                                <button
                                    type="button"
                                    className="flex w-full items-center justify-between rounded-xl border bg-card px-4 py-3 text-left"
                                    onClick={() =>
                                        can.editPopups &&
                                        setScreen({ kind: 'popup', popup })
                                    }
                                    disabled={!can.editPopups}
                                >
                                    <span>
                                        <span className="font-medium">
                                            {popup.name}
                                        </span>
                                        <span className="mt-0.5 block text-sm text-muted-foreground">
                                            {popup.title ?? 'Без заголовка'}
                                            {popup.form_public_id
                                                ? ` · Форма: ${
                                                      forms.find(
                                                          (form) =>
                                                              form.value ===
                                                              popup.form_public_id,
                                                      )?.label ?? ''
                                                  }`
                                                : ''}
                                        </span>
                                    </span>
                                    <span className="text-sm text-[#3aa0c8]">
                                        Изменить
                                    </span>
                                </button>
                            </li>
                        ))}
                    </ul>
                )}
            </main>
        </>
    );
}

WidgetIndex.layout = {
    breadcrumbs: [{ title: 'Все сайты', href: dashboard() }],
};
