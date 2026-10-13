import { spawn } from 'node:child_process';
import { mkdtempSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

const PROTOCOL_PREFIX = '\u001eartisan-worker ';
const OUTPUT_LIMIT = 4_000;

type Reply = { status: number; output: string };

type Pending = {
    resolve: (reply: Reply) => void;
    reject: (error: Error) => void;
};

export type ArtisanWorker = {
    /** Runs one Artisan command line; throws with exit code, stdout and stderr on failure. */
    run: (...args: string[]) => Promise<void>;
    stop: () => Promise<void>;
};

function tail(output: string): string {
    if (output === '') {
        return '(empty)';
    }

    return output.length > OUTPUT_LIMIT
        ? `…[${output.length - OUTPUT_LIMIT} chars truncated]\n${output.slice(-OUTPUT_LIMIT)}`
        : output;
}

/**
 * Starts one PHP process for the isolated E2E environment that boots Laravel once and then runs
 * Artisan commands in the order they are sent (tests/browser/support/artisan-worker.php). Booting a
 * separate `php artisan` per command cost seconds per call under full-suite load.
 */
export function startArtisanWorker(): ArtisanWorker {
    // Keep PsySH/Tinker state inside a writable E2E-only directory. On Windows PsySH gives
    // APPDATA precedence over XDG_CONFIG_HOME, so overriding APPDATA is the supported isolation.
    const e2eAppData = mkdtempSync(join(tmpdir(), 'landflow-e2e-appdata-'));
    const child = spawn('php', ['tests/browser/support/artisan-worker.php'], {
        env: { ...process.env, APP_ENV: 'e2e', APPDATA: e2eAppData },
        stdio: 'pipe',
    });
    const pending: Pending[] = [];
    let buffer = '';
    let strayStdout = '';
    let stderr = '';
    let exited: string | null = null;

    const diagnostics = (stdout: string) =>
        [
            `--- stdout ---\n${tail(stdout + strayStdout)}`,
            `--- stderr ---\n${tail(stderr)}`,
        ].join('\n');

    const fail = (reason: string) => {
        exited ??= reason;

        for (const waiting of pending.splice(0)) {
            waiting.reject(
                new Error(
                    `Artisan worker stopped: ${exited}\n${diagnostics('')}`,
                ),
            );
        }
    };

    child.stdout.setEncoding('utf8');
    child.stdout.on('data', (chunk: string) => {
        buffer += chunk;
        let newline = buffer.indexOf('\n');

        while (newline !== -1) {
            const line = buffer.slice(0, newline).replace(/\r$/, '');
            buffer = buffer.slice(newline + 1);
            newline = buffer.indexOf('\n');

            if (line.startsWith(PROTOCOL_PREFIX)) {
                pending
                    .shift()
                    ?.resolve(
                        JSON.parse(line.slice(PROTOCOL_PREFIX.length)) as Reply,
                    );
            } else {
                strayStdout = `${strayStdout}${line}\n`.slice(-OUTPUT_LIMIT);
            }
        }
    });
    child.stderr.setEncoding('utf8');
    child.stderr.on('data', (chunk: string) => {
        stderr = `${stderr}${chunk}`.slice(-OUTPUT_LIMIT * 2);
    });
    child.once('error', (error) => fail(`error=${error.message}`));
    child.once('exit', (code, signal) =>
        fail(`status=${code}, signal=${signal}`),
    );

    const nextReply = () =>
        new Promise<Reply>((resolve, reject) => {
            if (exited !== null) {
                reject(
                    new Error(
                        `Artisan worker stopped: ${exited}\n${diagnostics('')}`,
                    ),
                );

                return;
            }

            pending.push({ resolve, reject });
        });
    const ready = nextReply();
    // A failed start is reported by the first `run`, not as an unhandled rejection.
    ready.catch(() => undefined);

    return {
        async run(...args: string[]) {
            await ready;
            const command = [...args, '--no-interaction'];
            const reply = nextReply();
            child.stdin.write(`${JSON.stringify(command)}\n`);
            const { status, output } = await reply;

            if (status !== 0) {
                throw new Error(
                    `php artisan ${command.join(' ')} failed: status=${status}\n${diagnostics(output)}`,
                );
            }
        },
        async stop() {
            if (exited !== null) {
                return;
            }

            const stopped = new Promise<void>((resolve) =>
                child.once('exit', () => resolve()),
            );
            child.stdin.end();

            const timer = setTimeout(() => child.kill(), 5_000);
            await stopped;
            clearTimeout(timer);
        },
    };
}
