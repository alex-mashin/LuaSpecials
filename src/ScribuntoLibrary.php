<?php
namespace MediaWiki\Extension\LuaSpecials;

use MediaWiki\MediaWikiServices;

/**
 * Scribunto library exposing query special pages as mw.ext.luaSpecials.*.
 *
 * Works with Scribunto 1.35 (global Scribunto_LuaLibraryBase) through
 * Scribunto master (namespaced LibraryBase, alias removed): the alias is
 * rebuilt below if Scribunto no longer provides it. The autoload-enabled
 * class_exists() both loads the global class where Scribunto maps it
 * (AutoloadClasses, 1.35-1.44) and resolves the namespaced class through
 * AutoloadNamespaces where only it exists (master).
 */
if ( !class_exists( \Scribunto_LuaLibraryBase::class ) ) {
	class_alias(
		'MediaWiki\Extension\Scribunto\Engines\LuaCommon\LibraryBase',
		'Scribunto_LuaLibraryBase'
	);
}

class ScribuntoLibrary extends \Scribunto_LuaLibraryBase {
	/** @var int|false Default query limit. */
	private static $limit;

	/**
	 * @inheritDoc
	 */
	public function register() {
		$config = MediaWikiServices::getInstance()->getMainConfig();
		// GlobalVarConfig::get() takes the unprefixed name and prepends
		// 'wg' itself: 'LuaSpecialsDefaultLimit' → $wgLuaSpecialsDefaultLimit.
		self::$limit = $config->get( Hooks::EXT . 'DefaultLimit' );

		$functions = [
			'userGroups' => __CLASS__ . '::userGroups'
		];

		// Namespace exists since MW 1.41:
		$qp = class_exists( 'QueryPage' ) ? 'QueryPage' : '\MediaWiki\SpecialPage\QueryPage';
		$disabled = $qp::getDisabledQueryPages( $config );
		// SpecialPageFactory (since MW 1.35) constructs special pages with
		// their services, required since MW 1.42 for many query pages.
		$factory = MediaWikiServices::getInstance()->getSpecialPageFactory();
		foreach ( $qp::getPages() as [ , $special ] ) {
			if ( isset( $disabled[$special] ) ) {
				// Updating is disabled in $wgDisableQueryPageUpdate.
				continue;
			}
			try {
				$page = $factory->getPage( $special );
			} catch ( \Throwable $e ) {
				continue; // Not constructable, e.g. a missing dependency.
			}
			if ( !$page instanceof $qp || $page->getRestriction() !== '' ) {
				// Lua modules run before any specific reader is known, so
				// per-user access control is impossible: exclude pages that
				// require a permission (Special:Unwatchedpages, most
				// notably), as well as pages that are no QueryPages at all.
				continue;
			}
			$functions[lcfirst( $special )] =
			function ( $offset = false, $limit = false, ...$args ) use ( $page ): array {
				// Bound the number of DB-backed queries per parse, like
				// other expensive Scribunto libraries do.
				$this->incrementExpensiveFunctionCount();
				// Lua API: limit = 0 means "no limit"; nil or false mean
				// $wgLuaSpecialsDefaultLimit (which may itself be false,
				// i.e. no limit).
				$effectiveLimit = $limit === 0 ? false : ( $limit ?: self::$limit );
				$result = $page->doQuery( $offset ?? false, $effectiveLimit );
				$num = $result->numRows();
				$rows = [];
				for ( $i = 0; $i < $num && $row = $result->fetchRow(); $i++ ) {
					$row = array_filter( $row, 'is_string', ARRAY_FILTER_USE_KEY ); // halve memory load.
					unset( $row['qc_type'] ); // further reduce memory load.
					$rows[$i + 1] = $row;
				}
				// Cached (miser mode) results may be stale and are capped
				// by $wgQueryCacheLimit: return their timestamp as a third
				// value, null for fresh results. Scribunto unwraps the array.
				$cached = $page->isCached() ? $page->getCachedTimestamp() : null;
				return [ $rows, $num, $cached ?: null ];
			};
		}
		$this->getEngine()->registerInterface( __DIR__ . '/mw.ext.' . lcfirst( Hooks::EXT ) . '.lua', $functions, [] );
	}

	/**
	 * List all user groups defined on the wiki.
	 *
	 * @param mixed ...$args Ignored; the generic Lua wrapper passes offset
	 *  and limit placeholders to every function.
	 * @return array[] Rows, number of rows and the cache timestamp
	 *  (always null here), to match the shape of the query page functions.
	 */
	public static function userGroups( ...$args ): array {
		$zeroBased = MediaWikiServices::getInstance()->getUserGroupManager()->listAllGroups();
		// Zero-based array to one-based:
		$oneBased = [];
		foreach ( $zeroBased as $index => $group ) {
			$oneBased[$index + 1] = $group;
		}
		return [ $oneBased, count( $oneBased ), null ];
	}
}
