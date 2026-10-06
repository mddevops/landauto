import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { dashboard } from '@/routes';
import { edit, update } from '@/routes/workspace/settings';

type WorkspaceSettingsProps = {
    settings: {
        name: string;
        plan: string | null;
        created_at: string | null;
    };
    nameMaxLength: number;
};

export default function WorkspaceSettings({
    settings,
    nameMaxLength,
}: WorkspaceSettingsProps) {
    return (
        <>
            <Head title="Настройки пространства" />
            <main className="flex min-w-0 flex-1 flex-col gap-6 p-4 sm:p-6">
                <header className="min-w-0 space-y-1">
                    <p className="text-sm text-muted-foreground">
                        Рабочее пространство
                    </p>
                    <h1 className="text-2xl font-semibold tracking-tight break-words sm:text-3xl">
                        Настройки пространства
                    </h1>
                </header>

                <Card className="max-w-2xl">
                    <CardHeader>
                        <CardTitle>Рабочее пространство</CardTitle>
                        <CardDescription>
                            Название видят все участники пространства.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-6">
                        <Form
                            {...update.form()}
                            options={{ preserveScroll: true }}
                            disableWhileProcessing
                            className="grid gap-4"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <div className="grid gap-2">
                                        <Label htmlFor="workspace-name">
                                            Название
                                        </Label>
                                        <Input
                                            id="workspace-name"
                                            name="name"
                                            type="text"
                                            required
                                            maxLength={nameMaxLength}
                                            autoComplete="off"
                                            defaultValue={settings.name}
                                            aria-invalid={Boolean(errors.name)}
                                            aria-describedby={
                                                errors.name
                                                    ? 'workspace-name-error'
                                                    : undefined
                                            }
                                        />
                                        <InputError
                                            id="workspace-name-error"
                                            message={errors.name}
                                        />
                                    </div>
                                    <div>
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            {processing && <Spinner />}
                                            Сохранить
                                        </Button>
                                    </div>
                                </>
                            )}
                        </Form>

                        <dl className="grid gap-3 text-sm sm:grid-cols-2">
                            {settings.plan && (
                                <div className="space-y-1">
                                    <dt className="text-muted-foreground">
                                        Тариф
                                    </dt>
                                    <dd className="font-medium">
                                        {settings.plan}
                                    </dd>
                                </div>
                            )}
                            {settings.created_at && (
                                <div className="space-y-1">
                                    <dt className="text-muted-foreground">
                                        Создано
                                    </dt>
                                    <dd className="font-medium">
                                        {new Date(
                                            settings.created_at,
                                        ).toLocaleDateString('ru-RU')}
                                    </dd>
                                </div>
                            )}
                        </dl>
                    </CardContent>
                </Card>
            </main>
        </>
    );
}

WorkspaceSettings.layout = {
    breadcrumbs: [
        { title: 'Все сайты', href: dashboard() },
        { title: 'Настройки пространства', href: edit() },
    ],
};
