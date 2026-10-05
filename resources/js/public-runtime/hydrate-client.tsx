import { hydrateRoot } from 'react-dom/client';
import type { CaptchaConfig } from '@/blocks/captcha';
import { connectMetrica } from './analytics';
import { PublishedSite } from './published-site';
import type { PublishedPagePayload } from './published-site';

/**
 * Hydrates the stored publish-time HTML with the same payload it was rendered from. Nothing
 * is fetched from the Draft; interactivity stays inside the loaded Published Version.
 */
const root = document.getElementById('lf-root');
const data = document.getElementById('lf-page-data');

if (root && data?.textContent) {
    const { payload, captcha, analytics } = JSON.parse(data.textContent) as {
        payload: PublishedPagePayload;
        captcha: CaptchaConfig | null;
        analytics?: { metrica: string | null };
    };

    connectMetrica(analytics?.metrica ?? null);
    hydrateRoot(root, <PublishedSite payload={payload} captcha={captcha} />);
}
