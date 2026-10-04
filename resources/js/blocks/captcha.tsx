import { useEffect, useRef } from 'react';

/** Public CAPTCHA widget config from the backend: provider and client key only. */
export type CaptchaConfig = { provider: 'yandex' | 'fake'; client_key: string };

export const FAKE_CAPTCHA_PASS_TOKEN = 'fake-captcha-pass';

type SmartCaptchaApi = {
    render: (
        container: HTMLElement,
        params: {
            sitekey: string;
            hl: string;
            callback: (token: string) => void;
        },
    ) => number;
    destroy: (widgetId?: number) => void;
};

declare global {
    interface Window {
        smartCaptcha?: SmartCaptchaApi;
        landflowSmartCaptchaReady?: () => void;
    }
}

const scriptUrl =
    'https://smartcaptcha.cloud.yandex.ru/captcha.js?render=onload&onload=landflowSmartCaptchaReady';

let loader: Promise<SmartCaptchaApi> | null = null;

function loadSmartCaptcha(): Promise<SmartCaptchaApi> {
    if (window.smartCaptcha) {
        return Promise.resolve(window.smartCaptcha);
    }

    loader ??= new Promise<SmartCaptchaApi>((resolve, reject) => {
        window.landflowSmartCaptchaReady = () => {
            if (window.smartCaptcha) {
                resolve(window.smartCaptcha);
            } else {
                reject(new Error('SmartCaptcha is unavailable'));
            }
        };

        const script = document.createElement('script');
        script.src = scriptUrl;
        script.async = true;
        script.onerror = () => {
            loader = null;
            reject(new Error('SmartCaptcha failed to load'));
        };
        document.head.appendChild(script);
    });

    return loader;
}

/**
 * CAPTCHA widget with reserved height so the form does not jump while it loads.
 * Remount it (change `key`) to get a fresh single-use token.
 */
export function CaptchaWidget({
    config,
    onToken,
}: {
    config: CaptchaConfig;
    onToken: (token: string) => void;
}) {
    const container = useRef<HTMLDivElement>(null);
    const tokenHandler = useRef(onToken);

    useEffect(() => {
        tokenHandler.current = onToken;
    }, [onToken]);

    useEffect(() => {
        if (config.provider !== 'yandex') {
            return;
        }

        let widgetId: number | null = null;
        let cancelled = false;

        loadSmartCaptcha()
            .then((api) => {
                if (cancelled || !container.current) {
                    return;
                }

                widgetId = api.render(container.current, {
                    sitekey: config.client_key,
                    hl: 'ru',
                    callback: (token) => tokenHandler.current(token),
                });
            })
            .catch(() => undefined);

        return () => {
            cancelled = true;

            if (widgetId !== null) {
                window.smartCaptcha?.destroy(widgetId);
            }
        };
    }, [config.provider, config.client_key]);

    if (config.provider === 'fake') {
        return (
            <label className="flex min-h-[102px] items-center gap-2 rounded-(--lf-radius) border border-dashed border-neutral-300 p-4 text-sm text-neutral-700">
                <input
                    type="checkbox"
                    className="size-4 accent-(--lf-primary)"
                    onChange={(event) =>
                        onToken(
                            event.target.checked ? FAKE_CAPTCHA_PASS_TOKEN : '',
                        )
                    }
                />
                Я не робот (тестовая проверка)
            </label>
        );
    }

    return <div ref={container} className="min-h-[102px]" />;
}
