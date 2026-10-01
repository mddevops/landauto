import { Button } from '@/components/ui/button';

export default function YandexAuthButton() {
    return (
        <Button variant="outline" className="w-full" asChild>
            <a href="/auth/yandex/redirect">Войти через Яндекс</a>
        </Button>
    );
}
