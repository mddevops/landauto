# Landflow — Lean Cursor Workflow v2

Этот пакет заменяет тяжёлый review-heavy workflow на экономный режим разработки.

## Что НЕ заменяется

Не удаляйте и не перезаписывайте текущие:

- `docs/automation/BACKLOG.md`
- `docs/automation/PROJECT_STATE.md`
- `docs/automation/DECISIONS.md`
- product/architecture docs
- application code

Они остаются источником фактического состояния проекта.

## Что заменяется этим архивом

- `.cursor/rules/*.mdc`
- `.cursor/agents/*.md`
- `docs/automation/AUTONOMOUS_WORKFLOW.md`
- `docs/automation/DEFINITION_OF_DONE.md`
- `docs/automation/QUALITY_COMMANDS.md`

## Главная идея

Обычная задача выполняется одним Agent:

`task -> implementation -> targeted tests -> commit/push -> GitHub CI`

Не запускаются автоматически цепочки `architect -> backend -> security -> QA -> UI reviewer -> reviewer`.

Subagent вызывается только по реальному risk trigger.

## Token budget rules

1. Только `00-project-core.mdc` имеет `alwaysApply: true`.
2. Остальные правила подключаются по glob или релевантности.
3. Перед задачей не читать весь `/docs`.
4. Читать только current task + `PROJECT_STATE` + конкретный релевантный документ.
5. Обычная задача: максимум один specialist review.
6. High-risk задача: максимум два review passes, если действительно нужны.
7. Полный набор reviews — только Phase Gate.
8. Не запускать background/parallel subagents.
9. Не перечитывать зелёные GitHub Actions logs. Читать logs только при FAIL.
10. Финальные отчёты держать короткими.

## Установка

Распакуйте архив в корень проекта с заменой файлов.

После этого лучше зафиксировать изменения обычным Git вручную, без Agent:

```bash
git status
git add .cursor docs/automation/AUTONOMOUS_WORKFLOW.md docs/automation/DEFINITION_OF_DONE.md docs/automation/QUALITY_COMMANDS.md README-LEAN-CURSOR.md
git commit -m "chore: adopt lean Cursor workflow"
git push origin main
```

Перед commit проверьте `git diff --cached`.

## Как теперь запускать обычную задачу

Короткая команда:

```text
Выполни следующую ready task Landflow по Lean Autonomous Workflow в SINGLE TASK MODE.
Не запускай subagents без явного risk trigger.
Используй targeted checks. Commit/push только с моего разрешения.
```

Для маленького исправления ещё короче:

```text
Исправь только эту проблему. Не запускай subagents. Запусти только релевантные targeted tests. Не делай commit/push.
```

## Когда нужен дорогой review

Security review: tenancy, permissions, auth, secrets, public endpoints, uploads, outbound HTTP, publishing, domains, personal data.

UI review: значимый новый экран/Designer/public UI, а не каждая подпись или spacing fix.

QA review: критичный workflow, regression/security bug, phase gate.

Final Reviewer: Phase Gate, большой cross-cutting task или явный запрос владельца.

## Full quality

GitHub Actions остаётся главным полным gate после push.

Локально не нужно каждый раз запускать полный `composer quality + npm run test:e2e`, если изменение маленькое и покрыто targeted tests.

Полный локальный прогон нужен для high-risk/cross-cutting задач, перед Phase Gate или когда CI недоступен.
