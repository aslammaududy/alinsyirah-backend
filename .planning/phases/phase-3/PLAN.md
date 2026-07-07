# Plan: Student Profile Photo Upload to Cloudflare R2

**Phase:** 3
**Date:** 2026-07-07
**Status:** draft

## Decision Trace

| Decision | Plan Task |
|----------|-----------|
| D-01: Upload + Update + Delete endpoints | Task 2, Task 4 |
| D-02: 2 MB max, jpeg/png/webp | Task 3 |
| D-03: `students/{id}/{uuid}.{ext}` path | Task 4 |
| D-04: Separate R2 env vars | Task 1 |
| D-05: Full test suite | Task 6 |

## Wave Structure

### Wave 1: Foundation (parallel, no dependencies)

**Task 1: Configure R2 disk** <action trace="D-04">
- Add `r2` disk to `config/filesystems.php` using S3 driver
- Add env vars: `R2_ENDPOINT`, `R2_ACCESS_KEY_ID`, `R2_SECRET_ACCESS_KEY`, `R2_BUCKET`
- Add `R2_URL` for public URL generation
- Files: `config/filesystems.php`
- Verification: Config loads without error

**Task 2: Add `photo_url` column** <action trace="D-01">
- Create migration: `add_photo_url_to_students_table`
- Column: `photo_url`, string, nullable, after `status`
- Files: `database/migrations/*_add_photo_url_to_students_table.php`
- Verification: Migration runs cleanly

**Task 3: Create UploadPhotoRequest** <action trace="D-02">
- Create `app/Http/Requests/UploadPhotoRequest.php`
- Validation: `photo` required, file, image:jpeg,png,webp, max:2048
- `authorize()` returns true
- Files: `app/Http/Requests/UploadPhotoRequest.php`
- Verification: Request class instantiates

### Wave 2: Implementation (depends on Wave 1)

**Task 4: Implement photo upload/update/delete** <action trace="D-01, D-03">
- Update `app/Models/Student.php`: add `photo_url` to `$fillable`
- Add `uploadPhoto(Student $student, UploadedFile $file): Student` method
  - Generate path: `students/{student_id}/{uuid}.{extension}`
  - Store to `r2` disk
  - Delete old photo from R2 if exists
  - Update `photo_url` on student
- Add `deletePhoto(Student $student): void` method
  - Delete photo from R2
  - Set `photo_url` to null
- Add `uploadPhoto` and `deletePhoto` methods to `StudentController`
- Files: `app/Models/Student.php`, `app/Http/Controllers/Api/StudentController.php`
- Verification: Methods exist and are callable

**Task 5: Update StudentResource and routes** <action trace="D-01">
- Add `photo_url` to `StudentResource::toArray()` response
- Add routes: `POST /api/students/{student}/photo`, `DELETE /api/students/{student}/photo`
- Files: `app/Http/Resources/StudentResource.php`, `routes/api.php`
- Verification: Routes registered, resource returns photo_url

### Wave 3: Testing & Polish (depends on Wave 2)

**Task 6: Write StudentPhotoTest** <action trace="D-05">
- Create `tests/Feature/StudentPhotoTest.php`
- Tests (6-8):
  1. `it uploads a profile photo successfully`
  2. `it replaces an existing photo on re-upload`
  3. `it deletes a profile photo`
  4. `it rejects file larger than 2mb`
  5. `it rejects non-image file type`
  6. `it rejects unsupported image format (gif)`
  7. `it returns 404 for non-existent student`
  8. `it requires authentication`
- Mock R2 storage in tests (use `Storage::fake('r2')` or `Http::fake()`)
- Files: `tests/Feature/StudentPhotoTest.php`
- Verification: All tests pass

**Task 7: Update StudentFactory** <action trace="D-05">
- Add optional `photo_url` state to `StudentFactory`
- Files: `database/factories/StudentFactory.php`
- Verification: Factory creates student with photo_url when state used

## Dependencies

```
Wave 1 (parallel):
  Task 1 (R2 config) ──┐
  Task 2 (migration) ──┼── Wave 2
  Task 3 (FormRequest) ┘

Wave 2 (parallel):
  Task 4 (controller + model) ──┐
  Task 5 (resource + routes) ───┘── Wave 3

Wave 3 (sequential):
  Task 6 (tests)
  Task 7 (factory update)
```

## Files Modified

| File | Task | Change |
|------|------|--------|
| `config/filesystems.php` | 1 | Add `r2` disk |
| `database/migrations/*_add_photo_url_to_students_table.php` | 2 | New migration |
| `app/Http/Requests/UploadPhotoRequest.php` | 3 | New form request |
| `app/Models/Student.php` | 4 | Add `photo_url` to fillable, add photo methods |
| `app/Http/Controllers/Api/StudentController.php` | 4 | Add uploadPhoto/deletePhoto |
| `app/Http/Resources/StudentResource.php` | 5 | Add `photo_url` |
| `routes/api.php` | 5 | Add photo routes |
| `tests/Feature/StudentPhotoTest.php` | 6 | New test file |
| `database/factories/StudentFactory.php` | 7 | Add photo_url state |

## Verification

After all tasks complete:
1. `php artisan migrate` — runs migration cleanly
2. `php artisan test --compact --filter=StudentPhotoTest` — all tests pass
3. `php artisan route:list --path=students` — photo routes visible
4. `vendor/bin/pint --dirty --format agent` — code formatted

## Out of Scope (per DISCUSS.md)

- Image resizing/thumbnails
- Multiple photos per student
- Image cropping/optimization
- R2 bucket creation/management
