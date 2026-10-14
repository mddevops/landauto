import { spawn } from 'node:child_process';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '../../..');
const publicDirectory = resolve(root, 'public');
const router = resolve(
    root,
    'vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php',
);
const playwrightCli = resolve(root, 'node_modules/@playwright/test/cli.js');
const vitePlusCli = resolve(root, 'node_modules/vite-plus/bin/vp');
const baseURL = 'http://127.0.0.1:8200';
const environment = {
    ...process.env,
    APP_ENV: 'e2e',
    VITE_APP_NAME: 'Landflow',
};
let serverDiagnostics = '';

function run(command, args, extraEnvironment = {}) {
    return new Promise((resolveRun, reject) => {
        const child = spawn(command, args, {
            cwd: root,
            env: { ...environment, ...extraEnvironment },
            stdio: 'inherit',
            shell: false,
        });

        child.once('error', reject);
        child.once('exit', (code, signal) => {
            if (code === 0) {
                resolveRun();
            } else {
                reject(
                    new Error(
                        `${command} завершился с code=${code ?? 'null'}, signal=${signal ?? 'null'}.`,
                    ),
                );
            }
        });
    });
}

async function waitForServer(server) {
    const deadline = Date.now() + 180_000;

    while (Date.now() < deadline) {
        if (server.exitCode !== null || server.signalCode !== null) {
            throw new Error(
                `PHP E2E server exited before readiness: pid=${server.pid}, code=${server.exitCode}, signal=${server.signalCode}.\n${serverDiagnostics}`,
            );
        }

        try {
            const response = await fetch(`${baseURL}/up`);

            if (response.ok) {
                return;
            }
        } catch {
            // The server is still starting.
        }

        await new Promise((resolveWait) => setTimeout(resolveWait, 250));
    }

    throw new Error(`PHP E2E server did not become ready at ${baseURL}/up.`);
}

function waitForExit(server, timeout) {
    if (server.exitCode !== null || server.signalCode !== null) {
        return Promise.resolve(true);
    }

    return Promise.race([
        new Promise((resolveExit) =>
            server.once('exit', () => resolveExit(true)),
        ),
        new Promise((resolveTimeout) =>
            setTimeout(() => resolveTimeout(false), timeout),
        ),
    ]);
}

async function stopServer(server) {
    if (server.exitCode !== null || server.signalCode !== null) {
        return;
    }

    console.error(`[e2e] Stopping PHP server pid=${server.pid}.`);
    server.kill('SIGTERM');

    if (await waitForExit(server, 5_000)) {
        return;
    }

    console.error(
        `[e2e] PHP server pid=${server.pid} did not stop after SIGTERM; sending SIGKILL.`,
    );
    server.kill('SIGKILL');

    if (!(await waitForExit(server, 5_000))) {
        throw new Error(
            `PHP E2E server did not exit: pid=${server.pid}, exitCode=${server.exitCode}, signalCode=${server.signalCode}.\n${serverDiagnostics}`,
        );
    }
}

let server;
let failure;

try {
    await run(process.execPath, ['tests/browser/support/prepare-e2e.mjs']);

    if (process.env.E2E_REUSE_BUILD !== '1') {
        await run(process.execPath, [vitePlusCli, 'build']);
        await run(process.execPath, [vitePlusCli, 'build', '--ssr']);
    }

    await run('php', ['artisan', 'migrate', '--force', '--no-interaction']);
    await run('php', [
        'artisan',
        'catalog:migrate',
        '--force',
        '--no-interaction',
    ]);
    await run('php', [
        'artisan',
        'db:seed',
        '--class=E2eSeeder',
        '--force',
        '--no-interaction',
    ]);

    server = spawn('php', ['-S', '127.0.0.1:8200', router], {
        cwd: publicDirectory,
        env: environment,
        stdio: ['ignore', 'ignore', 'pipe'],
        shell: false,
    });
    server.stderr.setEncoding('utf8');
    server.stderr.on('data', (chunk) => {
        serverDiagnostics = `${serverDiagnostics}${chunk}`.slice(-32_768);
    });
    console.error(`[e2e] Started PHP server pid=${server.pid}.`);
    await waitForServer(server);
    await run(process.execPath, [playwrightCli, 'test'], {
        E2E_MANAGED_SERVER: '1',
    });
} catch (error) {
    failure = error;
} finally {
    if (server) {
        try {
            await stopServer(server);
        } catch (error) {
            failure ??= error;
        }
    }
}

if (failure) {
    console.error(`[e2e] ${failure.message}`);
    process.exitCode = 1;
}
