# Config Revisions — Design RFC

> **Feature**: Git-Backed Config Management
> **Codename**: TimeWarp
> **Status**: Design
> **Author**: opencode
> **Created**: 2026-07-17

---

## 1. Problem

Game server admins change config files constantly — tuning `server.properties`, editing plugin YAMLs, tweaking MOTD files. When a config change breaks the server, the only recovery options are:

- Restore a full backup (heavy, destructive, slow)
- Manually undo the change (requires remembering exactly what changed)
- Ask the hosting provider (slow, expensive)

There is no lightweight way to answer: **"Who changed this file, when, and can I undo it?"**

## 2. Solution

**Config Revisions** adds panel-side version control for server configuration files. The panel maintains a revision history for tracked files, enabling:

- **Auto-snapshots** on every file save through the panel editor
- **Manual snapshots** with user-written messages
- **Diff viewing** (inline + side-by-side) between any two revisions
- **One-click rollback** that writes old content back to the daemon
- **Config presets** — named, tagged snapshots that can be switched between
- **Watch patterns** — `.revitrack` file controls which files are tracked
- **Subuser attribution** — every revision tied to who made it

No git binary required. No daemon changes. No container modifications.

## 3. Architecture

### 3.1 Design Decisions

| Decision | Choice | Rationale |
|----------|--------|-----------|
| Storage backend | DB + filesystem | Pure Laravel. Portable. No git binary dependency. |
| Content storage | `storage/app/config-revisions/blobs/` | Content-addressable flat blob store. Deduplicated by SHA-256. |
| Snapshot trigger | Event listener on `FileController::write()` | Automatic, transparent to user. |
| Diff computation | Server-side PHP (native `xdiff` or fallback line-diff) | Keeps frontend simple. Returns unified diff JSON. |
| Watch patterns | `.revitrack` file in server root | Familiar `.gitignore`-like syntax. Zero config for common cases. |
| Rollback model | Non-destructive (creates new revision) | Preserves full history. Never loses data. |
| Storage strategy | Content-addressable + dedup + delta | Avoids storing duplicate file content across revisions. |

### 3.2 Storage Efficiency

**Problem**: Storing full file snapshots per revision scales badly. 10 files × 200 revisions × 1,000 servers ≈ 1TB worst case.

**Solution: Three-layer optimization**:

#### Layer 1: Content-Addressable Storage (CAS)

File content is stored by its SHA-256 hash, not by revision. If a file's content hasn't changed between two revisions, the same blob is referenced — not duplicated.

```
storage/app/config-revisions/
    └── blobs/
        ├── a1b2c3...  (content of "motd=Hello\n...")
        ├── d4e5f6...  (content of "max-players=20\n...")
        └── ...
```

`server_config_files` row points to blob hash, not a copy:
```
revision_id=123, file_path="server.properties", content_hash="a1b2c3..."
revision_id=124, file_path="server.properties", content_hash="a1b2c3..."  ← same blob, zero extra storage
```

**Savings**: If `server.properties` is only changed every 50 revisions, we store 1 copy instead of 50.

#### Layer 2: Only Store Changed Files

Each revision only stores files that actually changed from the previous revision. Unchanged files are inherited by reference.

```
Revision 123: { server.properties: "a1b2c3...", bukkit.yml: "d4e5f6..." }
Revision 124: { server.properties: "789abc..." }  ← only this file changed
```

Restoration walks the revision chain backward to reconstruct full snapshot. `ConfigRevisionService::getFullSnapshot(revisionId)` handles this:

```php
// Pseudocode
function getFullSnapshot(int $revisionId): array {
    $files = [];
    $current = $revisionId;

    while ($current && count($files) < $totalTrackedFiles) {
        $revision = Revision::find($current);
        foreach ($revision->files as $file) {
            $files[$file->file_path] ??= $file->content_hash; // first hit wins
        }
        $current = $revision->parent_id; // walk backward
    }

    return $files; // { "server.properties" => "a1b2c3...", ... }
}
```

