<?php
namespace MediaWiki\Extension\LuaSpecials;

use MediaWiki\MediaWikiServices;

class ScribuntoLibrary extends \Scribunto_LuaLibraryBase {
	/** @const string EXT The extension name. */
	private const EXT = 'LuaSpecials';
	/** @var int Default query limit. */
	private static $limit;

	/**
	 * @inheritDoc
	 */
	public function register() {
		$config = MediaWikiServices::getInstance()->getMainConfig();
		self::$limit = $config->get( self::EXT . 'DefaultLimit' );

		$functions = [
			'userGroups' => __CLASS__ . '::userGroups'
		];

		// Namespace exists since MW 1.41:
		$qp = class_exists( 'QueryPage' ) ? 'QueryPage' : '\MediaWiki\SpecialPage\QueryPage';
		$disabled = $qp::getDisabledQueryPages( $config );
		foreach ( $qp::getPages() as [ $class, $special ] ) {
			if ( isset( $disabled[$special] ) ) {
				continue;
			}
			$functions[lcfirst( $special )] =
			static function ( $offset = false, $limit = false, ...$args ) use ( $special, $class ): array {
				$page = new $class( $special );
				$result = $page->doQuery( $offset ?? false, $limit ?: self::$limit );
				$num = $result->numRows();
				$rows = [];
				for ( $i = 0; $i < $num && $row = $result->fetchRow(); $i++ ) {
					$row = array_filter( $row, 'is_string', ARRAY_FILTER_USE_KEY ); // halve memory load.
					unset( $row['qc_type'] ); // further reduce memory load.
					$rows[$i + 1] = $row;
				}
				return [ $rows ]; // Scribunto will unwrap it.
			};
		}
		$this->getEngine()->registerInterface( __DIR__ . '/mw.ext.' . lcfirst( self::EXT ) . '.lua', $functions, [] );
	}

	/**
	 * @param any $_ Ignored.
	 * @param any $__ Ignored.
	 * @return array[]
	 */
	public static function userGroups( $_, $__ ): array {
		// Zero-based array to one-based:
		$based_0 = MediaWikiServices::getInstance()->getUserGroupManager()->listAllGroups();
		$based_1 = [];
		foreach ( $based_0 as $key => $group ) {
			$based_1[$key + 1] = $group;
		}
		return [ $based_1 ];
	}
}
