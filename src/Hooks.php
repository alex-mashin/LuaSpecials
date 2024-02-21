<?php
namespace MediaWiki\Extension\LuaSpecials;

class Hooks {
	/** @const string EXT The extension name. */
	private const EXT = 'LuaSpecials';

	/**
	 * @param string $engine
	 * @param array &$extraLibraries
	 * @return bool
	 */
	public static function onScribuntoExternalLibraries( string $engine, array &$extraLibraries ): bool {
		$extraLibraries['mw.ext.' . self::EXT] = __NAMESPACE__ . '\ScribuntoLibrary';
		return true;
	}
}
