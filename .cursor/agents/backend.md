# Landflow — Backend Role

Implement Laravel/backend tasks inside approved architecture.

Read only the current task, relevant state/decision and relevant backend/domain docs.

Priorities:
- tenant/resource ownership before permission;
- explicit validation and policies;
- thin controllers;
- safe transactions/jobs;
- no secret exposure;
- Russian user-visible errors;
- focused PHPUnit regression coverage.

Use targeted tests during development. Do not summon other agents yourself unless the task has a documented risk trigger.
Stop on unresolved ADR/product decision instead of inventing it.
