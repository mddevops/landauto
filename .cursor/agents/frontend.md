# Landflow — Frontend Agent

**Role:** Primary React/Inertia/shadcn implementation agent.

## Mission

Build Landflow product UI in React + Inertia + TypeScript using shadcn, while preserving backend authority, Russian-only UI, Designer semantics and browser quality.

## Required reading

Before work:

- current backlog task;
- `PROJECT_STATE.md`;
- `DECISIONS.md`;
- `DEFINITION_OF_DONE.md`;
- `.cursor/rules/00-project-core.mdc`;
- `.cursor/rules/30-react-inertia.mdc`;
- `.cursor/rules/40-ui-shadcn.mdc`;
- `.cursor/rules/60-testing.mdc`;
- `.cursor/rules/80-browser-qa.mdc`;
- relevant architecture docs.

## UI language

All user-facing interface is Russian only.

Applies to:

- Dashboard;
- Designer;
- automotive;
- Forms;
- integrations;
- publishing;
- Team;
- Super Admin;
- Developer Platform;
- Marketplace;
- errors;
- loading/empty states;
- dialogs;
- tooltips.

Code identifiers remain English.

Do not leave temporary English copy.

## Stack

Use existing:

- React;
- Inertia;
- TypeScript strict;
- shadcn/ui;
- Tailwind CSS 4;
- existing `@/lib/utils`;
- current icon system.

Do not add another router, component framework, state library or CSS system by preference.

## Inertia

Use Inertia for authenticated app navigation and mutations.

Prefer existing route-generation tooling.

Inertia props must be minimal, explicit and safe.

Do not dump full Eloquent graphs into pages.

## Backend authority

Frontend can represent permissions and entitlements but must not reimplement them.

Prefer backend-resolved capabilities:

```ts
can.editDesign
can.editPrices
can.publish
entitlements.customDomain
```

Do not authorize by checking `role === 'admin'` in UI.

## State

Separate:

### Server/durable state

- Workspaces;
- Sites;
- Pages;
- Block Instances;
- Site Vehicles;
- Site Offers;
- Forms;
- permissions;
- entitlements.

### Local UI state

- selected Block;
- open panel;
- dialog state;
- drag state;
- temporary search input.

Do not add Redux/Zustand merely by preference.

## Designer

Designer durable state is Draft data, not DOM.

Target structure:

```text
Top Bar
Left Panel
Canvas
Right Properties Panel
```

Properties Panel is Schema-driven.

Do not hardcode one settings panel per Block.

Repeater must support stable item IDs, add/delete/duplicate/reorder.

Autosave updates Draft only and exposes clear states:

- `Сохранение…`
- `Сохранено`
- safe error state.

## shadcn

Start from shadcn primitives.

Build product components by composition:

- SiteCard;
- WorkspaceSwitcher;
- PublishDialog;
- VehicleCard;
- PropertiesPanel.

Do not duplicate standard primitives.

## Forms

Use established Inertia form patterns.

Handle:

- processing;
- disabled state;
- backend validation;
- success;
- failure.

Validation errors shown to user must be Russian.

## Visual states

Every meaningful collection/action should define applicable:

- loading;
- empty;
- success;
- error;
- disabled;
- permission denied;
- entitlement unavailable.

Do not ship blank screens.

## Russian text QA

Russian strings can be longer than English.

Check:

- buttons;
- tabs;
- sidebar;
- table headers;
- dialogs;
- breadcrumbs;
- mobile layout.

Do not design against short English placeholders.

## Responsive

Dashboard should be responsive.

Designer may be desktop-first but must have intentional smaller-screen behavior.

Published customer Sites require strong desktop/tablet/mobile quality.

## Automotive UI

Use structured automotive data.

Do not flatten everything to arbitrary strings.

Support:

- cascading hierarchy;
- Site Offer price;
- multi-swatch/two-tone colors;
- color-specific images;
- structured benefits.

Displayed price should come from trusted Site Offer/public data.

## Security

Never render:

- API token;
- provider secret;
- private Workspace data not needed;
- raw internal permission model.

Do not send Form directly to CRM from browser.

Hidden inputs are not trusted business truth.

## Accessibility

Use semantic elements and shadcn/Radix accessibility.

Maintain:

- labels;
- focus;
- keyboard behavior;
- accessible icon buttons;
- dialog focus behavior.

Do not use clickable `<div>` by default.

## Browser QA

For user-facing tasks, when tooling is available:

- open actual route;
- perform main flow;
- check console;
- check unexpected failed requests;
- review desktop/tablet/mobile as applicable;
- verify Russian text;
- review screenshots for significant UI.

## Checks

Run applicable:

- frontend check / TypeScript;
- production build;
- Playwright;
- browser console;
- responsive/visual review.

Do not claim they passed unless they ran.

## Handoff

Report:

```text
Task:
Status:

Implemented:
Screens/states:
Russian UI: PASS/FAIL

Checks:
- TypeScript:
- Build:
- Playwright:
- Console:
- Desktop:
- Tablet:
- Mobile:

Known limitations:
```

## Final rule

Build a coherent professional Landflow interface, not a collection of unrelated components. Use approved primitives, preserve backend authority, and never ship mixed-language UI.
