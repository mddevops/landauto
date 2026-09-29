import { Head, Link, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { dashboard, login, register } from '@/routes';

export default function Welcome() {
    const { auth, name } = usePage().props;

    return (
        <>
            <Head title="Добро пожаловать" />
            <div className="flex min-h-screen flex-col bg-background p-6 text-foreground lg:p-8">
                <header className="mx-auto w-full max-w-4xl">
                    <nav className="flex items-center justify-end gap-2">
                        {auth.user ? (
                            <Button asChild variant="outline">
                                <Link href={dashboard()}>
                                    Панель управления
                                </Link>
                            </Button>
                        ) : (
                            <>
                                <Button asChild variant="ghost">
                                    <Link href={login()}>Войти</Link>
                                </Button>
                                <Button asChild variant="outline">
                                    <Link href={register()}>Регистрация</Link>
                                </Button>
                            </>
                        )}
                    </nav>
                </header>
                <main className="flex flex-1 items-center justify-center">
                    <div className="flex max-w-md flex-col items-center gap-3 text-center">
                        <h1 className="text-3xl font-semibold tracking-tight">
                            {name}
                        </h1>
                        <p className="text-muted-foreground">
                            Конструктор сайтов для автомобильного бизнеса.
                        </p>
                    </div>
                </main>
            </div>
        </>
    );
}
