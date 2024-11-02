<?php

/**
 * Implements SubpageAnchorNavigation extension for MediaWiki.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 */

namespace MediaWiki\SubpageAnchorNavigation;

use Linker;
use MediaWiki\MediaWikiServices;
use MediaWiki\Page\PageIdentity;
use Title;
use Xml;

class NavigationTemplate {
	/**
	 * Find all subpages of $title, find all tags like <span id="pg123"> inside each subpage,
	 * then generate links to every #pg<number> anchor, sorted by number after "pg".
	 * @param PageIdentity $title
	 * @return string|array
	 */
	public function generate( PageIdentity $title ) {
		$dbr = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_REPLICA );
		$ns = $title->getNamespace();

		// Find all subpages of $title.
		$res = $dbr->newSelectQueryBuilder()
			->select( [ 'page_title AS title', 'pp_value AS anchors' ] )
			->from( 'page' )
			->join( 'page_props', null, [ 'pp_page = page_id' ] )
			->where( [
				'page_namespace' => $ns,
				'page_title ' . $dbr->buildLike( $title->getDBKey(), '/', $dbr->anyString() )
			] )
			->caller( __METHOD__ )
			->fetchResultSet();

		if ( $res->numRows() == 0 ) {
			// None of the subpages have anchors.
			return '';
		}

		$anchorsFound = []; // [ 'subpageName1' => [ anchorNumber1, anchorNumber2, ... ], ... ]
		foreach ( $res as $row ) {
			$anchorsFound[$row->title] = explode( ',', $row->anchors );
		}

		// Sort subpages in the order of their anchor numbers.
		uasort( $anchorsFound, static function ( $numbers1, $numbers2 ) {
			return $numbers1[0] - $numbers2[0];
		} );

		// Generate navigation links.
		$links = [];
		foreach ( $anchorsFound as $subpageName => $anchorNumbers ) {
			foreach ( $anchorNumbers as $anchor ) {
				$anchorTitle = Title::makeTitle( $ns, $subpageName, "pg$anchor" );
				$links[] = Linker::link( $anchorTitle, $anchor );
			}
		}

		$resultHtml = Xml::tags( 'div',
			[ 'class' => 'mw-subpage-navtemplate' ],
			implode( ' ', $links )
		);
		return [ $resultHtml, 'isHTML' => true ];
	}
}