`parent_id` on `server_config_revisions` links to the previous revision for fast traversal.

#### Layer 3: Automatic Retention Policy

| Policy | Default | Configurable |
|--------|---------|-------------|
| Max revisions per server | 200 | `PANEL_MAX_CONFIG_REVISIONS` |
| Max presets per server | 20 | `PANEL_MAX_CONFIG_PRESETS` |
| Max total storage per server | 100MB | `PANEL_CONFIG_REVISIONS_MAX_STORAGE` |
| Cleanup strategy | FIFO (oldest non-preset first) | Automatic |

Presets are **never auto-deleted**. When limit hit, oldest non-preset revision is pruned (its unique blobs deleted).

#### Realistic Storage Estimate

With CAS + delta:
```
50KB avg file × 3 changed files per revision × 200 revisions × 1,000 servers
= 300GB (vs 1TB without optimization)

With dedup (60% of blobs shared across revisions):
≈ 120GB for 1,000 servers, 200 revisions each
```

This is manageable. Most panels have <100 servers.

### 3.3 How It Works

```
User saves file in editor
        │
        ▼
FileController::write()
        │
        ├─── writes to daemon (existing)
        │
        └─── dispatches FileWritten event
                    │
                    ▼
          ConfigRevisionListener::handle()
                    │
                    ├─── checks if file matches watch patterns
                    │
                    ├─── fetches file content from daemon (already in memory)
                    │
                    ├─── computes diff from previous revision
                    │
                    ├─── stores revision in DB + filesystem
                    │
                    └─── done (no blocking)
```

### 3.4 Storage Layout

```
storage/app/config-revisions/
    └── blobs/
        ├── a1b2c3d4e5f6...  # SHA-256 of file content (raw bytes)
        ├── 789abcdef012...
        └── ...
```

All file content stored exactly once by SHA-256 hash. `server_config_files` table maps `(revision_id, file_path) → content_hash`. Restoration reads the hash from DB, fetches blob from disk.

No per-revision directories. No per-server directories. Single flat blob store. Cleanup = delete orphaned blobs.

## 4. Database Schema

### 4.1 `server_config_revisions`

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint (PK) | Auto-increment |
| `server_id` | bigint (FK) | References `servers.id` |
| `author_id` | bigint (FK) | References `users.id` |
| `message` | varchar(500) | Revision message (auto: "Auto-snapshot on save" or user-provided) |
| `hash` | char(40) | SHA-1 of the snapshot content (dedup + integrity) |
| `is_preset` | boolean | Whether this revision is a named preset |
| `preset_name` | varchar(100) | Preset name (nullable, unique per server) |
| `file_count` | integer | Number of tracked files in this revision |
| `created_at` | timestamp | When the snapshot was taken |

### 4.2 `server_config_files`

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint (PK) | Auto-increment |
| `revision_id` | bigint (FK) | References `server_config_revisions.id` |
| `file_path` | varchar(500) | Relative path within server (e.g., `server.properties`) |
| `content_hash` | char(64) | SHA-256 of file content |
| `content_length` | integer | Content length in bytes |
| `created_at` | timestamp | |

> Content stored on disk at `storage/app/config-revisions/{server_uuid}/{revision_hash}/{file_path}`

### 4.3 `server_config_watch_patterns`

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint (PK) | Auto-increment |
| `server_id` | bigint (FK) | References `servers.id` |
| `pattern` | varchar(255) | Glob pattern (e.g., `*.yml`, `server.properties`, `plugins/**`) |
| `created_at` | timestamp | |

> Default patterns (when no `.revitrack` exists): `*.properties`, `*.yml`, `*.yaml`, `*.json`, `*.toml`, `*.cfg`, `*.conf`, `*.ini`, `*.txt` (but not in `logs/`, `world/`)

## 5. API Endpoints

All endpoints under `/api/client/servers/{server}/config-revisions`.

