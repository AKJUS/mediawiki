<?php
/**
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
 * @ingroup Testing
 */

use MediaWiki\Installer\DatabaseUpdater;
use Wikimedia\Rdbms\IMaintainableDatabase;

class DbTestRecorder extends TestRecorder {
	/** @var string */
	public $version;
	/** @var IMaintainableDatabase */
	private $db;
	/** @var int */
	private $curRun;

	public function __construct( IMaintainableDatabase $db ) {
		$this->db = $db;
	}

	/**
	 * Set up result recording; insert a record for the run with the date
	 * and all that fun stuff
	 */
	public function start() {
		$this->db->begin( __METHOD__ );

		if ( !$this->db->tableExists( 'testrun', __METHOD__ )
			|| !$this->db->tableExists( 'testitem', __METHOD__ )
		) {
			print "WARNING> `testrun` table not found in database. Trying to create table.\n";
			$updater = DatabaseUpdater::newForDB( $this->db );
			$this->db->sourceFile( $updater->patchPath( $this->db, 'patch-testrun.sql' ) );
			echo "OK, resuming.\n";
		}

		$this->db->newInsertQueryBuilder()
			->insertInto( 'testrun' )
			->row( [
				'tr_date' => $this->db->timestamp(),
				'tr_mw_version' => $this->version,
				'tr_php_version' => PHP_VERSION,
				'tr_db_version' => $this->db->getServerVersion(),
				'tr_uname' => php_uname()
			] )
			->caller( __METHOD__ )
			->execute();
		$this->curRun = $this->db->insertId();
	}

	/**
	 * Record an individual test item's success or failure to the db
	 */
	public function record( ParserTestResult $result ) {
		$desc = $result->getDescription();
		$this->db->newInsertQueryBuilder()
			->insertInto( 'testitem' )
			->row( [
				'ti_run' => $this->curRun,
				'ti_name' => $desc,
				'ti_success' => $result->isSuccess() ? 1 : 0,
			] )
			->caller( __METHOD__ )
			->execute();
	}

	/**
	 * Commit transaction and clean up for result recording
	 */
	public function end() {
		$this->db->commit( __METHOD__ );
	}
}
