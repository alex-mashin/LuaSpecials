This _[MediaWiki](https://mediawiki.org)_ extension gives access to query special pages from Lua.

# Requirements
This extension requires _[MediaWiki](https://mediawiki.org)_
and _[Scribunto](https://mediawiki.org/wiki/extension:Scribunto)_.

# Installation and configuration
To install,
```bash
cd extensions
git clone https://github.com/alex-mashin/LuaSpecials.git
```

To enable the extension, add to `LocalSettings.php`:
```php
wfLoadExtension( 'LuaSpecials' );
```

The only configuration setting is `$wgLuaSpecialsDefaultLimit`,
regulating, how much rows are fetched from the special page, if the limit
is not specified in Lua module. Its default value is 50.
To remove the limit by default, set
```php
$wgLuaSpecialsDefaultLimit = false;
```

Every query counts towards the Scribunto expensive function limit
(`$wgExpensiveParserFunctionLimit`), so a module cannot run unboundedly many
queries per parse.

Pages that require a permission to view (like Special:Unwatchedpages, which
needs the `unwatchedpages` right) are not exposed to Lua, because Lua modules
run before any specific reader is known.

# Usage
```lua
local long1_100 = mw.ext.luaSpecials.longpages( 0, 100 ) -- fist 100 rows of Special:Longpages.
local long101_200 = mw.ext.luaSpecials.longpages( 100, 100 ) -- next 100 rows.
local long = mw.ext.luaSpecials.longpages() -- first $wgLuaSpecialsDefaultLimit rows.
local all = mw.ext.luaSpecials.longpages( 0, 0 ) -- all rows: limit = 0 means "no limit".

-- Each function returns rows, the number of rows,
-- and the timestamp of the query cache, if the results came from it
-- (miser mode): cached results may be stale and are capped
-- by $wgQueryCacheLimit.
local rows, count, cacheTimestamp = mw.ext.luaSpecials.longpages( 0, 100 )
if cacheTimestamp then
	-- results are cached and may be stale
end

-- Get all available special pages:
local pages = {}
for special, func in pairs( mw.ext.luaSpecials ) do
	pages[special] = func()
end
```

# Credits
This extension is written by Alexander Mashin in 2024,
and was inspired by [a feature request](https://phabricator.wikimedia.org/T354890)
by [SD0001](https://phabricator.wikimedia.org/p/SD0001/). 
