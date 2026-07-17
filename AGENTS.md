# AGENTS.md

## Stack
- **Backend**: Laravel 13, Filament v5, PHP 8.3+, Pest (tests), Pint (formatter), Larastan/PHPStan (static analysis)
- **Frontend**: React 19, TypeScript ~5.6, Tailwind CSS 3, Vite 7, Vitest (tests)
- **Package managers**: pnpm 11 (frontend), composer (backend)

## Commands

### Frontend (pnpm)
```bash
pnpm install              # install deps
pnpm run build            # production build -> public/build
pnpm run dev              # vite dev server (HMR)
pnpm run watch            # build + watch
pnpm run lint             # eslint resources/scripts/**/*.{ts,tsx}
pnpm run tsc              # tsc --noEmit (typecheck)
pnpm test                 # vitest run
pnpm run coverage         # vitest run --coverage
```

### Backend (composer)
```bash
composer install          # install deps
php artisan serve         # dev server
./vendor/bin/pint         # format PHP (pint:fix)
./vendor/bin/pint --test -v  # check PHP formatting (pint:check)
php artisan test          # run Pest tests
php artisan migrate       # run migrations
```

### Verify changes (run in order)
```bash
pnpm run lint && pnpm run tsc && pnpm test   # frontend
./vendor/bin/pint --test -v && php artisan test  # backend
```

## Path Aliases (tsconfig + vite)
- `@/*` -> `resources/scripts/*`
- `@definitions/*` -> `resources/scripts/api/definitions/*`
- `@feature/*` -> `resources/scripts/components/server/features/*`

## Architecture
- `app/` - Laravel backend (Models, Http, Filament admin, Services, Repositories)
- `resources/scripts/` - React frontend entry (`index.tsx`)
- `resources/scripts/reviactyl/` - UI components and theme engine
- `resources/scripts/components/` - page-level React components
- `resources/scripts/api/` - API client functions
- `resources/scripts/state/` - easy-peasy store
- `extensions/` - extension API (currently empty, `.gitkeep`)
- `routes/` - api-application, api-client, api-remote, auth, install
- `database/Seeders/eggs/` - game server egg definitions (JSON)
- `config/designify.php` - theme/design config
- `config/extensions.php` - extensions config

## Style
- PHP: Laravel preset via Pint, 4-space indent, LF line endings
- TS/JS: Prettier (120 width, 4-space tabs, single quotes, LF), ESLint with React + TS rules
- Tailwind: class-based forms strategy, custom `reviactyl` color vars via CSS custom properties

## Git Workflow
- All changes must be PRed to remote (`origin`); do not push directly to `master`
- Use `gh pr create` to open PRs; target branch is `master`
- Use `@warpfix` in PR comments for automated review:
  - `@warpfix explain` - explain why a change was made
  - `@warpfix fix` - generate a fix for an issue
  - `@warpfix test` - generate test cases
  - `@warpfix refactor` - suggest refactoring
  - `@warpfix security` - security analysis
  - `@warpfix performance` - performance analysis
  - `@warpfix help` - show all commands

## Quirks
- Vite config skips laravel-vite-plugin when `VITEST` env is set
- Tests use `happy-dom` environment (vitest)
- PHPStan level 5, excludes `app/Livewire`, `app/Repositories`
- CalVer versioning (YY.MM.MICRO)
- Node >= 22 required
