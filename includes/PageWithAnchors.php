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

use MediaWiki\MediaWikiServices;
use ParserOutput;
use Title;

class PageWithAnchors {
	/**
	 * If the page $title has any anchors, record them into "page_props" database table.
	 * This function is used to initially populate the database.
	 * This function is NOT needed when the page is modified, because MultiContentSave hook
	 * already has ParserOutput and can add page properties directly to it.
	 * @param Title $title
	 */
	public static function recalculate( Title $title ) {
		$services = MediaWikiServices::getInstance();
		$content = $services->getWikiPageFactory()->newFromTitle( $title )->getContent();
		if ( !$content ) {
			// No such page.
			return;
		}

		// Parse the wikitext, but prohibit expansion of templates.
		$pout = $services->getContentRenderer()->getParserOutput( $content, $title );
		$anchors = self::findAnchors( $pout );
		if ( !$anchors ) {
			// No anchors found.
			return;
		}

		$dbw = $services->getDBLoadBalancer()->getConnection( DB_PRIMARY );
		$dbw->replace(
			'page_props',
			[ [ 'pp_page', 'pp_propname' ] ],
			[
				'pp_page' => $title->getArticleID(),
				'pp_propname' => 'nav_anchors',
				'pp_value' => $anchors
			],
			__METHOD__
		);
	}

	/**
	 * Find all anchors numbers in ParserOutput (e.g. 123 if HTML of the page has <span id="pg123">).
	 * Results are sorted from lower to higher number.
	 * @param ParserOutput $pout
	 * @return string Comma-separated list of numbers.
	 */
	public static function findAnchors( ParserOutput $pout ) {
		$matches = null;
		if ( !preg_match_all( '/id="pg([0-9]+)"/', $pout->getText(), $matches ) ) {
			return '';
		}

		$anchorNumbers = array_map( 'intval', $matches[1] );
		sort( $anchorNumbers );

		return implode( ',', $anchorNumbers );
	}
}
