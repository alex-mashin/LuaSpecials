--[[
	Registers methods that can be accessed through the Scribunto extension

	@since 2.2

	@licence GNU GPL v2+
	@author Alexander Mashin
]]

local name = 'luaSpecials'

-- Variable instantiation
local external = {}

function external.setupInterface()

	-- Variable instantiation
	local php

	-- Interface setup
	external.setupInterface = nil
	php = mw_interface
	mw_interface = nil

	-- Register library within the "mw.ext.luaSpecials" namespace
	mw = mw or {}
	mw.ext = mw.ext or {}

	-- Export data retrieval functions from php (ScribuntoLibrary).
	for name, func in pairs( php ) do
		external[name] = function( offset, limit, ... )
			return func( offset, limit, ... )
		end
	end

	mw.ext [name] = external
	package.loaded['mw.ext.' .. name] = external
end

return external
