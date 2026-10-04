# ADR-003 — Site Asset Ownership and Asset Versioning

**Status:** Accepted for Phase 2 scope (owner instruction, 2026-10-03: "P2 Site assets are customer Site assets. They are NOT the future global Series Media Library. Keep those concepts separate.")
**Resolves:** D-087 (Phase 2 scope), D-075 (direction)
**Backlog:** X-010 (trigger: before P2-013)

## Context

P2-013 adds image upload and an image picker for Block fields. Two questions had to be settled first: who owns an uploaded asset (D-087), and how Published output stays stable when a customer replaces media (D-075). Platform-owned automotive media (Series Media Library, Phase 3) is a different concept and is out of scope here.

## Decision

### Ownership

1. A Phase 2 asset is a **Site Asset**: it belongs to exactly one Site (`site_assets.site_id`). Its Workspace is derived through the Site; the browser never supplies Workspace or Site ownership.
2. Block state references a Site Asset by its immutable ULID `public_id`. On every save the backend verifies that each referenced asset belongs to the same Site. A reference to another Site's asset is a validation error and is never resolved.
3. A Workspace-level reusable Media Library is **deferred**. When it is added, it may introduce Workspace assets with Site references, but existing Site Assets stay valid as they are. Site-to-site reuse copies assets by default, in line with the copy-not-sync rule.
4. The global Series Media Library is platform-owned and never shares tables, storage prefixes or permissions with Site Assets.

### Immutability and versioning

1. An asset's file is written once and never overwritten or edited in place. There is no "replace file" endpoint.
2. Replacing an image means uploading a new asset and changing the Block reference. The previous asset stays untouched.
3. Future Published Versions reference asset `public_id`s. Immutability alone keeps old Published Versions stable.
4. Asset deletion is not part of Phase 2. When deletion is introduced, it must refuse (or defer) to delete an asset referenced by any draft Block state or any Published Version.

### Storage and validation

1. Files are stored on the private `local` disk under a server-generated key `site-assets/{site public_id}/{asset public_id}.{ext}`. The client file name is never used in the path; it is kept only as a display label (trimmed, max 255 characters).
2. Accepted formats are JPEG, PNG and WebP, verified by detected MIME type and decoded image dimensions. The extension is derived from the detected type. SVG and every other type are rejected. The maximum size is 10 MB and each side is limited to 10 000 px.
3. Files are served only through an authenticated, policy-checked controller (`view_site` in the current Workspace) with `X-Content-Type-Options: nosniff`. Public delivery for published sites (CDN/object storage, D-076) is decided with publishing and is not part of this ADR.
4. No URL import or remote fetch exists, so there is no SSRF surface.

### Permissions

- Uploading requires `manage_assets`.
- Choosing an existing Site Asset for an image field is a content change and requires `edit_content` through the normal Block state save.
- Viewing an asset file requires `view_site`.

## Consequences

- P2-013 can ship without a Workspace media model.
- Storage grows with replacements; cleanup of unreferenced assets is future work and must respect the deletion rule above.
- The Workspace Media Library remains an open product topic and needs its own decision before implementation.