### 5.1 List Revisions

```
GET /config-revisions
```

**Query params**: `page`, `per_page`, `preset_only` (boolean)

**Response**:
```json
{
    "object": "list",
    "data": [
        {
            "object": "config_revision",
            "attributes": {
                "id": 123,
                "hash": "a1b2c3...",
                "message": "Updated MOTD and max players",
                "author": { "uuid": "...", "username": "admin", "email": "..." },
                "file_count": 3,
                "is_preset": false,
                "preset_name": null,
                "files": ["server.properties", "plugins/essentials/config.yml"],
                "created_at": "2026-07-17T10:30:00Z"
            }
        }
    ]
}
```

### 5.2 Get Revision Detail

```
GET /config-revisions/{revision}
```

**Response**: Full revision with all file contents.

### 5.3 Get Revision Files

```
GET /config-revisions/{revision}/files
```

**Response**: List of tracked files with their content at this revision.

### 5.4 Get File at Revision

```
GET /config-revisions/{revision}/file?path=server.properties
```

**Response**: Plain text file content.

### 5.5 Compare Revisions

```
GET /config-revisions/{revision_a}/diff/{revision_b}
```

**Query params**: `path` (optional, specific file diff)

**Response**:
```json
{
    "object": "config_diff",
    "attributes": {
        "revision_from": 120,
        "revision_to": 123,
        "files": {
            "server.properties": {
                "status": "modified",
                "additions": 3,
                "deletions": 1,
                "hunks": [
                    {
                        "content": " @@ -1,3 +1,5 @@\n motd=Hello\n+max-players=20\n+difficulty=hard\n ...",
                        "old_start": 1,
                        "old_lines": 3,
                        "new_start": 1,
                        "new_lines": 5
                    }
                ]
            },
            "plugins/essentials/config.yml": {
                "status": "added",
                "additions": 45,
                "deletions": 0,
                "hunks": [...]
            }
        }
    }
}
```

### 5.6 Create Snapshot

```
POST /config-revisions
```

**Body**:
```json
{
    "message": "Before upgrading to Paper 1.21",
    "files": ["server.properties", "bukkit.yml", "spigot.yml"]
}
```

**Behavior**: Fetches current content of listed files from daemon, creates a new revision.

### 5.7 Revert to Revision

```
POST /config-revisions/{revision}/revert
```

**Body**:
```json
{
    "files": ["server.properties"],           // optional, default: all tracked files
    "restart_server": false,                   // optional, restart after revert
    "message": "Reverted to pre-upgrade config" // optional
}
```

**Behavior**:
1. Fetches file content from the target revision's snapshot
2. Writes each file back to the daemon via `DaemonFileRepository::putContent()`
3. Creates a new revision (the "revert" commit) — non-destructive
4. Optionally restarts server

### 5.8 Create/Manage Preset

```
POST /config-revisions/{revision}/promote
```

**Body**:
```json
{
    "name": "survival-mode"
}
```

**Behavior**: Tags the revision as a named preset. Unique per server.

```
DELETE /config-revisions/presets/{preset_name}
```

**Behavior**: Removes preset tag (revision still exists in history).

```
POST /config-revisions/presets/{preset_name}/activate
```

**Behavior**: Reverts all tracked files to this preset's snapshot. Creates a new revision.

### 5.9 Watch Patterns

```
GET /config-revisions/watch-patterns
```

**Response**: Current watch patterns for this server.

```
PUT /config-revisions/watch-patterns
```

**Body**:
```json
{
    "patterns": [
        "*.properties",
        "*.yml",
        "*.yaml",
        "*.json",
        "*.toml",
        "*.cfg",
        "plugins/**",
        "!plugins/.paper-remapped/**"
    ]
}
```

```
POST /config-revisions/watch-patterns/reset
```

**Behavior**: Resets to default patterns.

### 5.10 Diff Against Current

```
GET /config-revisions/{revision}/diff-current
```

