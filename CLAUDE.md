# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## About

Orchestral is a Laravel package for orchestrating long-running processes using musical metaphors (Conductor, Performer, Score). It provides supervisor-style management for commands like `queue:work`, `schedule:work`, and custom processes.

- PHP ^8.2, Laravel ^12.0, Symfony Process ^7.0
- Test framework: Pest PHP 3.0

## Commands

```bash
# Run all tests
vendor/bin/pest --ci

# Run a specific test suite
vendor/bin/pest --testsuite=Unit
vendor/bin/pest --testsuite=Feature
vendor/bin/pest --testsuite=Integration
vendor/bin/pest --testsuite=Security

# Run a single test file
vendor/bin/pest tests/Unit/Conductor/ConductorTest.php

# Run a single test by name
vendor/bin/pest --filter "test name"

# Code style check (dry run)
./vendor/bin/pint --test

# Auto-fix code style
./vendor/bin/pint
```

## Architecture

### Core Components (`src/Conductor/`)

- **Score** — Loads environment-specific configuration from `config/orchestral.php`. Builds properly escaped commands. Acts as the configuration source of truth.
- **Conductor** — Main singleton that manages the lifecycle of all Performers: `conduct()`, `pause()`, `encore()`, health monitoring, auto-restart on failure, and performance event logging.
- **Performer** — Wraps a single process instance using Symfony Process. Handles nohup detachment, PID tracking via `/proc`, memory limits, nice values, and resource monitoring.
- **ProcessRegistry** — Persists process state to Laravel Cache (Redis default, 7-day TTL). Recovers process handles across restarts.

### Service Provider

`OrchestralServiceProvider` registers `Score`, `ProcessRegistry`, and `Conductor` as singletons, publishes config/migrations, and registers 6 Artisan commands.

### Artisan Commands

| Command | Class | Purpose |
|---|---|---|
| `orchestral:conduct` | `ConductCommand` | Start configured processes |
| `orchestral:pause` | `PauseCommand` | Gracefully stop processes |
| `orchestral:encore` | `EncoreCommand` | Restart stopped processes |
| `orchestral:status` | `StatusCommand` | Show process status (supports `--json`) |
| `orchestral:instruments` | `InstrumentsCommand` | List configured processes |
| `orchestral:install` | `InstallCommand` | Interactive setup wizard |

### Storage

Two backends configured via `config/orchestral.php`:
- **Redis** (default) — Cache-based, no migration needed
- **Database** — Requires publishing and running migration stub (`create_orchestral_performances_table`)

The `Performance` Eloquent model tracks events (`performance_started`, `performer_restarted`, `performer_failed`, `memory_exceeded`, etc.) with scopes for `recent()`, `failures()`, `restarts()`, etc.

### Configuration Structure

Performances are defined per-environment under `config/orchestral.php`:

```php
'performances' => [
    'production' => [
        'queue-worker' => [
            'command' => 'queue:work',
            'performers' => 2,   // number of parallel instances
            'memory' => 128,     // MB limit before auto-restart
            'timeout' => 3600,
            'nice' => 0,         // process priority (-20 to 19)
            'options' => [],
        ],
    ],
],
```

## Security

Shell injection is a primary concern. Commands are built using `escapeshellarg()` in `Score::buildCommand()` and `Performer`. The Security test suite (`tests/Security/CommandInjectionTest.php`) covers injection via performance names, command options, env vars, path traversal, null bytes, PID injection, and serialization attacks. Any change to command-building logic must maintain these protections.

## Testing Setup

Tests use `Orchestra\Testbench` with SQLite in-memory database. The base `TestCase` extends `Orchestra\Testbench\TestCase`, runs package migrations, and loads the `OrchestralServiceProvider`. Test suites are defined in `phpunit.xml`: Unit, Feature, Integration, Security.

## Git Branching

This is an open source Laravel package following Gitflow conventions:

| Branch | Purpose |
|---|---|
| `main` | Latest stable release — do not target PRs here |
| `x.x` (e.g. `1.x`, `2.x`) | Active development branch for each major release line |

**Always base new branches off the current active development branch** (e.g. `1.x`), never off `main`. Check which version branch is active by looking at the remote branches (`git branch -r`) or the open PRs on GitHub before starting work.

```bash
git fetch origin
git checkout -b <type>/issue-<n>-<short-description> origin/<active-branch>
```

Branch naming:
- `fix/issue-3-hyphenated-performance-names` — bug fixes
- `feat/issue-5-some-new-feature` — new features
- `chore/issue-7-update-deps` — maintenance / non-functional changes

PRs should target the active development branch (`x.x`), not `main`.

## CI/CD

GitHub Actions runs tests on PHP 8.2 and 8.3 against Laravel 12 (both `prefer-stable` and `prefer-lowest`). A separate job runs `pint --test` for code style. The pint auto-fix workflow runs on non-main branches and auto-commits styled files.

## MCP: Context7

Use the Context7 MCP server to fetch up-to-date documentation for libraries used in this project. This is especially useful for Laravel, Symfony Process, Pest, and Orchestra Testbench.

```
# Resolve a library ID first, then query docs
mcp__context7__resolve-library-id  →  mcp__context7__query-docs
```

Example workflow when working with an unfamiliar API:
1. Call `resolve-library-id` with e.g. `"laravel"` or `"pestphp/pest"`
2. Call `query-docs` with the returned ID and a topic like `"queue workers"` or `"process management"`
