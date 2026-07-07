# Discussion: Student Profile Photo Upload to Cloudflare R2

**Phase:** 3
**Date:** 2026-07-07
**Topic:** Student Profile Photo Upload to Cloudflare R2

## Preflight Evidence Used

- Tech stack: Laravel 13 / PHP 8.4 / SQLite / Sanctum / Pest v4
- Prior decisions loaded: No (first discussion for this phase)
- Questions suppressed by evidence: 5 (no existing upload, no R2 config, Student model structure, controller pattern, testing conventions — all answered by exploration)

## Decisions

D-01: [Scope] — Upload + Update + Delete with dedicated endpoints (`POST /api/students/{id}/photo`, `DELETE /api/students/{id}/photo`) (Keeps file handling separate from text field updates, single-responsibility)
D-02: [Photo Constraints] — 2 MB max, jpeg/png/webp formats (Conservative size, modern format support)
D-03: [Storage Path] — `students/{student_id}/{uuid}.{ext}` (Collision-proof, easy per-student cleanup)
D-04: [R2 Configuration] — Separate R2 env vars: `R2_ENDPOINT`, `R2_ACCESS_KEY_ID`, `R2_SECRET_ACCESS_KEY`, `R2_BUCKET` (No conflict with future AWS usage)
D-05: [Test Coverage] — Full test suite in `StudentPhotoTest.php` with 6-8 tests covering happy path + error cases

## Answered Recommendations

RQ-01: What photo operations do you need?
  Recommendation: Dedicated endpoints for upload, update, delete (option 2)
  User choice: Option 2 (Upload + Update + Delete)
  Rationale: Cleanest separation of concerns
  Asked by: discusser
  Stage: discuss
  Timestamp: 2026-07-07T13:40:00Z

RQ-02: What file size limit and image formats should be allowed?
  Recommendation: 2 MB max, jpeg/png/webp (option 1)
  User choice: Option 1 (2 MB max)
  Rationale: Standard practice for profile photos
  Asked by: discusser
  Stage: discuss
  Timestamp: 2026-07-07T13:40:30Z

RQ-03: How should uploaded photos be named and organized in R2?
  Recommendation: `students/{id}/{uuid}.{ext}` (option 1)
  User choice: Option 1
  Rationale: Collision-proof, easy per-student cleanup
  Asked by: discusser
  Stage: discuss
  Timestamp: 2026-07-07T13:41:00Z

RQ-04: How should Cloudflare R2 credentials be managed?
  Recommendation: Separate R2 env vars (option 1)
  User choice: Option 1
  Rationale: Cleanest, no conflict with future real AWS usage
  Asked by: discusser
  Stage: discuss
  Timestamp: 2026-07-07T13:41:30Z

RQ-05: What test coverage do you expect?
  Recommendation: Full test suite (option 1)
  User choice: Option 1 (Full test suite)
  Rationale: Comprehensive coverage for upload, update, delete, and error cases
  Asked by: discusser
  Stage: discuss
  Timestamp: 2026-07-07T13:42:00Z

## Suppressed Questions

- "What tech stack does this project use?" → answered by: `.codebase/STACK.md`
- "Is there existing file upload functionality?" → answered by: `@code-explorer` found only Excel import
- "Is there existing R2/Cloudflare integration?" → answered by: `@code-explorer` found none
- "What does the Student model look like?" → answered by: `@code-explorer` found full model structure
- "How are tests structured?" → answered by: `.codebase/TESTING.md` (Pest v4, LazilyRefreshDatabase, factory-based)

## Open Questions

- None — all required topics covered

## Acceptance Criteria (Derived from Decisions)

1. `POST /api/students/{id}/photo` — Uploads photo to R2, returns updated student with `photo_url`
2. `POST /api/students/{id}/photo` — Replaces existing photo (deletes old from R2, uploads new)
3. `DELETE /api/students/{id}/photo` — Deletes photo from R2, sets `photo_url` to null
4. Validation: 2 MB max, jpeg/png/webp only
5. Storage path: `students/{student_id}/{uuid}.{ext}`
6. R2 disk configured with separate env vars
7. `photo_url` column added to students table (nullable)
8. StudentResource includes `photo_url` in response
9. `StudentPhotoTest.php` with 6-8 tests passing

## Out of Scope

- Image resizing/thumbnails (store original only)
- Multiple photos per student (single profile photo)
- Image cropping/optimization on upload
- R2 bucket creation/management (assumed pre-existing)

## Next Steps

Workflow class: `standard`
Next step: `/fd-plan` — create implementation plan from these decisions