**Behavior**: Compares a historical revision against the current live files on the daemon. Fetches current content, computes diff. Useful for checking "what has changed since this snapshot?"

## 6. Permissions

New permission constants in `App\Models\Permission`:

```php
public const ACTION_CONFIG_REVISION_READ = 'config-revision.read';
public const ACTION_CONFIG_REVISION_CREATE = 'config-revision.create';
public const ACTION_CONFIG_REVISION_REVERT = 'config-revision.revert';
public const ACTION_CONFIG_REVISION_PRESET = 'config-revision.preset';
public const ACTION_CONFIG_REVISION_MANAGE = 'config-revision.manage';
```

Permission group:

```php
'config-revision' => [
    'description' => 'Permissions that control access to server config revision history.',
    'keys' => [
        'read' => 'View config revision history and file diffs.',
        'create' => 'Create manual snapshots of config files.',
        'revert' => 'Revert config files to a previous revision.',
        'preset' => 'Create and activate config presets.',
        'manage' => 'Manage watch patterns and revision settings.',
    ],
],
```

## 7. Frontend

### 7.1 Route

New server route in `routes.ts`:

```typescript
{
    route: 'config-revisions/*',
    permission: 'config-revision.*',
    name: 'server.config-revisions',
    component: ConfigRevisionsContainer,
    icon: FaCodeBranch,
}
```

Placed in `server.management` section (between backups and schedules).

### 7.2 Components

```
resources/scripts/components/server/config-revisions/
├── ConfigRevisionsContainer.tsx        # Main page layout
├── RevisionListContainer.tsx           # Timeline list of revisions
├── RevisionRow.tsx                     # Single revision entry
├── DiffViewerContainer.tsx             # Side-by-side / inline diff view
├── DiffFileHeader.tsx                  # Per-file diff header (additions/deletions count)
├── CreateSnapshotModal.tsx             # Manual snapshot creation modal
├── RevertConfirmModal.tsx              # Revert confirmation modal
├── PresetManagerContainer.tsx          # Preset list + management
├── CreatePresetModal.tsx               # Create preset from revision
├── WatchPatternsContainer.tsx          # Watch pattern editor
├── CompareSelector.tsx                 # Two-revision selector for diff
└── FileAtRevisionViewer.tsx            # Single file content at a revision
```

### 7.3 UI Mockup (Description)

**Config Revisions page**:
- Top: Quick actions bar — "Create Snapshot" button, "Compare Revisions" button, "Presets" dropdown
- Middle: Vertical timeline of revisions (newest first)
  - Each entry: timestamp, author avatar + name, message, file count badge, changed files list
  - Clicking a revision expands to show the diff inline
  - Hover on a file shows a "View full file" button
- Right sidebar: Presets section (pinned presets with quick-activate button)

**Diff Viewer**:
- Tab switcher: "Inline" / "Side-by-side"
- File tabs at top (if multiple files changed)
- Syntax-highlighted diff with line numbers
- Green/red highlighting for additions/deletions
- "Revert this file" button per file

**Watch Patterns**:
- Editor with syntax highlighting for glob patterns
- Preview of currently matched files
- "Reset to defaults" button

### 7.4 API Client Functions

```
resources/scripts/api/server/config-revisions/
├── getRevisions.ts
├── getRevisionDetail.ts
├── getRevisionFiles.ts
├── getFileAtRevision.ts
├── compareRevisions.ts
├── diffAgainstCurrent.ts
├── createSnapshot.ts
├── revertToRevision.ts
├── getPresets.ts
├── promoteToPreset.ts
├── deletePreset.ts
├── activatePreset.ts
├── getWatchPatterns.ts
├── updateWatchPatterns.ts
└── resetWatchPatterns.ts
```

## 8. Backend Implementation

### 8.1 Models

- `App\Models\ServerConfigRevision` — Eloquent model for `server_config_revisions`
- `App\Models\ServerConfigFile` — Eloquent model for `server_config_files`

### 8.2 Services

