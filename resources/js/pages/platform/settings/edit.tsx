import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { edit, update } from '@/routes/platform/settings';

type Settings = {
    submission_retention_months: number;
    account_deletion_grace_days: number;
    free_max_storage_mb: number;
};

export default function PlatformSettingsEdit({
    settings,
}: {
    settings: Settings;
}) {
    return (
        <>
            <Head title="Настройки платформы" />
            <div className="mx-auto flex w-full max-w-2xl flex-col gap-8 p-6">
                <div className="space-y-2">
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Настройки платформы
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Значения по умолчанию для хранения заявок, отсрочки
                        удаления аккаунта и лимита файлов тарифа Free. Тарифы
                        без лимита хранилища остаются без ограничения.
                    </p>
                </div>

                <Form
                    {...update.form()}
                    className="space-y-6"
                    options={{ preserveScroll: true }}
                >
                    {({ errors, processing }) => (
                        <>
                            <div className="space-y-2">
                                <Label htmlFor="submission_retention_months">
                                    Срок хранения заявок (месяцы)
                                </Label>
                                <Input
                                    id="submission_retention_months"
                                    name="submission_retention_months"
                                    type="number"
                                    min={1}
                                    max={120}
                                    defaultValue={
                                        settings.submission_retention_months
                                    }
                                    required
                                />
                                <InputError
                                    message={errors.submission_retention_months}
                                />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="account_deletion_grace_days">
                                    Отсрочка удаления аккаунта (дни)
                                </Label>
                                <Input
                                    id="account_deletion_grace_days"
                                    name="account_deletion_grace_days"
                                    type="number"
                                    min={1}
                                    max={365}
                                    defaultValue={
                                        settings.account_deletion_grace_days
                                    }
                                    required
                                />
                                <p className="text-xs text-muted-foreground">
                                    После запроса удаления вход блокируется;
                                    аккаунт удаляется по истечении этого срока.
                                </p>
                                <InputError
                                    message={errors.account_deletion_grace_days}
                                />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="free_max_storage_mb">
                                    Лимит хранилища Free (МБ)
                                </Label>
                                <Input
                                    id="free_max_storage_mb"
                                    name="free_max_storage_mb"
                                    type="number"
                                    min={1}
                                    defaultValue={settings.free_max_storage_mb}
                                    required
                                />
                                <InputError
                                    message={errors.free_max_storage_mb}
                                />
                            </div>

                            <Button type="submit" disabled={processing}>
                                {processing && <Spinner />}
                                Сохранить
                            </Button>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

PlatformSettingsEdit.layout = {
    breadcrumbs: [{ title: 'Настройки платформы', href: edit() }],
};
