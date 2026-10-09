<?php

namespace App\Blocks\Native;

use Symfony\Component\Process\Process;

/** Token-aware defense-in-depth policy for the approved mount-body contract. */
final class NativeJavascriptPolicy
{
    private const FORBIDDEN = [
        'eval', 'Function', 'import', 'export', 'fetch', 'XMLHttpRequest', 'WebSocket',
        'EventSource', 'Worker', 'SharedWorker', 'document', 'window', 'globalThis',
    ];

    /** @return list<string> */
    public function issues(string $source): array
    {
        $tokens = $this->identifiers($source);
        $issues = [];
        foreach (self::FORBIDDEN as $name) {
            if (in_array($name, $tokens, true)) {
                $issues[] = "script.js: {$name} запрещён для нативного блока.";
            }
        }

        for ($i = 0; $i < count($tokens) - 2; $i++) {
            if ($tokens[$i] === 'navigator' && $tokens[$i + 1] === '.' && $tokens[$i + 2] === 'sendBeacon') {
                $issues[] = 'script.js: navigator.sendBeacon запрещён для нативного блока.';
            }
        }

        if ($source !== '' && ! $this->syntaxIsValid($source)) {
            $issues[] = 'script.js: исправьте синтаксис JavaScript.';
        }

        return array_values(array_unique($issues));
    }

    /** @return list<string> */
    private function identifiers(string $source): array
    {
        $out = [];
        $length = strlen($source);
        $i = 0;
        while ($i < $length) {
            $char = $source[$i];
            $next = $source[$i + 1] ?? '';
            if ($char === '/' && $next === '/') {
                $i += 2;
                while ($i < $length && $source[$i] !== "\n") {
                    $i++;
                }

                continue;
            }
            if ($char === '/' && $next === '*') {
                $i += 2;
                while ($i + 1 < $length && ! ($source[$i] === '*' && $source[$i + 1] === '/')) {
                    $i++;
                } $i += 2;

                continue;
            }
            if ($char === "'" || $char === '"' || $char === '`') {
                $quote = $char;
                $i++;
                while ($i < $length) {
                    if ($source[$i] === '\\') {
                        $i += 2;

                        continue;
                    } if ($source[$i++] === $quote) {
                        break;
                    }
                }

                continue;
            }
            if (preg_match('/[A-Za-z_$]/', $char) === 1) {
                $start = $i++;
                while ($i < $length && preg_match('/[A-Za-z0-9_$]/', $source[$i]) === 1) {
                    $i++;
                }
                $out[] = substr($source, $start, $i - $start);

                continue;
            }
            if ($char === '.') {
                $out[] = '.';
            }
            $i++;
        }

        return $out;
    }

    private function syntaxIsValid(string $source): bool
    {
        $process = new Process(
            [(string) config('publishing.node_binary'), '--check', '-'],
            env: self::systemEnvironment(),
            input: "function mount(root, props, api) {\n\"use strict\";\n{$source}\n}\n",
        );
        $process->setTimeout(10)->run();

        return $process->isSuccessful();
    }

    /**
     * Web SAPIs expose a filtered environment to child processes. Node needs PATH and
     * SystemRoot on Windows; Symfony Process also needs the configured temp directory.
     *
     * @return array<string, string>
     */
    private static function systemEnvironment(): array
    {
        $environment = [];

        foreach (['PATH', 'SystemRoot', 'TEMP', 'TMP'] as $key) {
            $value = getenv($key);

            if (is_string($value) && $value !== '') {
                $environment[$key] = $value;
            }
        }

        return $environment;
    }
}