- `App\Services\ConfigRevisions\ConfigRevisionService` — Core logic: create snapshot, compute diff, revert
- `App\Services\ConfigRevisions\DiffService` — Diff computation (uses `xdiff` extension if available, falls back to PHP line-diff)
- `App\Services\ConfigRevisions\WatchPatternService` — Pattern matching logic (glob → regex conversion)

### 8.3 Event Listener

- `App\Listeners\ConfigRevisionListener` — Listens for `FileWritten` event, auto-creates revision if file matches watch patterns

### 8.4 Controllers

- `App\Http\Controllers\Api\Client\Servers\ConfigRevisionController` — All API endpoints

### 8.5 Filament Admin Widget

- `App\Filament\Widgets\ConfigRevisionActivityWidget` — Shows recent config changes across all servers in admin dashboard

### 8.6 Jobs

- `App\Jobs\ConfigRevisions\CreateSnapshotJob` — Queue-able job for creating large snapshots (many files)
- `App\Jobs\ConfigRevisions\RevertJob` — Queue-able job for reverting many files at once
- `App\Jobs\ConfigRevisions\CleanupJob` — Scheduled job that prunes old revisions per retention policy, deletes orphaned blobs from disk. Runs daily.

### 8.7 Migration

```php
// 2026_07_17_000001_create_server_config_revisions_table.php

Schema::create('server_config_revisions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('server_id')->constrained()->cascadeOnDelete();
    $table->foreignId('author_id')->constrained('users');
    $table->foreignId('parent_id')->nullable()->constrained('server_config_revisions')->nullOnDelete();
    $table->string('message', 500)->default('Auto-snapshot');
    $table->char('hash', 40)->unique();
    $table->boolean('is_preset')->default(false);
    $table->string('preset_name', 100)->nullable()->unique();
    $table->unsignedInteger('file_count')->default(0);
    $table->timestamps();

    $table->index(['server_id', 'created_at']);
    $table->index(['server_id', 'is_preset']);
    $table->index('parent_id');
});

Schema::create('server_config_files', function (Blueprint $table) {
    $table->id();
    $table->foreignId('revision_id')->constrained('server_config_revisions')->cascadeOnDelete();
    $table->string('file_path', 500);
    $table->char('content_hash', 64);
    $table->unsignedInteger('content_length')->default(0);
    $table->timestamps();

    $table->index(['revision_id', 'file_path']);
});

Schema::create('server_config_watch_patterns', function (Blueprint $table) {
    $table->id();
    $table->foreignId('server_id')->constrained()->cascadeOnDelete();
    $table->string('pattern', 255);
    $table->timestamps();

    $table->unique(['server_id', 'pattern']);
});
```

## 9. Configuration

Add to `config/panel.php`:

```php
'config_revisions' => [
    'enabled' => env('PANEL_CONFIG_REVISIONS_ENABLED', true),
    'max_revisions_per_server' => env('PANEL_MAX_CONFIG_REVISIONS', 200),
    'max_presets_per_server' => env('PANEL_MAX_CONFIG_PRESETS', 20),
    'max_storage_per_server' => env('PANEL_CONFIG_REVISIONS_MAX_STORAGE', 100 * 1024 * 1024), // 100MB
    'auto_snapshot_on_write' => env('PANEL_AUTO_SNAPSHOT_ON_WRITE', true),
    'max_file_size' => env('PANEL_CONFIG_REVISION_MAX_FILE_SIZE', 1024 * 1024), // 1MB per file
    'storage_path' => env('PANEL_CONFIG_REVISIONS_PATH', storage_path('app/config-revisions/blobs')),
    'cleanup_presets' => false, // never auto-delete presets
],
```

## 10. Activity Logging

Events logged:

