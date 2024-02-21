This _[MediaWiki](https://mediawiki.org)_ extension gives access to query special pages from Lua.

# Requirements
This extension requires _[MediaWiki](https://mediawiki.org)_
and _[Scribunto](https://mediawiki.org/wiki/extension:Scribunto)_.

# Installation and configuration
To install,
```bash
cd extensions
git clone https://github.com/alex-mashin/LuaSpecials
```

To enable the extension, add to `LocalSettings.php`:
```php
wfLoadExtension( 'LuaSpecials' );
```

The only configuration setting is `$wgLuaSpecialsDefaultLimit`,
regulating, how much rows are fetched from the special page, if the limit
is not specified in Lua module. Its default value is 50.
To remove the limit, set
```php
$wgLuaSpecialsDefaultLimit = false;
```

# Usage
```lua
mw.ext.luaSpecials.longpages( 0, 100) -- fist 100 rows of Special:Longpages.
mw.ext.luaSpecials.longpages( 100, 100) -- next 100 rows.
mw.ext.luaSpecials.longpages() -- first $wgLuaSpecialsDefaultLimit rows.

-- Get all available special pages:
local pages = {}
for special, func in pairs (mw.ext.luaSpecials) do
	pages [special] = func ()
end 
```

# Credits
This extension is written by Alexander Mashin in 2024,
and was inspired by [a feature request](https://phabricator.wikimedia.org/T354890)
by [SD0001](https://phabricator.wikimedia.org/p/SD0001/). 
