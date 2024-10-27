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
		$subpageNames = $dbr->newSelectQueryBuilder()
			->select( [ 'page_title' ] )
			->from( 'page' )
			->where( [
				'page_namespace' => $ns,
				'page_title ' . $dbr->buildLike( $title->getDBKey(), '/', $dbr->anyString() )
			] )
			->caller( __METHOD__ )
			->fetchFieldValues();

		if ( !$subpageNames ) {
			// No subpages.
			return '';
		}

		$anchorsFound = []; // [ 'subpageName1' => [ anchorNumber1, anchorNumber2, ... ], ... ]
		foreach ( $subpageNames as $subpageName ) {
			$subpageTitle = Title::makeTitle( $ns, $subpageName );
			$anchorNumbers = self::getSortedAnchorNumbers( $subpageTitle );
			if ( !$anchorNumbers ) {
				continue;
			}

			$anchorsFound[$subpageName] = $anchorNumbers;
		}

		if ( !$anchorsFound ) {
			// None of the subpages have anchors.
			return '';
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
				$links[] = Linker::link( $anchorTitle, (string)$anchor );
			}
		}

		$resultHtml = Xml::tags( 'div',
			[ 'class' => 'mw-subpage-navtemplate' ],
			implode( ' ', $links )
		);
		return [ $resultHtml, 'isHTML' => true ];
	}

	/**
	 * Find all anchor numbers (e.g. 123 if HTML of the page has <span id="pg123">) on the page $title.
	 * Results are sorted from lower to higher number.
	 * @param PageIdentity $title
	 * @return int[]
	 */
	protected function getSortedAnchorNumbers( PageIdentity $title ) {
		$services = MediaWikiServices::getInstance();
		$content = $services->getWikiPageFactory()->newFromTitle( $title )->getContent();
		if ( !$content ) {
			return [];
		}

		// Parse the wikitext, but prohibit expansion of templates.
		$pout = $services->getContentRenderer()->getParserOutput( $content, $title );
		$html = $pout->getText();

		$matches = null;
		if ( !preg_match_all( '/id="pg([0-9]+)"/', $html, $matches ) ) {
			return [];
		}

		$anchorNumbers = array_map( 'intval', $matches[1] );
		sort( $anchorNumbers );

		return $anchorNumbers;
	}
}
