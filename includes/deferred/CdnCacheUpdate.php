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
 */

namespace MediaWiki\Deferred;

use Exception;
use InvalidArgumentException;
use MediaWiki\JobQueue\Jobs\CdnPurgeJob;
use MediaWiki\MainConfigNames;
use MediaWiki\MediaWikiServices;
use MediaWiki\Page\PageReference;
use RuntimeException;
use Wikimedia\Assert\Assert;
use Wikimedia\IPUtils;

/**
 * Handles purging the appropriate CDN objects given a list of URLs or Title instances
 * @ingroup Cache
 */
class CdnCacheUpdate implements DeferrableUpdate, MergeableUpdate {
	/** @var array[] List of (URL, rebound purge delay) tuples */
	private $urlTuples = [];
	/** @var array[] List of (PageReference, rebound purge delay) tuples */
	private $pageTuples = [];

	/** @var int Maximum seconds of rebound purge delay */
	private const MAX_REBOUND_DELAY = 300;

	/**
	 * @param string[]|PageReference[] $targets Collection of URLs/titles to be purged from CDN
	 * @param array $options Options map. Supports:
	 *   - reboundDelay: how many seconds after the first purge to send a rebound purge.
	 *      No rebound purge will be sent if this is not positive. [Default: 0]
	 */
	public function __construct( array $targets, array $options = [] ) {
		$delay = min(
			(int)max( $options['reboundDelay'] ?? 0, 0 ),
			self::MAX_REBOUND_DELAY
		);

		foreach ( $targets as $target ) {
			if ( $target instanceof PageReference ) {
				$this->pageTuples[] = [ $target, $delay ];
			} else {
				$this->urlTuples[] = [ $target, $delay ];
			}
		}
	}

	public function merge( MergeableUpdate $update ) {
		/** @var self $update */
		Assert::parameterType( __CLASS__, $update, '$update' );
		'@phan-var self $update';

		$this->urlTuples = array_merge( $this->urlTuples, $update->urlTuples );
		$this->pageTuples = array_merge( $this->pageTuples, $update->pageTuples );
	}

	public function doUpdate() {
		// Resolve the final list of URLs just before purging them (T240083)
		$reboundDelayByUrl = $this->resolveReboundDelayByUrl();

		// Send the immediate purges to CDN
		self::purge( array_keys( $reboundDelayByUrl ) );
		$immediatePurgeTimestamp = time();

		// Get the URLs that need rebound purges, grouped by seconds of purge delay
		$urlsWithReboundByDelay = [];
		foreach ( $reboundDelayByUrl as $url => $delay ) {
			if ( $delay > 0 ) {
				$urlsWithReboundByDelay[$delay][] = $url;
			}
		}
		// Enqueue delayed purge jobs for these URLs (usually only one job)
		$jobs = [];
		foreach ( $urlsWithReboundByDelay as $delay => $urls ) {
			$jobs[] = new CdnPurgeJob( [
				'urls' => $urls,
				'jobReleaseTimestamp' => $immediatePurgeTimestamp + $delay
			] );
		}
		MediaWikiServices::getInstance()->getJobQueueGroup()->lazyPush( $jobs );
	}

	/**
	 * Purges a list of CDN nodes defined in $wgCdnServers.
	 * $urlArr should contain the full URLs to purge as values
	 * (example: $urlArr[] = 'http://my.host/something')
	 *
	 * @param string[] $urls List of full URLs to purge
	 */
	public static function purge( array $urls ) {
		$cdnServers = MediaWikiServices::getInstance()->getMainConfig()->get( MainConfigNames::CdnServers );
		$htcpRouting = MediaWikiServices::getInstance()->getMainConfig()->get( MainConfigNames::HTCPRouting );
		if ( !$urls ) {
			return;
		}

		// Remove duplicate URLs from list
		$urls = array_unique( $urls );

		wfDebugLog( 'squid', __METHOD__ . ': ' . implode( ' ', $urls ) );

		// Reliably broadcast the purge to all edge nodes
		$ts = microtime( true );
		$relayerGroup = MediaWikiServices::getInstance()->getEventRelayerGroup();
		$relayerGroup->getRelayer( 'cdn-url-purges' )->notifyMulti(
			'cdn-url-purges',
			array_map(
				static function ( $url ) use ( $ts ) {
					return [
						'url' => $url,
						'timestamp' => $ts,
					];
				},
				$urls
			)
		);

		// Send lossy UDP broadcasting if enabled
		if ( $htcpRouting ) {
			self::HTCPPurge( $urls );
		}

		// Do direct server purges if enabled (this does not scale very well)
		if ( $cdnServers ) {
			self::naivePurge( $urls );
		}
	}

