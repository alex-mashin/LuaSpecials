# AGENTS.md — LuaSpecials

Instructions for AI agents and human contributors working in this repository.

## What this repository is

**LuaSpecials** is a MediaWiki extension (by Alexander Mashin,
GPL-2.0-or-later) that exposes MediaWiki special *query* pages to Lua via
Scribunto as `mw.ext.luaSpecials.<specialPageName>`, plus one extra function
`mw.ext.luaSpecials.userGroups()`.

- `src/Hooks.php` — registers the Scribunto external library via the
  `ScribuntoExternalLibraries` hook.
- `src/ScribuntoLibrary.php` — the Scribunto library: builds one closure per
  `QueryPage::getPages()` entry (skipping pages disabled via
  `$wgDisableQueryPageUpdate` and permission-restricted pages), exposes them
  to Lua, and adds `userGroups()`. Page instances are resolved through
  `SpecialPageFactory::getPage()` (required since MW 1.42, when query pages
  grew required DI constructor parameters). Every function returns three
  values: rows, row count, and the querycache timestamp (`nil` when fresh).
- `src/mw.ext.luaSpecials.lua` — the Lua half of the interface (mandatory:
  Scribunto's `registerInterface()` loads this file and calls its
  `setupInterface()`, which installs the package as `mw.ext.luaSpecials`).
- `i18n/*.json` — messages for the extension description and the
  `$wgLuaSpecialsDefaultLimit` description (en, ru, qqq).
- No tests exist in this repository; `composer test` / `npm test` run only
  static analysis (lint + code style + message checks).

## Toolchain and versions

Two independent dev toolchains:

- **PHP** (`composer.json`): exact version constraints pin the tools. Note
  that `composer.lock` is gitignored here (`.gitignore`), so it exists only
  as a local artifact — `composer.json`'s exact constraints are the
  committed source of truth for PHP dev-tool versions.
  - `mediawiki/mediawiki-codesniffer` 38.0.0 — `phpcs`, configured in
    `.phpcs.xml` (MediaWiki standard; the ruleset is referenced by the
    `./vendor/...` path, so run it only after `composer install`).
  - `php-parallel-lint/php-parallel-lint` 1.2.0 (+ console-highlighter 0.5.0).
  - `mediawiki/minus-x` 1.1.0.
- **Node** (`package.json` / committed `package-lock.json` — authoritative
  for exact versions):
  - `grunt` 1.5.3 with `grunt-eslint` 23.0.0 +
    `eslint-config-wikimedia` 0.16.2 (`.eslintrc.json`), `grunt-stylelint`
    0.15.0 + `stylelint-config-wikimedia` 0.10.3 (`.stylelintrc.json`),
    `grunt-banana-checker` 0.9.0 (checks `i18n/` as declared by
    `MessagesDirs` in `extension.json`).

Runtime dependencies (not installable from this repo): MediaWiki ≥ 1.35.0 and
the Scribunto extension, declared in `extension.json` `requires`.

## How to run checks

All checks are static; there are no unit or integration tests.

```sh
# PHP toolchain (after `composer install`)
composer test   # parallel-lint . --exclude vendor --exclude node_modules
                #   + phpcs (-sp --cache) + minus-x check .
composer fix    # minus-x fix . + phpcbf (auto-fixes style)

# Node toolchain (after `npm install` or `npm ci`)
npm test        # grunt test = grunt eslint + stylelint + banana
grunt --fix     # eslint auto-fix (grunt option consumed by the eslint target)
```

## Coding conventions

Follow the conventions already present in the code; don't mix styles.

### PHP
- MediaWiki coding style, enforced by phpcs (MediaWiki standard, v38):
  tabs for indentation, single spaces inside parentheses in calls
  (`lcfirst( $special )`), `/** */` doc comments with `@param`/`@return`,
  `@const` doc annotation for class constants (see `Hooks.php`,
  `ScribuntoLibrary.php`).
