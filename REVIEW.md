# REVIEW.md — LuaSpecials static review

Review date: 2026-11-07. Fixes applied: 2026-11-07 (**[FIXED]** markers below).
Scope: first-party source only (`src/`, `i18n/`, manifests, lint configs,
`Gruntfile.js`, `README.md`). Static review — nothing was built, run, or
installed at review time. Framework-behavior claims were verified against
MediaWiki and Scribunto sources (REL1_35, REL1_41, REL1_42, REL1_43, REL1_44,
master branches).

Defects and simplification opportunities are intentionally kept in separate
sections: defects are things that are wrong, simplifications are things that
work but are needlessly complex.

---

## Defects

### D1 (high) — Legacy Scribunto parent class breaks on Scribunto master — [FIXED]

`src/ScribuntoLibrary.php:6` (pre-fix) — `class ScribuntoLibrary extends
\Scribunto_LuaLibraryBase`.

Verified against Scribunto sources: the global alias
`class_alias( LibraryBase::class, 'Scribunto_LuaLibraryBase' )` exists in
Scribunto REL1_43 and REL1_44 but is **removed in Scribunto master**, where
only the namespaced `LibraryBase` remains. On a wiki running master, hook
registration would fatal on the missing parent class.

Version map verified while fixing:
- Scribunto 1.35–1.40: global `abstract class Scribunto_LuaLibraryBase`
  declared directly (1.35 path `includes/engines/LuaCommon/LibraryBase.php`);
  **no namespaced class exists** → a `use` import of the namespaced class
  would itself be fatal on these branches; the shim must alias by FQN *string*.
- Scribunto 1.41–1.44: namespaced `LibraryBase` + global `class_alias`.
- Scribunto master: namespaced only; no alias anywhere in the tree.
- Crucially, the global class name is mapped in Scribunto's own
  `extension.json` `AutoloadClasses` on 1.35–1.44, so an **autoload-enabled**
  `class_exists()` both loads the global class on old branches (and skips the
  shim) and fails on master (shim fires; the FQN string then autoloads the
  namespaced class). A no-autoload `class_exists( ..., false )` would
  wrongly fire on old branches and fatal there.

**Fix applied:** shim before the class declaration:

```php
if ( !class_exists( \Scribunto_LuaLibraryBase::class ) ) {
	class_alias(
		'MediaWiki\Extension\Scribunto\Engines\LuaCommon\LibraryBase',
		'Scribunto_LuaLibraryBase'
	);
}
```

### D2 (high) — Permission bypass for restricted query pages — [FIXED]

The closures called `new $class( $special )` + `$page->doQuery()` directly.
`QueryPage::execute()` (REL1_35 verified) calls `checkPermissions()`;
`doQuery()` skips it. `SpecialUnwatchedPages` (restriction `unwatchedpages`,
REL1_35 verified) was readable by any Lua module.