| Event | Properties |
|-------|------------|
| `server:config-revision.create` | `message`, `file_count`, `revision_hash` |
| `server:config-revision.revert` | `target_revision_hash`, `files`, `restart_server` |
| `server:config-revision.preset.create` | `preset_name`, `revision_hash` |
| `server:config-revision.preset.activate` | `preset_name`, `files_reverted` |
| `server:config-revision.preset.delete` | `preset_name` |
| `server:config-revision.watch-patterns.update` | `patterns` |

## 11. Edge Cases

| Case | Handling |
|------|----------|
| File deleted since snapshot | Revert creates the file. Diff shows as "deleted". |
| File too large to snapshot | Skip file, log warning. User can increase limit. |
| Daemon unreachable during revert | Queue job for retry. Show error in UI. |
| Duplicate content (no change) | Compare content hashes. Skip revision if identical. |
| Server reinstalled | Revisions preserved (they're panel-side). |
| Server transferred to another node | Revisions preserved (panel-side storage). |
| Concurrent edits by subusers | Last-write-wins for auto-snapshots. Manual snapshots always work. |
| `.revitrack` file edited manually | Panel re-reads on next snapshot. Changes take effect immediately. |

## 12. Future Enhancements

- **Git backend option**: Store revisions in a real git repo (panel server). Use `git log`, `git diff` for richer history. Opt-in via config.
- **Export to git**: Export full revision history to a git repo for archival.
- **Webhook integration**: Fire webhook on config changes (Discord, Slack).
- **Scheduled snapshots**: Integration with existing schedule system — snapshot before restarts.
- **Pre-update snapshots**: Auto-snapshot before plugin installs, egg updates, reinstalls.
- **Config validation**: Validate config files before applying (e.g., YAML syntax check, JSON parse).
- **Branching**: True branching (not just presets). Merge conflicts UI.
- **Collaborative editing**: Real-time multi-user editing with operational transforms.

## 13. Implementation Estimate

| Component | Effort |
|-----------|--------|
| Database migration + models | 1 day |
| Services (snapshot, diff, revert) | 2 days |
| API endpoints + tests | 2 days |
| Frontend components | 3 days |
| Event listener + auto-snapshot | 0.5 day |
| Permissions integration | 0.5 day |
| Filament admin widget | 0.5 day |
| Documentation + i18n | 1 day |
| **Total** | **~10 days** |

## 14. Files to Create/Modify

### New Files

```
database/migrations/2026_07_17_000001_create_server_config_revisions_table.php
app/Models/ServerConfigRevision.php
app/Models/ServerConfigFile.php
app/Services/ConfigRevisions/ConfigRevisionService.php
app/Services/ConfigRevisions/DiffService.php
app/Services/ConfigRevisions/WatchPatternService.php
app/Http/Controllers/Api/Client/Servers/ConfigRevisionController.php
app/Http/Requests/Api/Client/Servers/ConfigRevisions/CreateSnapshotRequest.php
app/Http/Requests/Api/Client/Servers/ConfigRevisions/RevertRequest.php
app/Http/Requests/Api/Client/Servers/ConfigRevisions/WatchPatternsRequest.php
app/Http/Requests/Api/Client/Servers/ConfigRevisions/PromotePresetRequest.php
app/Http/Requests/Api/Client/Servers/ConfigRevisions/ActivatePresetRequest.php
app/Transformers/Api/Client/ConfigRevisionTransformer.php
app/Listeners/ConfigRevisionListener.php
app/Jobs/ConfigRevisions/CreateSnapshotJob.php
app/Jobs/ConfigRevisions/RevertJob.php
app/Filament/Widgets/ConfigRevisionActivityWidget.php
resources/scripts/components/server/config-revisions/ (all TSX components)
resources/scripts/api/server/config-revisions/ (all API functions)
```

### Modified Files

```
routes/api-client.php                          # Add config-revision routes
app/Models/Permission.php                      # Add config-revision permissions
resources/scripts/routers/routes.ts            # Add config-revisions route
resources/scripts/api/definitions/             # Add translation keys
config/panel.php                               # Add config_revisions settings
lang/ (various)                                # Add i18n strings
```
