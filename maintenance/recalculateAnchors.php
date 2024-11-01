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

if ( getenv( 'MW_INSTALL_PATH' ) ) {
	$IP = getenv( 'MW_INSTALL_PATH' );
} else {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

use Maintenance;
use MediaWiki\MediaWikiServices;
use Title;

/**
 * Check all pages for anchors (<span id="pg123">) and record all found anchors in the dabatase.
 */
class RecalculateAnchors extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->requireExtension( 'SubpageAnchorNavigation' );
		$this->addDescription( 'Rebuilds the SubpageAnchorNavigation database' );
	}

	public function execute() {
		$dbr = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_REPLICA );
		$res = $dbr->newSelectQueryBuilder()
			->select( [ 'page_namespace', 'page_title' ] )
			->from( 'page' )
			->where( [
				'page_title ' . $dbr->buildLike( $dbr->anyString(), '/', $dbr->anyString() )
			] )
			->caller( __METHOD__ )
			->fetchResultSet();

		foreach ( $res as $row ) {
			$title = Title::newFromRow( $row );
			echo 'Calculating for: ' . $title->getFullText() . "\n";

			PageWithAnchors::recalculate( $title );
		}

		echo "Populated database of \"SubpageAnchorNavigation\" extension.\n";
	}
}

$maintClass = RecalculateAnchors::class;
require_once RUN_MAINTENANCE_IF_MAIN;
