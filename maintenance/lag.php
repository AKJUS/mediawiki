<?php
/**
 * Shows database lag
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
 * @ingroup Maintenance
 */

use MediaWiki\Maintenance\Maintenance;

// @codeCoverageIgnoreStart
require_once __DIR__ . '/Maintenance.php';
// @codeCoverageIgnoreEnd

/**
 * Maintenance script to show database lag.
 *
 * @ingroup Maintenance
 */
class DatabaseLag extends Maintenance {

	/** @var bool */
	protected $stopReporting = false;

	public function __construct() {
		parent::__construct();
		$this->addDescription( 'Shows database lag' );
		$this->addOption( 'r', "Don't exit immediately, but show the lag every 5 seconds" );
	}

	public function execute() {
		$lb = $this->getServiceContainer()->getDBLoadBalancer();
		if ( $this->hasOption( 'r' ) ) {
			$this->output( 'time     ' );

			$serverCount = $lb->getServerCount();
			for ( $i = 1; $i < $serverCount; $i++ ) {
				$hostname = $lb->getServerName( $i );
				$this->output( sprintf( "%-12s ", $hostname ) );
			}
			$this->output( "\n" );

			do {
				$lags = $lb->getLagTimes();
				unset( $lags[0] );
				$this->output( gmdate( 'H:i:s' ) . ' ' );
				foreach ( $lags as $lag ) {
					$this->output(
						sprintf(
							"%-12s ",
							$lag === false ? 'replication stopped or errored' : $lag
						)
					);
				}
				$this->output( "\n" );
				sleep( 5 );
			} while ( !$this->stopReporting );

		} else {
			$lags = $lb->getLagTimes();
			foreach ( $lags as $i => $lag ) {
				$name = $lb->getServerName( $i );
				$this->output(
					sprintf(
						"%-20s %s\n",
						$name,
						$lag === false ? 'replication stopped or errored' : $lag
					)
				);
			}
		}
	}
}

// @codeCoverageIgnoreStart
$maintClass = DatabaseLag::class;
require_once RUN_MAINTENANCE_IF_MAIN;
// @codeCoverageIgnoreEnd
