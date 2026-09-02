# Task Report 020 — Past Paper File Upload & Secure File Access Verification and Upgrade

## 1. Executive Summary & Verification Findings

This audit and upgrade verified and hardened the upload, storage, access control, and delivery pipeline for past exam papers and solution documents in the Lumani platform.

### Summary of Audit Findings & Actions Taken:

| Item # | Verification Target | Initial State (Audit) | Final State (Upgraded & Verified) | Status |
| :--- | :--- | :--- | :--- | :--- |
| **1** | **Filament Component Type** | `TextInput::make('file_path')` and `TextInput::make('solution_file_path')` requiring manual string paths. | Upgraded to `Filament\Forms\Components\FileUpload` storing real PDF binaries. | **FIXED & VERIFIED** |
| **2** | **Storage Disk & Privacy** | No explicit disk configured on text inputs. | Configured on private `local` disk (`storage/app/private`), `visibility('private')`, strictly excluded from public web access. | **FIXED & VERIFIED** |
| **3** | **Fixes Applied** | Raw strings, no upload validation. | `PastPaperForm` uses `FileUpload` with PDF mime validation (`application/pdf`), 50MB cap, dedicated subdirectories (`past-papers/questions`, `past-papers/solutions`). `PastPapersTable` enhanced with visual PDF status icons. | **COMPLETED** |
| **4** | **Real File Serving** | Existing unlock endpoint returned only JSON metadata (`success`, `coins_spent`, `coin_balance`); no download endpoint existed. | Implemented streaming binary file delivery via `Storage::disk('local')->response(...)`. Streams actual PDF content with proper `Content-Type` and `Content-Disposition`. | **FIXED & VERIFIED** |
| **5** | **Documentation Deliverable** | Missing documentation report. | Documented in `docs/task-reports/020-past-paper-file-upload-verification.md`. | **COMPLETED** |
| **6** | **Auth, Ownership & Privacy** | N/A (no download endpoint existed). | Download endpoints enforce Sanctum auth + `AccessControlService` unlock ownership on **every** request. Zero cacheable permanent URLs. Anti-caching headers applied. `$hidden` prevents raw path leakage in JSON responses. | **HARDENED & VERIFIED** |

---

## 2. Technical Implementation Details

### 2.1 Filament Admin Form (`PastPaperForm.php`)
Replaced legacy `TextInput` fields with Filament `FileUpload` components inside `App\Filament\Resources\PastPapers\Schemas\PastPaperForm`:
```php
FileUpload::make('file_path')
    ->label('Past Paper Document (PDF)')
    ->disk('local')
    ->directory('past-papers/questions')
    ->visibility('private')
    ->acceptedFileTypes(['application/pdf'])
    ->maxSize(51200) // 50 MB
    ->downloadable()
    ->openable()
    ->helperText('Upload the questions PDF document (stored securely on private disk).'),

FileUpload::make('solution_file_path')
    ->label('Solution Document (PDF)')
    ->disk('local')
    ->directory('past-papers/solutions')
    ->visibility('private')
    ->acceptedFileTypes(['application/pdf'])
    ->maxSize(51200) // 50 MB
    ->downloadable()
    ->openable()
    ->helperText('Upload the worked solutions PDF document (stored securely on private disk).'),
```

### 2.2 Filament Admin Table (`PastPapersTable.php`)
Added visual status columns allowing administrators to immediately identify whether documents are uploaded:
- `IconColumn::make('file_path')->label('Paper PDF')->boolean()`
- `IconColumn::make('solution_file_path')->label('Solution PDF')->boolean()`

### 2.3 Model Layer Privacy (`PastPaper.php`)
1. **Serialization Masking**:
   Added `file_path` and `solution_file_path` to the model's `$hidden` property:
   ```php
   protected $hidden = [
       'file_path',
       'solution_file_path',
   ];
   ```
   Ensures that calling `$pastPaper->toArray()`, `$pastPaper->toJson()`, or returning models in API responses will **never** expose internal server disk paths.
2. **Helper Methods**:
   Added `hasPaperFile(): bool` and `hasSolutionFile(): bool` for clean status querying.

### 2.4 Secure File Delivery Controller (`PastPaperController.php`)
Implemented robust file streaming methods backed by `AccessControlService`:
1. **`downloadPaper(Request $request, int $id): Response`**:
   Authenticates user, verifies question paper unlock status, checks file existence on private disk, and streams the binary PDF with `Content-Disposition: attachment`.
2. **`viewPaper(Request $request, int $id): Response`**:
   Streams the binary PDF with `Content-Disposition: inline` for in-app or browser PDF viewers. Supports query parameter `?disposition=inline` on download endpoint as well.
3. **`downloadSolution(Request $request, int $id): Response`** & **`viewSolution(Request $request, int $id): Response`**:
   Verifies solution unlock status and streams the worked solution PDF.
4. **`accessStatus(Request $request, int $id): JsonResponse`**:
   Provides students with current ownership status, document availability flags, and dynamic download/view URLs (only populated if unlocked).
