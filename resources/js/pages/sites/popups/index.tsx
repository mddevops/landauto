import { Head, Link } from '@inertiajs/react';
import { Eye, PanelsTopLeft, Pencil, Plus } from 'lucide-react';
import { useState } from 'react';
import type { DesignTokens } from '@/blocks/design';
import { FormView } from '@/blocks/form';
import { PopupView } from '@/blocks/popup';
import type { Choice } from '@/components/platform/form-fields';
import type {
    ManagedPopup,
    PopupChoices,
} from '@/components/popups/popup-settings-dialog';
import { PopupSettingsDialog } from '@/components/popups/popup-settings-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardDescription, CardHeader } from '@/components/ui/card';
import { dashboard } from '@/routes';
import { designer } from '@/routes/sites';

type PopupsIndexProps = {
    site: { public_id: string; name: string };
    design: DesignTokens;
    popups: ManagedPopup[];
    forms: Choice[];
    choices: PopupChoices;
    can: { editPopups: boolean };
};

export default function PopupsIndex({
    site,
    design,
    popups,
    forms,
    choices,
    can,
}: PopupsIndexProps) {
    const [previewId, setPreviewId] = useState<string | null>(null);
    const previewed = popups.find((popup) => popup.public_id === previewId);
    const sizeLabel = (value: string) =>
        choices.sizes.find((choice) => choice.value === value)?.label ?? value;

    return (
        <>
            <Head title={`Попапы — ${site.name}`} />
            <main className="flex min-w-0 flex-1 flex-col gap-6 p-4 sm:p-6">
                <header className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div className="min-w-0 space-y-1">
                        <p className="truncate text-sm text-muted-foreground">
                            {site.name}
                        </p>
                        <h1 className="text-2xl font-semibold tracking-tight sm:text-3xl">
                            Попапы
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Всплывающие окна, которые открываются кнопками
                            сайта. Один попап можно открыть из разных блоков.
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button asChild variant="outline">
                            <Link href={designer(site.public_id)}>
                                Открыть дизайнер
                            </Link>
                        </Button>
                        {can.editPopups && (
                            <PopupSettingsDialog
                                sitePublicId={site.public_id}
                                choices={choices}
                                forms={forms}
                                trigger={
                                    <Button>
                                        <Plus aria-hidden="true" />
                                        Создать попап
                                    </Button>
                                }
                            />
                        )}
                    </div>
                </header>

                {popups.length === 0 ? (
                    <Card className="border-dashed">
                        <CardHeader>
                            <h2 className="leading-none font-semibold">
                                Попапов пока нет
                            </h2>
                            <CardDescription>
                                Создайте попап, затем выберите действие «Открыть
                                попап» у кнопки в дизайнере.
                            </CardDescription>
                        </CardHeader>
                    </Card>
                ) : (
                    <ul className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        {popups.map((popup) => (
                            <li
                                key={popup.public_id}
                                className="flex min-w-0 flex-col gap-3 rounded-xl border bg-card p-4 shadow-sm"
                            >
                                <div className="flex items-start gap-3">
                                    <PanelsTopLeft
                                        aria-hidden="true"
                                        className="mt-0.5 size-5 shrink-0 text-muted-foreground"
                                    />
                                    <div className="min-w-0 flex-1">
                                        <h2 className="font-semibold break-words">
                                            {popup.name}
                                        </h2>
                                        <p className="text-sm text-muted-foreground">
                                            {popup.title ?? 'Без заголовка'}
                                        </p>
                                    </div>
                                    {!popup.status && (
                                        <Badge variant="secondary">
                                            Выключен
                                        </Badge>
                                    )}
                                </div>
                                <p className="text-sm text-muted-foreground">
                                    {`Размер: ${sizeLabel(popup.size)} · Форма: ${
                                        forms.find(
                                            (form) =>
                                                form.value ===
                                                popup.form_public_id,
                                        )?.label ?? 'не выбрана'
                                    }`}
                                </p>
                                <div className="mt-auto flex flex-wrap gap-2">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() =>
                                            setPreviewId(popup.public_id)
                                        }
                                        aria-label={`Просмотреть попап «${popup.name}»`}
                                    >
                                        <Eye aria-hidden="true" />
                                        Просмотр
                                    </Button>
                                    {can.editPopups && (
                                        <PopupSettingsDialog
                                            sitePublicId={site.public_id}
                                            choices={choices}
                                            forms={forms}
                                            popup={popup}
                                            trigger={
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    aria-label={`Изменить попап «${popup.name}»`}
                                                >
                                                    <Pencil aria-hidden="true" />
                                                    Изменить
                                                </Button>
                                            }
                                        />
                                    )}
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </main>
            {previewed && (
                <PopupView
                    popup={previewed}
                    tokens={design}
                    open
                    onOpenChange={(open) => !open && setPreviewId(null)}
                >
                    {previewed.form && <FormView form={previewed.form} />}
                </PopupView>
            )}
        </>
    );
}

PopupsIndex.layout = {
    breadcrumbs: [{ title: 'Панель управления', href: dashboard() }],
};
