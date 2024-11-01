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

use MediaWiki\Revision\RenderedRevision;
use Parser;
use Title;

class Hooks {
	/**
	 * Set up {{#subpage_anchor_navigation:}} syntax.
	 *
	 * @param Parser $parser
	 * @return true
	 */
	public static function onParserFirstCallInit( Parser $parser ) {
		$parser->setFunctionHook( 'subpage_anchor_navigation',
			'\MediaWiki\SubpageAnchorNavigation\Hooks::parserFunction' );
		return true;
	}

	/**
	 * MultiContentSave hook handler.
	 * Updates "which pages have which anchors" database after subpage has been edited.
	 * @param RenderedRevision $renderedRevision
	 * @return bool|void
	 */
	public static function onMultiContentSave( RenderedRevision $renderedRevision ) {
		$pout = $renderedRevision->getRevisionParserOutput();
		$anchors = PageWithAnchors::findAnchors( $pout );

		if ( $anchors ) {
			$pout->setPageProperty( 'nav_anchors', $anchors );
		}
	}

	/**
	 * Converts {{#subpage_anchor_navigation:}} wikitext into HTML output.
	 * @param Parser $parser
	 * @param ?string $pageName
	 * @return array|string
	 */
	public static function parserFunction( Parser $parser, $pageName ) {
		$title = $pageName ? Title::newFromText( $pageName ) : null;
		if ( !$title ) {
			$title = $parser->getTitle();
		}

		$template = new NavigationTemplate();
		return $template->generate( $title );
	}
}