	/**
	 * @return string[] List of URLs
	 */
	public function getUrls() {
		return array_keys( $this->resolveReboundDelayByUrl() );
	}

	/**
	 * @return int[] Map of (URL => rebound purge delay)
	 */
	private function resolveReboundDelayByUrl() {
		$services = MediaWikiServices::getInstance();
		/** @var PageReference $page */

		// Avoid multiple queries for HTMLCacheUpdater::getUrls() call
		$lb = $services->getLinkBatchFactory()->newLinkBatch()
			->setCaller( __METHOD__ );
		foreach ( $this->pageTuples as [ $page, ] ) {
			$lb->addObj( $page );
		}
		$lb->execute();

		$reboundDelayByUrl = [];

		// Resolve the titles into CDN URLs
		$htmlCacheUpdater = $services->getHtmlCacheUpdater();
		foreach ( $this->pageTuples as [ $page, $delay ] ) {
			foreach ( $htmlCacheUpdater->getUrls( $page ) as $url ) {
				// Use the highest rebound for duplicate URLs in order to handle the most lag
				$reboundDelayByUrl[$url] = max( $reboundDelayByUrl[$url] ?? 0, $delay );
			}
		}

		foreach ( $this->urlTuples as [ $url, $delay ] ) {
			// Use the highest rebound for duplicate URLs in order to handle the most lag
			$reboundDelayByUrl[$url] = max( $reboundDelayByUrl[$url] ?? 0, $delay );
		}

		return $reboundDelayByUrl;
	}

	/**
	 * Send Hyper Text Caching Protocol (HTCP) CLR requests
	 *
	 * @param string[] $urls Collection of URLs to purge
	 */
	private static function HTCPPurge( array $urls ) {
		$htcpRouting = MediaWikiServices::getInstance()->getMainConfig()->get( MainConfigNames::HTCPRouting );
		$htcpMulticastTTL = MediaWikiServices::getInstance()->getMainConfig()->get( MainConfigNames::HTCPMulticastTTL );
		// HTCP CLR operation
		$htcpOpCLR = 4;

		// @todo FIXME: PHP doesn't support these socket constants (include/linux/in.h)
		if ( !defined( "IPPROTO_IP" ) ) {
			define( "IPPROTO_IP", 0 );
			define( "IP_MULTICAST_LOOP", 34 );
			define( "IP_MULTICAST_TTL", 33 );
		}

		// pfsockopen doesn't work because we need set_sock_opt
		$conn = socket_create( AF_INET, SOCK_DGRAM, SOL_UDP );
		if ( !$conn ) {
			$errstr = socket_strerror( socket_last_error() );
			wfDebugLog( 'squid', __METHOD__ .
				": Error opening UDP socket: $errstr" );

			return;
		}

		// Set socket options
		socket_set_option( $conn, IPPROTO_IP, IP_MULTICAST_LOOP, 0 );
		if ( $htcpMulticastTTL != 1 ) {
			// Set multicast time to live (hop count) option on socket
			socket_set_option( $conn, IPPROTO_IP, IP_MULTICAST_TTL,
				$htcpMulticastTTL );
		}

		// Get sequential trx IDs for packet loss counting
		$idGenerator = MediaWikiServices::getInstance()->getGlobalIdGenerator();
		$ids = $idGenerator->newSequentialPerNodeIDs(
			'squidhtcppurge',
			32,
			count( $urls )
		);

		foreach ( $urls as $url ) {
			if ( !is_string( $url ) ) {
				throw new InvalidArgumentException( 'Bad purge URL' );
			}
			$url = self::expand( $url );
			$conf = self::getRuleForURL( $url, $htcpRouting );
			if ( !$conf ) {
				wfDebugLog( 'squid', __METHOD__ .
					"No HTCP rule configured for URL {$url} , skipping" );
				continue;
			}

			if ( isset( $conf['host'] ) && isset( $conf['port'] ) ) {
				// Normalize single entries
				$conf = [ $conf ];
			}
			foreach ( $conf as $subconf ) {
				if ( !isset( $subconf['host'] ) || !isset( $subconf['port'] ) ) {
					throw new RuntimeException( "Invalid HTCP rule for URL $url\n" );
				}
			}

			// Construct a minimal HTCP request diagram
			// as per RFC 2756
			// Opcode 'CLR', no response desired, no auth
			$htcpTransID = current( $ids );
			next( $ids );

			$htcpSpecifier = pack( 'na4na*na8n',
				4, 'HEAD', strlen( $url ), $url,
				8, 'HTTP/1.0', 0 );

			$htcpDataLen = 8 + 2 + strlen( $htcpSpecifier );
			$htcpLen = 4 + $htcpDataLen + 2;

			// Note! Squid gets the bit order of the first
			// word wrong, wrt the RFC. Apparently no other
			// implementation exists, so adapt to Squid
			$htcpPacket = pack( 'nxxnCxNxxa*n',
				$htcpLen, $htcpDataLen, $htcpOpCLR,
				$htcpTransID, $htcpSpecifier, 2 );

			wfDebugLog( 'squid', __METHOD__ .
				"Purging URL $url via HTCP" );
			foreach ( $conf as $subconf ) {
				socket_sendto( $conn, $htcpPacket, $htcpLen, 0,
					$subconf['host'], $subconf['port'] );
			}
		}
	}

