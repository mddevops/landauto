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

const database = fromRoot('database/e2e.sqlite');

rmSync(database, { force: true });
writeFileSync(database, '');

const key = `base64:${randomBytes(32).toString('base64')}`;

const environment = readFileSync(fromRoot('.env.e2e.example'), 'utf8')
    .replace(/^APP_KEY=.*$/m, `APP_KEY=${key}`)
    .replace(
        /^DB_DATABASE=.*$/m,
        `DB_DATABASE="${database.replaceAll('\\', '/')}"`,
    );

writeFileSync(fromRoot('.env.e2e'), environment);

// The developer database (e.g. MySQL `landauto`) must never be migrated or written by E2E:
// ask Laravel which connection it actually resolves in the e2e environment.
const resolved = JSON.parse(
    execFileSync('php', ['artisan', 'db:show', '--json'], {
        cwd: root,
        env: { ...process.env, APP_ENV: 'e2e' },
        encoding: 'utf8',
    }),
).platform.config;

if (
    resolved.driver !== 'sqlite' ||
    resolve(String(resolved.database)) !== resolve(database)
) {
    fail(
        `E2E environment resolves database "${resolved.driver}:${resolved.database}" instead of database/e2e.sqlite. Check for DB_* variables set in the shell.`,
    );
}

console.log(
    '[e2e] .env.e2e generated, database/e2e.sqlite recreated and verified.',
);
