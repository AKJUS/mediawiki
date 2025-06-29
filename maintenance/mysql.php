<?php
/**
 * Execute the MySQL client binary, connecting to the wiki's DB.
 * Note that this will not do table prefixing or variable substitution.
 * To safely run schema patches, use sql.php.
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
use MediaWiki\Shell\Shell;
use Wikimedia\FileBackend\FSFile\TempFSFile;
use Wikimedia\IPUtils;
use Wikimedia\Rdbms\ServerInfo;

// @codeCoverageIgnoreStart
require_once __DIR__ . '/Maintenance.php';
// @codeCoverageIgnoreEnd

/**
 * @ingroup Maintenance
 */
class MysqlMaintenance extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->addDescription( "Execute the MySQL client binary. " .
			"Non-option arguments will be passed through to mysql." );
		$this->addOption( 'write', 'Connect to the primary database', false, false );
		$this->addOption( 'group', 'Specify query group', false, true );
		$this->addOption( 'host', 'Connect to a known MySQL server', false, true );
		$this->addOption( 'raw-host',
			'Connect directly to a specific MySQL server, even if not known to MediaWiki '
				. 'via wgLBFactoryConf (e.g. parser cache or depooled host). '
				. 'Credentials will be chosen based on --cluster and --wikidb.',
			false,
			true
		);
		$this->addOption( 'list-hosts', 'List the available DB hosts', false, false );
		$this->addOption( 'cluster', 'Use an external cluster by name', false, true );
		$this->addOption( 'wikidb',
			'The database wiki ID to use if not the current one',
			false,
			true
		);

		// Fake argument for help message
		$this->addArg( '-- mysql_option ...', 'Options to pass to mysql', false );
	}

	public function execute() {
		$dbName = $this->getOption( 'wikidb', false );
		$lbf = $this->getServiceContainer()->getDBLoadBalancerFactory();

		// Pick LB
		if ( $this->hasOption( 'cluster' ) ) {
			try {
				$lb = $lbf->getExternalLB( $this->getOption( 'cluster' ) );
			} catch ( InvalidArgumentException ) {
				$this->fatalError( 'Error: invalid cluster' );
			}
		} else {
			$lb = $lbf->getMainLB( $dbName );
		}

		// List hosts, or pick host
		if ( $this->hasOption( 'list-hosts' ) ) {
			$serverCount = $lb->getServerCount();
			for ( $index = 0; $index < $serverCount; ++$index ) {
				echo $lb->getServerName( $index ) . "\n";
			}
			return;
		}
		if ( $this->hasOption( 'host' ) ) {
			$host = $this->getOption( 'host' );
			$serverCount = $lb->getServerCount();
			for ( $index = 0; $index < $serverCount; ++$index ) {
				if ( $lb->getServerName( $index ) === $host ) {
					break;
				}
			}
			if ( $index >= $serverCount ) {
				$this->fatalError( "Error: Host not configured: \"$host\"" );
			}
		} elseif ( $this->hasOption( 'write' ) ) {
			$index = ServerInfo::WRITER_INDEX;
		} else {
			$group = $this->getOption( 'group', false );
			$index = $lb->getReaderIndex( $group );
			if ( $index === false && $group ) {
				// retry without the group; it may not exist
				$index = $lb->getReaderIndex( false );
			}
			if ( $index === false ) {
				$this->fatalError( 'Error: unable to get reader index' );
			}
		}
		if ( $lb->getServerType( $index ) !== 'mysql' ) {
			$this->fatalError( 'Error: this script only works with MySQL/MariaDB' );
		}

		$serverInfo = $lb->getServerInfo( $index );
		// Override host. This uses the server info of the host determined
		// by the other options for the purposes of user/password.
		if ( $this->hasOption( 'raw-host' ) ) {
			$host = $this->getOption( 'raw-host' );
			$serverInfo = [ 'host' => $host ] + $serverInfo;
		}

		$this->runMysql( $serverInfo, $dbName );
	}

	/**
	 * Run the mysql client for the given server info
	 *
	 * @param array $info
	 * @param string|false $dbName The DB name, or false to use the main wiki DB
	 */
	private function runMysql( $info, $dbName ) {
		// Write the password to an option file to avoid disclosing it to other
		// processes running on the system
		$tmpFile = TempFSFile::factory( 'mw-mysql', 'ini' );
		chmod( $tmpFile->getPath(), 0600 );
		file_put_contents( $tmpFile->getPath(), "[client]\npassword={$info['password']}\n" );

		// stdin/stdout need to be the actual file descriptors rather than
		// PHP's pipe wrappers so that mysql can use them as an interactive
		// terminal.
		$desc = [
			0 => STDIN,
			1 => STDOUT,
			2 => STDERR,
		];

		// Split host and port as in DatabaseMySQL::mysqlConnect()
		$realServer = $info['host'];
		$hostAndPort = IPUtils::splitHostAndPort( $realServer );
		$socket = false;
		$port = false;
		if ( $hostAndPort ) {
			$realServer = $hostAndPort[0];
			if ( $hostAndPort[1] ) {
				$port = $hostAndPort[1];
			}
		} elseif ( substr_count( $realServer, ':' ) == 1 ) {
			// If we have a colon and something that's not a port number
			// inside the hostname, assume it's the socket location
			[ $realServer, $socket ] = explode( ':', $realServer, 2 );
		}

		if ( $dbName === false ) {
			$dbName = $info['dbname'];
		}

		$args = [
			'mysql',
			"--defaults-extra-file={$tmpFile->getPath()}",
			"--user={$info['user']}",
			"--database={$dbName}",
		];
		if ( $socket !== false ) {
			$args[] = "--socket={$socket}";
		} else {
			$args[] = "--host={$realServer}";
		}
		if ( $port !== false ) {
			$args[] = "--port={$port}";
		}

		$args = array_merge( $args, $this->getArgs() );

		// Ignore SIGINT if possible, otherwise the wrapper terminates when the user presses
		// ctrl-C to kill a query.
		if ( function_exists( 'pcntl_signal' ) ) {
			pcntl_signal( SIGINT, SIG_IGN );
		}

		$pipes = [];
		$proc = proc_open( Shell::escape( $args ), $desc, $pipes );
		if ( $proc === false ) {
			$this->fatalError( 'Unable to execute mysql' );
		}

		$ret = proc_close( $proc );
		if ( $ret === -1 ) {
			$this->fatalError( 'proc_close() returned -1' );
		} elseif ( $ret ) {
			$this->fatalError( 'Failed.', $ret );
		}
	}
}

// @codeCoverageIgnoreStart
$maintClass = MysqlMaintenance::class;
require_once RUN_MAINTENANCE_IF_MAIN;
// @codeCoverageIgnoreEnd