	/**
	 * Send HTTP PURGE requests for each of the URLs to all of the cache servers
	 *
	 * @param string[] $urls
	 * @throws Exception
	 */
	private static function naivePurge( array $urls ) {
		$cdnServers = MediaWikiServices::getInstance()->getMainConfig()->get( MainConfigNames::CdnServers );

		$reqs = [];
		foreach ( $urls as $url ) {
			$url = self::expand( $url );
			$urlInfo = wfGetUrlUtils()->parse( $url ) ?? false;
			$urlHost = strlen( $urlInfo['port'] ?? '' )
				? IPUtils::combineHostAndPort( $urlInfo['host'], (int)$urlInfo['port'] )
				: $urlInfo['host'];
			$baseReq = [
				'method' => 'PURGE',
				'url' => $url,
				'headers' => [
					'Host' => $urlHost,
					'Connection' => 'Keep-Alive',
					'Proxy-Connection' => 'Keep-Alive',
					'User-Agent' => 'MediaWiki/' . MW_VERSION . ' ' . __CLASS__
				]
			];
			foreach ( $cdnServers as $server ) {
				$reqs[] = ( $baseReq + [ 'proxy' => $server ] );
			}
		}

		$http = MediaWikiServices::getInstance()->getHttpRequestFactory()
			->createMultiClient( [ 'maxConnsPerHost' => 8, 'usePipelining' => true ] );
		$http->runMulti( $reqs );
	}

	/**
	 * Expand local URLs to fully-qualified URLs using the internal protocol
	 * and host defined in $wgInternalServer. Input that's already fully-
	 * qualified will be passed through unchanged.
	 *
	 * This is used to generate purge URLs that may be either local to the
	 * main wiki or include a non-native host, such as images hosted on a
	 * second internal server.
	 *
	 * Client functions should not need to call this.
	 *
	 * @param string $url
	 * @return string
	 */
	private static function expand( $url ) {
		return (string)MediaWikiServices::getInstance()->getUrlUtils()->expand( $url, PROTO_INTERNAL );
	}

	/**
	 * Find the HTCP routing rule to use for a given URL.
	 * @param string $url URL to match
	 * @param array $rules Array of rules, see $wgHTCPRouting for format and behavior
	 * @return mixed Element of $rules that matched, or false if nothing matched
	 */
	private static function getRuleForURL( $url, $rules ) {
		foreach ( $rules as $regex => $routing ) {
			if ( $regex === '' || preg_match( $regex, $url ) ) {
				return $routing;
			}
		}

		return false;
	}
}

/** @deprecated class alias since 1.42 */
class_alias( CdnCacheUpdate::class, 'CdnCacheUpdate' );