While fixing, a second, larger problem surfaced: many core QueryPages got
**required DI constructor parameters in MW 1.42** (verified: `SpecialLongPages`
needs `NamespaceInfo`, `IConnectionProvider`, `LinkBatchFactory` in 1.42–master),
so `new $class( $special )` fails for them. Core's own
`maintenance/updateSpecialPages.php` resolves the instances through
`SpecialPageFactory::getPage( $name )` instead — which works from 1.35 (the
factory's service name dates to 1.35) and constructs pages with their services.

**Fix applied:** build each function's `QueryPage` instance once at
registration via `SpecialPageFactory::getPage()`, then:
- skip pages that are not `instanceof QueryPage` or whose
  `getRestriction() !== ''` (permission gate — Lua modules run before any
  specific reader is known, so per-user checks are impossible; restricted
  pages are excluded entirely), and
- tolerate factory failures (`try/catch Throwable → skip`) so one broken
  third-party registration cannot break library setup.

### D3 (medium) — No Scribunto expensive-function accounting — [FIXED]

No closure called `incrementExpensiveFunctionCount()`, so a Lua module could
issue unbounded DB-backed queries per parse, bounded only by engine CPU/time
limits. `incrementExpensiveFunctionCount()` is verified present on the base
class since 1.35 (`$wgExpensiveParserFunctionLimit`).

**Fix applied:** each generated closure calls
`$this->incrementExpensiveFunctionCount()` first. This required dropping
`static` from the closures (a `static` closure has no `$this` — an error in
my first fix attempt, caught by self-review).

### D4 (medium) — Falsiness-based limit handling — [FIXED]

`$limit ?: self::$limit` collapsed everything falsy from Lua (`nil`, `false`,
`0`) to `$wgLuaSpecialsDefaultLimit`; there was no way to request an unlimited
query from Lua. (Config `false` *did* work as documented — verified
`false ?: false → false` → `doQuery( …, false )` → unlimited.)

**Fix applied (documented convention):** `limit = 0` from Lua now means
"no limit" (`$limit === 0 ? false : ( $limit ?: self::$limit )`); `nil`/`false`
keep meaning "default". Documented in README with an example.

### D5 (low) — Cached-path results may be stale or truncated (miser mode) — [FIXED]

When `doQuery()` serves `fetchFromCache()` (`$wgMiserMode` + expensive page),
rows are only as fresh as the last `updateSpecialPages.php` run and capped by
`$wgQueryCacheLimit` (default 10 000); Lua had no way to tell.

**Fix applied:** every function now returns three values — rows table, row
count, and the querycache timestamp (`getCachedTimestamp()`, queried only
when `isCached()`, i.e. only where the cache path can actually serve), `nil`
for fresh results. README documents stale/partial semantics.

### D6 (low) — `userGroups()` signature works only by accident — [FIXED]

`userGroups( $_, $__ )` was callable from Lua only because the generic wrapper
always forwards two placeholders; direct PHP callers had to pass dummies, and
`@param any` is not a real type.

**Fix applied:** `userGroups( ...$args ): array` with a real doc comment. It
now also returns the same 3-value shape (rows, count, `null` timestamp) as
the query page functions.

---

## Simplifications

Behavior-preserving only. All **[APPLIED]** on 2026-11-07 except S7.

### S1 — DRY: `EXT` constant declared twice — [APPLIED]

`Hooks.php` and `ScribuntoLibrary.php` both declared `EXT`. Now declared once
as `public const` on `Hooks` and referenced as `Hooks::EXT` everywhere.

### S2 — Cruft: stale `@since 2.2` — [APPLIED]

`src/mw.ext.luaSpecials.lua` claimed `@since 2.2` while `extension.json`
declares version 0.1 and no 2.2 exists. Line removed.

### S3 — Cruft: orphaned `.dockerignore` — [WONTFIX]

Repo-root `.dockerignore` with no `Dockerfile`/compose anywhere. **The
maintainer states it is part of the harness setup — intentional, not cruft.**

### S4 — Cruft: duplicate `.gitignore` entries — [WONTFIX]

Duplicate `node_modules` lines existed; the harness has since rewritten
`.gitignore` itself (its own entries included), so nothing to do here either.

### S5 — Idiom: Lua loop variable shadows the module-name local — [APPLIED]

`for name, func in pairs( php ) do` shadowed `local name = 'luaSpecials'`.
Renamed the loop variable to `funcName`.

### S6 — Style: stray spaces before `(` / `[` — [APPLIED]

`mw.ext [name] = external` → `mw.ext[name] = external` (Lua), and the README
examples now use `pairs( ... )` / `func()` without stray spaces.

### S7 — (optional) eslint config wider than the actual sources — [NOT APPLIED]

`.eslintrc.json` extends `wikimedia/client`, `wikimedia/jquery`,
`wikimedia/mediawiki` though the repo ships no client JS. Left as is: it is
the standard Wikimedia extension template, and aligning it is a maintainer's
call, not a defect.

---

## Errors caught while fixing (kept for the record)

1. First D1 attempt used `class_exists( …, false )` (no autoload): on
   Scribunto 1.35–1.40 the global base class is **not yet loaded** at
   `ScribuntoExternalLibraries` time (Scribunto instantiates its own core
   libraries only after the hook returns), the shim would have fired there,
   and the namespaced fallback does not exist below 1.41 → fatal on exactly
   the branches being supported. The autoload-enabled form is correct on all
   branches (see D1 version map).
2. First D3 attempt called `$this->incrementExpensiveFunctionCount()` inside
   a `static` closure — `static` closures have no `$this`; it would have been
   a fatal at first invocation. Closures are now non-static and bind `$this`
   (the library instance) explicitly.

## Verification of the fixes

- `src/ScribuntoLibrary.php`, `src/Hooks.php` — PHP 7+ syntax verified by AST
  parse (glensc/php-parser 3.7.0) before a PHP runtime was available, then
  re-verified by the real toolchain (below).
- `src/mw.ext.luaSpecials.lua` — Lua 5.1 syntax verified (luaparse 0.3.1;
  Scribunto targets 5.1).
- `npm ci && npm test` (eslint, stylelint, banana) — passes.
- `composer install` + `composer test` on PHP 8.5.12, Composer 2.10.3 with
  the pinned tools (mediawiki-codesniffer 38.0.0, parallel-lint 1.2.0,
  minus-x 1.1.0): parallel-lint clean, phpcs clean (3 files, 0 findings),
  minus-x clean after clearing a stray executable bit on the local
  (gitignored) `composer.lock`.
- Environment note, not a repo defect: the harness keeps a 1.6G cache in
  `.opencode/` at the repo root with its own nested `node_modules`; phpcs's
  top-level-only excludes don't cover it, so a full-tree phpcs run exhausts
  its 128M default memory limit on minified JS there. Run
  `phpcs --ignore=*/.opencode/*` (or raise `-d memory_limit`) in such
  environments; committed `composer test` is unaffected on normal checkouts.
