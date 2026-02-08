---
name: AdminFixesParallelComplete
overview: Complete two-track plan to harden uploads/saving in the PHP backend while improving admin UX and error visibility in the frontend, with clear integration points.
todos:
  - id: backend-upload-validate
    content: Add size + MIME checks and upload error collection
    status: pending
  - id: backend-partial-save
    content: Allow JSON save with upload errors; pass $uploadErrors
    status: pending
    dependencies:
      - backend-upload-validate
  - id: frontend-error-ui
    content: Render per-card upload error badges/messages
    status: pending
    dependencies:
      - backend-partial-save
  - id: frontend-ux-polish
    content: Improve search, preview metadata, empty state
    status: pending
    dependencies:
      - frontend-error-ui
---

# Admin Page Fixes Plan (Parallelized, Detailed)

## Goals
- Prevent bad uploads and tighten file handling while keeping current JSON schema intact.
- Allow non-upload edits to save even if some uploads fail.
- Surface upload errors and improve search/preview UX on the admin page.

## Part A (Backend/PHP) – Upload Validation + Save Logic

### A1. Define constraints and helpers (top of `index.php`)
File: [`src/admin/index.php`](src/admin/index.php)
- Add near `$uploadDir`:
  - `$maxUploadBytes = 5 * 1024 * 1024;` (or agreed limit).
  - `$allowedExtensions = ['jpg','jpeg','png','gif','webp'];`
  - `$allowedMime = ['image/jpeg','image/png','image/gif','image/webp'];`
  - `$finfo = finfo_open(FILEINFO_MIME_TYPE);` (reuse for all uploads).
- Optional: `$uploadSubdir = date('Y-m');` and `mkdir($uploadDir.'/'.$uploadSubdir, 0755, true)` if you want subfolders.

### A2. Upload validation inside POST loop
- Where `$imageUploads` is processed, add checks in this order:
  1) Presence check: `tmp_name` not empty.
  2) PHP upload error code: `$_FILES['image_upload']['error'][$i] !== UPLOAD_ERR_OK`.
  3) Size check: `$_FILES['image_upload']['size'][$i] > $maxUploadBytes`.
  4) MIME check: `finfo_file($finfo, $_FILES['image_upload']['tmp_name'][$i])`.
  5) Extension check: use `pathinfo($name, PATHINFO_EXTENSION)` and compare to `$allowedExtensions`.
- If any check fails:
  - Set `$uploadErrors[$i] = 'Readable message here';`
  - Keep `$imagePath` as the existing `image` field.

### A3. File naming and safe paths
- Continue sanitized ID + random token naming:
  - `$safeId = preg_replace('/[^a-zA-Z0-9_-]/', '', $id);`
  - `$filename = $safeId . '-' . bin2hex(random_bytes(4)) . '.' . $ext;`
- Ensure target path uses only `$uploadDir` (and `$uploadSubdir` if used).
- On `move_uploaded_file` failure, set `$uploadErrors[$i]`.

### A4. Partial-save behavior and error summary
- Initialize `$uploadErrors = [];` and `$hasUploadErrors = false;`.
- On any upload error, set `$hasUploadErrors = true;`.
- Always build `$updated[]` for all items.
- JSON save only fails if encode/write fails. Upload errors should not block save.
- Set a summary message when `$hasUploadErrors`:
  - `$error = 'Some uploads failed. Review item warnings.';`
- Keep `$success` if JSON write succeeds, even if `$hasUploadErrors` is true.

### A5. Clean up
- Close `finfo` handle after POST (`finfo_close($finfo);`).
- Ensure `$uploadNotice` remains for non-writable directory.

## Part B (Frontend/UX) – Error Visibility + Search/Preview Polish

### B1. Per-item upload error display
File: [`src/admin/index.php`](src/admin/index.php)
- Inside each `.admin-card`, add:
  - An error badge/alert area that renders when `isset($uploadErrors[$index])`.
  - Include the readable message and a short “Upload failed” label.
- At top of form, if `$hasUploadErrors`, show a non-blocking summary alert.

### B2. Image upload preview and metadata
- Extend existing JS:
  - Show file name + size near the input (`input.files[0].name`, `size`).
  - If size exceeds `$maxUploadBytes`, show a warning class (display only).
  - Add a “Reset preview” button to restore the current image URL (stored in `data-current-src`).

### B3. Search improvements
- Extend `data-search` to include `voltage`, `description`, and `features` (stringify features array).
- Add an empty-state element:
  - Hidden by default, shown when `visible === 0`.

### B4. CSS updates
File: [`src/admin/admin.css`](src/admin/admin.css)
- Add styles for:
  - `.admin-upload-error` or similar badge
  - `.admin-file-meta` (file name/size)
  - `.admin-empty-state`
  - Warning state for oversized file hint
  - Minor toolbar spacing adjustments if needed

## Integration Contract
- Backend provides `$uploadErrors` and `$hasUploadErrors` to template scope.
- Frontend reads `$uploadErrors[$index]` inline and uses it for per-card messages.
- JSON output unchanged; `image` stays a public URL string.

## Step-by-step Execution (Parallelizable)

### Track A: Backend (PHP)
1) Add constants/constraints and MIME helper handle.
2) Implement upload validation sequence and `$uploadErrors`.
3) Modify save flow for partial-save with summary error.
4) Close `finfo` handle and verify `$uploadNotice`.

### Track B: Frontend (UI/JS/CSS)
1) Add per-card error UI in the template.
2) Add empty-state element + search toggle.
3) Extend JS for file meta + reset preview + warnings.
4) Add CSS for new elements and warning state.

## Implementation Todos
- `backend-upload-validate` : Add size + MIME checks, error collection, and safe naming in `index.php`.
- `backend-partial-save` : Save JSON even with upload errors; add `$hasUploadErrors` summary.
- `frontend-error-ui` : Render per-card upload errors + top summary in `index.php` + `admin.css`.
- `frontend-ux-polish` : Search expansion, empty state, file meta, reset preview in `index.php` + `admin.css`.