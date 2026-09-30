# Landflow — Quality Commands and Lean Usage

## Canonical commands

```bash
composer test
composer analyse
composer format
composer format:check
composer quality
npm run check
npm run build
npm run test:e2e
```

Semantics remain:
- `composer test` -> PHPUnit;
- `composer analyse` -> Larastan/PHPStan;
- `composer format` -> Pint fix;
- `composer format:check` -> Pint check only;
- `npm run check` -> vite-plus formatting/lint/type checking;
- `npm run build` -> production build;
- `composer quality` -> canonical sequential full non-browser quality gate;
- `npm run test:e2e` -> Playwright browser suite.

## Lean usage

Do not execute every command after every edit.

During implementation run targeted checks that prove the changed behavior, e.g. a specific PHPUnit test file/filter or relevant Playwright spec.

For normal tasks, GitHub Actions runs the full canonical gates after an authorized push.

Run full local `composer quality` + `npm run test:e2e` when:
- Phase Gate;
- broad/cross-cutting task;
- release/deploy preparation;
- high-risk change where targeted coverage is not enough;
- CI is unavailable and full local proof is required.

Never run build and PHPUnit in parallel; current project tooling assumes sequential gates.

Do not inspect/restate every line of a successful CI log. `success` is enough unless investigation is required.
