import { Form, Head, router } from '@inertiajs/react';
import {
    Globe,
    Lock,
    Plus,
    RefreshCw,
    ShieldCheck,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { dashboard } from '@/routes';
import { check, destroy, ssl, store } from '@/routes/sites/domains';

type DomainState =
    | 'pending'
    | 'dns_misconfigured'
    | 'verified'
    | 'ssl_provisioning'
    | 'active'
    | 'failed';

export type SiteDomainRow = {
    public_id: string;
    hostname: string;
    state: DomainState;
    state_label: string;
    verification_status: 'pending' | 'verified' | 'failed';
    routing_status: 'pending' | 'misconfigured' | 'verified';
    ssl_status: 'pending' | 'provisioning' | 'active' | 'failed';
    is_primary: boolean;
    ssl_expires_at: string | null;
    can_provision_ssl: boolean;
    last_checked_at: string | null;
    last_error: string | null;
    verification: { name: string; value: string };
    url: string;
};

type DomainsProps = {
    site: { public_id: string; name: string };
    landflowAddress: string | null;
    domains: SiteDomainRow[];
    dns: {
        cname_target: string | null;
        ipv4: string | null;
        ipv6: string | null;
    };
    can: { manage: boolean };
    entitled: boolean;
};

const dateFormat = new Intl.DateTimeFormat('ru-RU', {
    dateStyle: 'medium',
    timeStyle: 'short',
});

function formatDate(value: string | null): string {
    return value ? dateFormat.format(new Date(value)) : '—';
}

const stateVariant: Record<
    DomainState,
    'default' | 'secondary' | 'outline' | 'destructive'
> = {
    pending: 'outline',
    dns_misconfigured: 'outline',
    verified: 'secondary',
    ssl_provisioning: 'secondary',
    active: 'default',
    failed: 'destructive',
};

function StepStatus({ done, label }: { done: boolean; label: string }) {
    return (
        <li className="flex items-center gap-2">
            <span
                aria-hidden="true"
                className={
                    done
                        ? 'size-2 rounded-full bg-emerald-500'
                        : 'size-2 rounded-full bg-muted-foreground/40'
                }
            />
            <span>{`${label}: ${done ? 'готово' : 'ожидает'}`}</span>
        </li>
    );
}

function DnsRecord({
    type,
    name,
    value,
}: {
    type: string;
    name: string;
    value: string;
}) {
    return (
        <div className="grid min-w-0 gap-1 rounded-md border bg-muted/40 p-3 text-sm sm:grid-cols-[5rem_minmax(0,1fr)_minmax(0,1.4fr)] sm:gap-3">
            <span className="font-medium">{type}</span>
            <code className="min-w-0 break-all">{name}</code>
            <code className="min-w-0 break-all">{value}</code>
        </div>
    );
}

function DomainCard({
    site,
    domain,
    dns,
    canManage,
}: {
    site: DomainsProps['site'];
    domain: SiteDomainRow;
    dns: DomainsProps['dns'];
    canManage: boolean;
}) {
    const [confirming, setConfirming] = useState(false);
    const [removing, setRemoving] = useState(false);
    const [busy, setBusy] = useState<'check' | 'ssl' | null>(null);
    const dnsConfigured = Boolean(dns.cname_target || dns.ipv4);
    const ids = { site: site.public_id, domain: domain.public_id };

    function post(action: 'check' | 'ssl') {
        setBusy(action);
        router.post(
            action === 'check' ? check.url(ids) : ssl.url(ids),
            {},
            { preserveScroll: true, onFinish: () => setBusy(null) },
        );
    }

    function remove() {
        setRemoving(true);
        router.delete(destroy.url(ids), {
            preserveScroll: true,
            onFinish: () => {
                setRemoving(false);
                setConfirming(false);
            },
        });
    }

    return (
        <Card className="min-w-0" data-testid="site-domain">
            <CardHeader>
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div className="min-w-0 space-y-1">
                        <CardTitle className="break-all">
                            {domain.hostname}
                        </CardTitle>
                        <CardDescription>
                            {`Последняя проверка: ${formatDate(domain.last_checked_at)}`}
                        </CardDescription>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {domain.is_primary && <Badge>Основной</Badge>}
                        <Badge
                            variant={stateVariant[domain.state]}
                            data-testid="domain-state"
                        >
                            {domain.state_label}
                        </Badge>
                    </div>
                </div>
            </CardHeader>
            <CardContent className="space-y-4">
                <ul className="grid gap-1 text-sm sm:grid-cols-3">
                    <StepStatus
                        done={domain.verification_status === 'verified'}
                        label="Владение"
                    />
                    <StepStatus
                        done={domain.routing_status === 'verified'}
                        label="Направление"
                    />
                    <StepStatus
                        done={domain.ssl_status === 'active'}
                        label="Сертификат"
                    />
                </ul>

                {domain.ssl_status === 'active' && domain.ssl_expires_at && (
                    <p className="text-sm text-muted-foreground">
                        {`Сертификат действует до ${formatDate(domain.ssl_expires_at)} и продлевается автоматически.`}
                    </p>
                )}

                {domain.last_error && (
                    <Alert variant="destructive">
                        <AlertDescription>{domain.last_error}</AlertDescription>
                    </Alert>
                )}

                {domain.state !== 'active' && (
                    <section className="space-y-3" aria-label="DNS-записи">
                        <p className="text-sm font-medium">
                            Добавьте записи у регистратора или DNS-провайдера
                            домена:
                        </p>
                        <div className="hidden px-3 text-xs text-muted-foreground sm:grid sm:grid-cols-[5rem_minmax(0,1fr)_minmax(0,1.4fr)] sm:gap-3">
                            <span>Тип</span>
                            <span>Имя</span>
                            <span>Значение</span>
                        </div>
                        <DnsRecord
                            type="TXT"
                            name={domain.verification.name}
                            value={domain.verification.value}
                        />
                        {dnsConfigured ? (
                            <>
                                {dns.cname_target && (
                                    <DnsRecord
                                        type="CNAME"
                                        name={domain.hostname}
                                        value={dns.cname_target}
                                    />
                                )}
                                {dns.ipv4 && (
                                    <DnsRecord
                                        type="A"
                                        name={domain.hostname}
                                        value={dns.ipv4}
                                    />
                                )}
                                {dns.ipv6 && (
                                    <DnsRecord
                                        type="AAAA"
                                        name={domain.hostname}
                                        value={dns.ipv6}
                                    />
                                )}
                                <p className="text-sm text-muted-foreground">
                                    Для www и других поддоменов используйте
                                    CNAME. Для корневого домена (например,
                                    dealer.ru), если провайдер не поддерживает
                                    CNAME для корня, добавьте A-запись
                                    {dns.ipv6 ? ' и AAAA-запись' : ''}. Менять
                                    NS-серверы не нужно.
                                </p>
                            </>
                        ) : (
                            <p className="text-sm text-muted-foreground">
                                Адрес подключения доменов ещё не настроен в
                                Landflow. Запись для направления трафика
                                появится здесь позже.
                            </p>
                        )}
                    </section>
                )}

                {canManage && (
                    <div className="flex flex-wrap gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            disabled={busy !== null}
                            onClick={() => post('check')}
                            aria-label={`Проверить DNS домена ${domain.hostname}`}
                        >
                            {busy === 'check' ? (
                                <Spinner />
                            ) : (
                                <RefreshCw aria-hidden="true" />
                            )}
                            Проверить DNS
                        </Button>
                        {domain.can_provision_ssl && (
                            <Button
                                type="button"
                                variant="outline"
                                disabled={busy !== null}
                                onClick={() => post('ssl')}
                                aria-label={`Выпустить сертификат для ${domain.hostname}`}
                            >
                                {busy === 'ssl' ? (
                                    <Spinner />
                                ) : (
                                    <ShieldCheck aria-hidden="true" />
                                )}
                                Выпустить сертификат
                            </Button>
                        )}
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setConfirming(true)}
                            aria-label={`Удалить домен ${domain.hostname}`}
                        >
                            <Trash2 aria-hidden="true" />
                            Удалить
                        </Button>
                    </div>
                )}
            </CardContent>

            <Dialog open={confirming} onOpenChange={setConfirming}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Отключить домен?</DialogTitle>
                        <DialogDescription>
                            {`Сайт перестанет открываться по адресу ${domain.hostname}. Адрес Landflow продолжит работать.`}
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setConfirming(false)}
                        >
                            Отмена
                        </Button>
                        <Button
                            type="button"
                            variant="destructive"
                            disabled={removing}
                            onClick={remove}
                        >
                            {removing && <Spinner />}
                            Отключить
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </Card>
    );
}