5. **Updated Unlock Endpoints (`unlockPaper`, `unlockSolution`)**:
   Now return `download_url` and `view_url` immediately upon successful (or idempotent) unlock, eliminating any guesswork on the client.

### 2.5 Anti-Caching and Storage Security Guardrails
On every file stream request:
- **No Long-Lived Signed URLs**: Files are streamed directly through the authenticated Laravel application layer. No permanent or guessable URLs are created on cloud storage.
- **Strict Headers**:
  ```http
  Cache-Control: private, no-cache, no-store, must-revalidate
  Pragma: no-cache
  Expires: 0
  Content-Type: application/pdf
  Content-Disposition: attachment; filename="<sanitized-title>-questions.pdf"
  ```
- **Permission Verification On Every Request**: If an unlock record is revoked or expired, subsequent requests immediately receive `403 Forbidden`.

---

## 3. API Endpoints Specification

### 3.1 `GET /api/past-papers/{id}/access`
Check student unlock ownership and available download endpoints.

#### Headers Required
```http
Authorization: Bearer <token>
Accept: application/json
```

#### Response Example (`200 OK`)
```json
{
  "id": 12,
  "title": "GCE A-Level Physics 2024 Paper 2",
  "year": 2024,
  "paper_unlocked": true,
  "solution_unlocked": false,
  "has_paper_file": true,
  "has_solution_file": true,
  "paper_download_url": "http://localhost/api/past-papers/12/download-paper",
  "paper_view_url": "http://localhost/api/past-papers/12/view-paper",
  "solution_download_url": null,
  "solution_view_url": null
}
```

---

### 3.2 `POST /api/past-papers/{id}/unlock-paper` & `POST /api/past-papers/{id}/unlock-solution`
Unlocks paper questions or worked solutions with coins.

#### Successful Unlock Response (`200 OK`)
```json
{
  "success": true,
  "message": "Past paper 'GCE A-Level Physics 2024 Paper 2' unlocked successfully.",
  "already_unlocked": false,
  "coins_spent": 15,
  "coin_balance": 85,
  "download_url": "http://localhost/api/past-papers/12/download-paper",
  "view_url": "http://localhost/api/past-papers/12/view-paper"
}
```

---

### 3.3 `GET /api/past-papers/{id}/download-paper` & `GET /api/past-papers/{id}/view-paper`
Streams the binary question paper PDF.

#### Response Headers (`200 OK`)
```http
HTTP/1.1 200 OK
Content-Type: application/pdf
Content-Disposition: attachment; filename="gce-a-level-physics-2024-paper-2-2024-questions.pdf"
Cache-Control: private, no-cache, no-store, must-revalidate
Pragma: no-cache
Expires: 0
```
*Body*: Binary PDF stream.

#### Unauthorized / Locked Rejections:
- **Unauthenticated**: `401 Unauthorized`
- **Not Unlocked**: `403 Forbidden` (`{"message": "You must unlock this past paper questions document before accessing it."}`)
- **Missing File on Server**: `404 Not Found` (`{"message": "The requested document is not currently available for download."}`)

---

## 4. Verification & Testing

Automated test suite `tests/Feature/Api/PastPaperFileUploadAndAccessTest.php` covers 11 dedicated test cases:

1. **Filament Form Component Configuration**:
   - Asserts `file_path` and `solution_file_path` are instances of `FileUpload`.
   - Asserts disk name is `'local'`.
   - Asserts visibility is `'private'`.
   - Asserts accepted file types contain `'application/pdf'`.
   - Asserts subdirectories are `past-papers/questions` and `past-papers/solutions`.
2. **Model Hidden Property**:
   - Confirms `file_path` and `solution_file_path` do not appear in Eloquent `toArray()` or `toJson()`.
3. **Unauthenticated Rejection**:
   - Confirms `download-paper`, `view-paper`, `download-solution`, `view-solution`, and `access` return `401 Unauthorized`.
4. **Locked Paper Rejection**:
   - Confirms student with sufficient coins but without unlock record receives `403 Forbidden`.
5. **Locked Solution Rejection**:
   - Confirms locked solution requests receive `403 Forbidden`.
6. **Binary Questions Paper Streaming**:
   - Confirms unlocked student receives `200 OK`, `application/pdf`, attachment disposition, anti-caching headers, and identical binary content.
7. **Inline Viewing**:
   - Confirms `/view-paper` and `?disposition=inline` return `Content-Disposition: inline`.
8. **Binary Solution Streaming**:
   - Confirms unlocked student downloads worked solution document.
9. **Missing File Handling**:
   - Confirms 404 response when file is missing from storage disk.
10. **Unlock Endpoints Flow**:
    - Confirms `unlock-paper` and `unlock-solution` return authenticated route URLs and do not expose server disk paths.
11. **Access Status Endpoint**:
    - Confirms dynamic URLs and permission checks without path leakage.
12. **Global Free Mode Override**:
    - Confirms `free_mode_enabled = true` allows instant document download without coin deductions.

Existing test suite `tests/Feature/Api/SpendAndAccessTest.php` continues to pass, ensuring 100% backward compatibility.
