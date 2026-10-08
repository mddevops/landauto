import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Monitor, Smartphone, Tablet } from 'lucide-react';
import { useState } from 'react';
import { NativeSelect } from '@/components/platform/form-fields';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { designer, preview } from '@/routes/studio/templates';
import { frame } from '@/routes/studio/templates/preview';

const devices = [
    { value: 'desktop', label: 'Компьютер', width: null, icon: Monitor },
    { value: 'tablet', label: 'Планшет', width: 768, icon: Tablet },
    { value: 'mobile', label: 'Телефон', width: 375, icon: Smartphone },
] as const;

type Device = (typeof devices)[number]['value'];

export default function TemplatePreview({
    template,
    page,
    pages,
}: {
    template: { public_id: string; name: string };
    page: { public_id: string; title: string };
    pages: { public_id: string; title: string }[];
}) {
    const [device, setDevice] = useState<Device>('desktop');
    const width = devices.find((item) => item.value === device)?.width ?? null;

    return (
        <>
            <Head title={`Предпросмотр — ${template.name}`}>
                <meta name="robots" content="noindex, nofollow" />
            </Head>
            <div className="flex h-svh flex-col bg-muted/40">
                <div className="flex flex-wrap items-center gap-3 border-b bg-background px-3 py-2 text-sm sm:px-4">
                    <Button asChild variant="ghost" size="sm">
                        <Link
                            href={designer(template.public_id, {
                                query: { page: page.public_id },
                            })}
                        >
                            <ArrowLeft aria-hidden="true" />
                            <span className="max-sm:sr-only">
                                Вернуться в дизайнер
                            </span>
                        </Link>
                    </Button>
                    <p className="min-w-0 flex-1 truncate text-muted-foreground">
                        {template.name}
                    </p>
                    <label className="sr-only" htmlFor="template-preview-page">
                        Страница
                    </label>
                    <NativeSelect
                        id="template-preview-page"
                        className="w-auto"
                        value={page.public_id}
                        onChange={(event) =>
                            router.get(
                                preview.url(template.public_id, {
                                    query: { page: event.target.value },
                                }),
                            )
                        }
                    >
                        {pages.map((item) => (
                            <option key={item.public_id} value={item.public_id}>
                                {item.title}
                            </option>
                        ))}
                    </NativeSelect>
                    <div
                        role="group"
                        aria-label="Устройство"
                        className="flex gap-1 rounded-lg border p-1"
                    >
                        {devices.map((item) => (
                            <Button
                                key={item.value}
                                type="button"
                                size="sm"
                                variant={
                                    item.value === device
                                        ? 'secondary'
                                        : 'ghost'
                                }
                                aria-pressed={item.value === device}
                                onClick={() => setDevice(item.value)}
                            >
                                <item.icon aria-hidden="true" />
                                <span className="max-sm:sr-only">
                                    {item.label}
                                </span>
                            </Button>
                        ))}
                    </div>
                    <Badge variant="outline">Черновик шаблона</Badge>
                </div>
                <main
                    aria-label="Предпросмотр страницы"
                    className="min-h-0 flex-1 overflow-auto p-2 sm:p-4"
                >
                    <div
                        className="mx-auto h-full bg-white shadow-sm"
                        style={{ width: width ?? '100%' }}
                        data-testid="template-preview-viewport"
                    >
                        <iframe
                            key={page.public_id}
                            title={`Предпросмотр страницы «${page.title}»`}
                            src={frame.url(template.public_id, {
                                query: { page: page.public_id },
                            })}
                            className="block h-full w-full border-0"
                        />
                    </div>
                </main>
            </div>
        </>
    );
}
