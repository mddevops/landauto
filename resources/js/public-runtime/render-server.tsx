import { renderToString } from 'react-dom/server';
import { PublishedSite } from './published-site';
import type { PublishedPagePayload } from './published-site';

/**
 * Publish-time renderer (ADR-006 §2). Reads `{ pages: PublishedPagePayload[] }` from STDIN and
 * writes `{ pages: [{ page, html }] }` to STDOUT. Pure: no database, network or secrets.
 */
async function readInput(): Promise<string> {
    const chunks: Buffer[] = [];

    for await (const chunk of process.stdin) {
        chunks.push(chunk as Buffer);
    }

    return Buffer.concat(chunks).toString('utf8');
}

async function main(): Promise<void> {
    const input = JSON.parse(await readInput()) as {
        pages: PublishedPagePayload[];
    };
    const pages = input.pages.map((payload) => ({
        page: payload.page.public_id,
        html: renderToString(<PublishedSite payload={payload} />),
    }));

    process.stdout.write(JSON.stringify({ pages }));
}

main().catch((error: unknown) => {
    process.stderr.write(
        error instanceof Error ? error.message : 'Render failed',
    );
    process.exitCode = 1;
});