export default function Domains({
    site,
    landflowAddress,
    domains,
    dns,
    can,
    entitled,
}: DomainsProps) {
    return (
        <>
            <Head title={`Домены — ${site.name}`} />
            <main className="flex min-w-0 flex-1 flex-col gap-6 p-4 sm:p-6">
                <header className="min-w-0 space-y-1">
                    <p className="truncate text-sm text-muted-foreground">
                        {site.name}
                    </p>
                    <h1 className="text-2xl font-semibold tracking-tight sm:text-3xl">
                        Домены
                    </h1>
                    <p className="max-w-2xl text-sm text-muted-foreground">
                        Подключите собственный домен. Регистратор и DNS остаются
                        у вас: достаточно добавить показанные записи. SSL-
                        сертификат Landflow выпустит автоматически.
                    </p>
                </header>

                {!entitled && (
                    <Alert>
                        <Lock aria-hidden="true" />
                        <AlertTitle>
                            Свой домен недоступен на текущем тарифе
                        </AlertTitle>
                        <AlertDescription>
                            Сайт работает по адресу Landflow. Подключение
                            собственного домена появится после смены тарифа.
                        </AlertDescription>
                    </Alert>
                )}

                <Card className="min-w-0">
                    <CardHeader>
                        <CardTitle>Адрес Landflow</CardTitle>
                        <CardDescription>
                            Работает всегда. Если основным выбран свой домен,
                            посетители автоматически перейдут на него.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <p className="flex min-w-0 items-center gap-2 text-sm">
                            <Globe
                                className="size-4 shrink-0"
                                aria-hidden="true"
                            />
                            <span
                                className="break-all"
                                data-testid="landflow-address"
                            >
                                {landflowAddress ?? 'Адрес ещё не задан'}
                            </span>
                        </p>
                    </CardContent>
                </Card>

                {can.manage && (
                    <Card className="min-w-0">
                        <CardHeader>
                            <CardTitle>Добавить домен</CardTitle>
                            <CardDescription>
                                Например, dealer.ru или www.dealer.ru. Каждый
                                адрес добавляется отдельно.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Form
                                {...store.form(site.public_id)}
                                options={{ preserveScroll: true }}
                                resetOnSuccess
                                disableWhileProcessing
                                className="flex flex-col gap-3 sm:flex-row sm:items-start"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <div className="grid min-w-0 flex-1 gap-2">
                                            <Label
                                                htmlFor="hostname"
                                                className="sr-only"
                                            >
                                                Домен
                                            </Label>
                                            <Input
                                                id="hostname"
                                                name="hostname"
                                                type="text"
                                                inputMode="url"
                                                autoComplete="off"
                                                spellCheck={false}
                                                required
                                                maxLength={253}
                                                placeholder="dealer.ru"
                                                aria-invalid={Boolean(
                                                    errors.hostname,
                                                )}
                                                aria-describedby={
                                                    errors.hostname
                                                        ? 'hostname-error'
                                                        : undefined
                                                }
                                            />
                                            <InputError
                                                id="hostname-error"
                                                message={errors.hostname}
                                            />
                                        </div>
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            {processing ? (
                                                <Spinner />
                                            ) : (
                                                <Plus aria-hidden="true" />
                                            )}
                                            Добавить домен
                                        </Button>
                                    </>
                                )}
                            </Form>
                        </CardContent>
                    </Card>
                )}

                {domains.length === 0 ? (
                    <p
                        className="text-sm text-muted-foreground"
                        data-testid="no-domains"
                    >
                        Свои домены ещё не подключены.
                    </p>
                ) : (
                    <section
                        aria-label="Подключённые домены"
                        className="grid gap-4"
                    >
                        {domains.map((domain) => (
                            <DomainCard
                                key={domain.public_id}
                                site={site}
                                domain={domain}
                                dns={dns}
                                canManage={can.manage}
                            />
                        ))}
                    </section>
                )}
            </main>
        </>
    );
}

Domains.layout = {
    breadcrumbs: [{ title: 'Все сайты', href: dashboard() }],
};
