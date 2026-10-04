// Prepares the isolated E2E environment before the Playwright web server starts:
// .env.e2e is regenerated from .env.e2e.example and the SQLite database is recreated,
// so every run starts from the same empty, migrated state.
import { execFileSync } from 'node:child_process';
import { randomBytes } from 'node:crypto';
import { existsSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '../../..');
const fromRoot = (path) => resolve(root, path);

function fail(message) {
    console.error(`[e2e] ${message}`);
    process.exit(1);
}

if (existsSync(fromRoot('bootstrap/cache/config.php'))) {
    fail(
        'Laravel configuration is cached; the E2E server would ignore .env.e2e. Run "php artisan config:clear" first.',
    );
}

if (existsSync(fromRoot('public/hot'))) {
    fail(
        'public/hot exists (Vite dev server); E2E must use the production build. Stop "npm run dev" first.',
    );
}

if (
    process.env.E2E_REUSE_BUILD === '1' &&
    !existsSync(fromRoot('public/build/manifest.json'))
) {
    fail(
        'E2E_REUSE_BUILD=1 but there is no production build (public/build/manifest.json). Run "composer quality" or "npm run build" first.',
    );
}

const database = fromRoot('database/e2e.sqlite');
const catalogDatabase = fromRoot('database/e2e-catalog.sqlite');

for (const file of [database, catalogDatabase]) {
    rmSync(file, { force: true });
    writeFileSync(file, '');
}

const key = `base64:${randomBytes(32).toString('base64')}`;

const environment = readFileSync(fromRoot('.env.e2e.example'), 'utf8')
    .replace(/^APP_KEY=.*$/m, `APP_KEY=${key}`)
    .replace(
        /^DB_DATABASE=.*$/m,
        `DB_DATABASE="${database.replaceAll('\\', '/')}"`,
    )
    .replace(
        /^CATALOG_DB_DATABASE=.*$/m,
        `CATALOG_DB_DATABASE="${catalogDatabase.replaceAll('\\', '/')}"`,
    );

writeFileSync(fromRoot('.env.e2e'), environment);

// The developer database (e.g. MySQL `landauto`) must never be migrated or written by E2E:
// ask Laravel which connection it actually resolves in the e2e environment.
// The same applies to the separate catalog database.
function resolveConnection(connection) {
    const target = connection === null ? [] : [`--database=${connection}`];

    return JSON.parse(
        execFileSync('php', ['artisan', 'db:show', '--json', ...target], {
            cwd: root,
            env: { ...process.env, APP_ENV: 'e2e' },
            encoding: 'utf8',
        }),
    ).platform.config;
}

for (const [connection, expected, label] of [
    [null, database, 'database/e2e.sqlite'],
    ['catalog', catalogDatabase, 'database/e2e-catalog.sqlite'],
]) {
    const resolved = resolveConnection(connection);

    if (
        resolved.driver !== 'sqlite' ||
        resolve(String(resolved.database)) !== resolve(expected)
    ) {
        fail(
            `E2E environment resolves ${connection ?? 'main'} database "${resolved.driver}:${resolved.database}" instead of ${label}. Check for DB_* / CATALOG_DB_* variables set in the shell.`,
        );
    }
}

console.log(
    '[e2e] .env.e2e generated, database/e2e.sqlite and database/e2e-catalog.sqlite recreated and verified.',
);