- PSR-4: namespace `MediaWiki\Extension\LuaSpecials\` → `src/`
  (`AutoloadNamespaces` in `extension.json`); one class per file, class name
  = file name.
- Class constants follow MediaWiki style with a `@const` doc comment. Shared
  constants are `public const` on `Hooks` (`Hooks::EXT`, the single
  definition of the extension name — do not duplicate it in other classes).
- Configuration comes from `extension.json` `config` with
  `config_prefix: wgLuaSpecials` (→ `$wgLuaSpecialsDefaultLimit`, default 50),
  read via `MediaWikiServices::getInstance()->getMainConfig()`.
- Cross-version compatibility pattern used in this repo: pick the class by
  availability — `class_exists( 'QueryPage' ) ? 'QueryPage' :
  '\MediaWiki\SpecialPage\QueryPage'` (`ScribuntoLibrary.php`), because the
  global `QueryPage` alias is gone in MediaWiki master while the namespaced
  class only exists since 1.41. Reuse this pattern for any other
  namespaced-in-1.41 class; keep both branches.
- Scribunto base class shim (top of `ScribuntoLibrary.php`): the global
  `Scribunto_LuaLibraryBase` is declared directly in Scribunto 1.35–1.40
  (where no namespaced class exists — so never `use` the namespaced name at
  file top), aliased in 1.41–1.44, and gone in master. The shim must use the
  autoload-enabled `class_exists( \Scribunto_LuaLibraryBase::class )` +
  `class_alias( '…\LibraryBase', 'Scribunto_LuaLibraryBase' )` by FQN string:
  Scribunto maps the global name in its `AutoloadClasses` through 1.44, so
  autoload resolves it on old branches; a no-autoload
  `class_exists( …, false )` would wrongly fire on 1.35–1.40 and fatal.

### JavaScript (tooling only)
- 4-space indentation, `var`, wikimedia eslint preset (`Gruntfile.js` is the
  only first-party JS file).

### Lua
- Follow `src/mw.ext.luaSpecials.lua`: tabs, `function external.x()`, the
  standard Scribunto `setupInterface()` boilerplate (nil out `mw_interface`,
  register under `mw.ext.<name>`, set `package.loaded`).

### i18n
- Every new message key must exist in `i18n/en.json`, `i18n/qqq.json`, and
  `i18n/ru.json`; `grunt banana` validates this.

## Agent conventions (standing rules)

1. **Shell scripts stay strictly POSIX.** Shebang `#!/bin/sh`, `[ ]` tests,
   quoted variable expansions, `set -eux` in setup scripts. No bashisms
   (`[[`, `((`, arrays, `source`, `function` keyword, `$'...'`, etc.).
   Verify with `shellcheck` and `dash -n` where available. The repository
   currently contains no first-party shell scripts (the README install
   snippet is two POSIX-compatible commands); this rule binds any script
   added later.

2. **Validate installed tool versions.** After installing any tool, runtime,
   or dependency whose version matters, compare the installed version against
   the expected one and fail fast with a clear error on mismatch. Expected
   versions come from the declared places in "Toolchain and versions" above
   (committed `package-lock.json` / exact `composer.json` constraints for dev
   tools; `extension.json` `requires` for the MediaWiki/Scribunto runtime).

3. **Funnel dependency installs through a single declared place.** Dependencies
   must be installed via the manifest + lockfile pair that declares them —
   `composer.json` + `composer.lock` for PHP dev tools, `package.json` +
   `package-lock.json` for Node dev tools, `extension.json` `requires` for the
   wiki runtime — never via scattered ad-hoc install commands in build steps
   or scripts. (In this repo the `composer.lock` is not committed, so
   `composer.json`'s exact constraints are the declared place for PHP dev-tool
   versions; see "Toolchain and versions" above.)

## Known issues

Full findings, fix records, and the version map behind them are in `REVIEW.md`.
All defects found by the 2026-11-07 review (D1–D6) are fixed; behavioral
conventions to keep in mind when extending the code:

1. **Lua API contract** — every `mw.ext.luaSpecials.*` function returns
   `(rows, rowCount, cacheTimestamp)`; `cacheTimestamp` is non-nil only for
   miser-mode cached results (possibly stale, capped by
   `$wgQueryCacheLimit`). `limit = 0` means "no limit"; `nil`/`false` mean
   `$wgLuaSpecialsDefaultLimit` (which may itself be `false` = no limit).
2. **Permission model** — pages whose special page has a restriction (e.g.
   `Special:Unwatchedpages`) are not exposed to Lua at all: Lua modules run
   before any specific reader is known, so per-user checks are impossible.
   Do not add closure-level bypasses for this.
3. **Expensive-function accounting** — every DB-backed closure must call
   `$this->incrementExpensiveFunctionCount()`; closures therefore must not be
   `static` (they bind the library instance).
