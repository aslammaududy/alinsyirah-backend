# Feature: Student Profile Photo Upload to Cloudflare R2

**Phase:** 3
**Created:** 2026-07-07
**Status:** defined

## Description

Add profile photo upload functionality for students. Photos are stored on Cloudflare R2 (S3-compatible object storage). Students can upload, update, and delete their profile photo. The photo URL is persisted in the students table.

## Current Codebase State

- **Student model:** `app/Models/Student.php` — fields: nis, name, school_class, parent_name, parent_phone, parent_email, monthly_fee, status
- **StudentController:** `app/Http/Controllers/Api/StudentController.php` — standard CRUD (index, store, show, update, destroy)
- **StudentResource:** `app/Http/Resources/StudentResource.php` — returns all student fields
- **Migration:** `database/migrations/2026_06_25_104750_create_students_table.php`
- **Filesystem config:** `config/filesystems.php` — has local, public, s3 disks; no R2 disk configured
- **No existing R2 integration** — this is the first Cloudflare R2 usage in this project

## Acceptance Criteria

(to be defined in /fd-discuss)

## Out of Scope

(to be defined in /fd-discuss)
