<?php
/**
 * Convenience class for weighted consistent hash rings.
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

namespace Wikimedia\HashRing;

use InvalidArgumentException;
use RuntimeException;
use UnexpectedValueException;

/**
 * Convenience class for weighted consistent hash rings
 *
 * This deterministically maps "keys" to a set of "locations" while avoiding clumping
 *
 * Each location is represented by a number of nodes on a ring proportionate to the ratio
 * of its weight compared to the total location weight. Note positions are deterministically
 * derived from the hash of the location name. Nodes are responsible for the portion of the
 * ring, counter-clockwise, up until the next node. Locations are responsible for all portions
 * of the ring that the location's nodes are responsible for.
 *
 * A location that is temporarily "ejected" is said to be absent from the "live" ring.
 * If no location ejections are active, then the base ring and live ring are identical.
 *
 * This class is designed in a way that using the "md5" algorithm will make it compatible
 * with libketama, e.g. OPT_LIBKETAMA_COMPATIBLE from the PECL memcached extension or "ketama"
 * from twemproxy. This can simplify the process of switching client libraries. However, note
 * that different clients might use incompatible 32-bit memcached value flag conventions.
 *
 * @since 1.22
 */
class HashRing {
	/** @var string Hashing algorithm for hash() */
	protected $algo;
	/** @var int[] Non-empty (location => integer weight) */
	protected $weightByLocation;
	/** @var int[] Map of (location => UNIX timestamp) */
	protected $ejectExpiryByLocation;

	/** @var array[] Non-empty position-ordered list of (position, location name) */
	protected $baseRing;
	/** @var array[]|null Non-empty position-ordered list of (position, location name) */
	protected $liveRing;

	/** @var integer Overall number of node groups per server */
	private const HASHES_PER_LOCATION = 40;
	/** @var integer Number of nodes in a node group */
	private const SECTORS_PER_HASH = 4;

	public const KEY_POS = 0;
	public const KEY_LOCATION = 1;

	/** @var int Consider all locations */
	public const RING_ALL = 0;
	/** @var int Only consider "live" locations */
	public const RING_LIVE = 1;

	/**
	 * Make a consistent hash ring given a set of locations and their weight values
	 *
	 * @param int[] $map Map of (location => weight)
	 * @param string $algo Hashing algorithm listed in hash_algos() [optional]
	 * @param int[] $ejections Map of (location => UNIX timestamp) for ejection expiries
	 * @since 1.31
	 */
	public function __construct( array $map, $algo = 'sha1', array $ejections = [] ) {
		$this->init( $map, $algo, $ejections );
	}

	/**
	 * @param int[] $map Map of (location => integer)
	 * @param string $algo Hashing algorithm
	 * @param int[] $ejections Map of (location => UNIX timestamp) for ejection expires
	 */
	protected function init( array $map, $algo, array $ejections ) {
		if ( !in_array( $algo, hash_algos(), true ) ) {
			throw new RuntimeException( __METHOD__ . ": unsupported '$algo' hash algorithm." );
		}

		$weightByLocation = array_filter( $map );
		if ( $weightByLocation === [] ) {
			throw new UnexpectedValueException( "No locations with non-zero weight." );
		} elseif ( min( $map ) < 0 ) {
			throw new InvalidArgumentException( "Location weight cannot be negative." );
		}

		$this->algo = $algo;
		$this->weightByLocation = $weightByLocation;
		$this->ejectExpiryByLocation = $ejections;
		$this->baseRing = $this->buildLocationRing( $this->weightByLocation );
	}

	/**
	 * Get the location of an item on the ring
	 *
	 * @param string $item
	 * @return string Location
	 * @throws UnexpectedValueException
	 */
	final public function getLocation( $item ) {
		return $this->getLocations( $item, 1 )[0];
	}

	/**
	 * Get the location of an item on the ring followed by the next ring locations
	 *
	 * @param string $item
	 * @param int $limit Maximum number of locations to return
	 * @param int $from One of the RING_* class constants
	 * @return string[] List of locations
	 * @throws UnexpectedValueException
	 */
	public function getLocations( $item, $limit, $from = self::RING_ALL ) {
		if ( $from === self::RING_ALL ) {
			$ring = $this->baseRing;
		} elseif ( $from === self::RING_LIVE ) {
			$ring = $this->getLiveRing();
		} else {
			throw new InvalidArgumentException( "Invalid ring source specified." );
		}

		// Short-circuit for the common single-location case. Note that if there was only one
		// location and it was ejected from the live ring, getLiveRing() would have error out.
		if ( count( $this->weightByLocation ) == 1 ) {
			return ( $limit > 0 ) ? [ $ring[0][self::KEY_LOCATION] ] : [];
		}

		// Locate the node index for this item's position on the hash ring
		$itemIndex = $this->findNodeIndexForPosition( $this->getItemPosition( $item ), $ring );

		$locations = [];
		$currentIndex = null;
		while ( count( $locations ) < $limit ) {
			if ( $currentIndex === null ) {
				$currentIndex = $itemIndex;
			} else {
				$currentIndex = $this->getNextClockwiseNodeIndex( $currentIndex, $ring );
				if ( $currentIndex === $itemIndex ) {
					break; // all nodes visited
				}
			}
			// @phan-suppress-next-line PhanTypeMismatchDimFetchNullable False positive
			$nodeLocation = $ring[$currentIndex][self::KEY_LOCATION];
			if ( !in_array( $nodeLocation, $locations, true ) ) {
				// Ignore other nodes for the same locations already added
				$locations[] = $nodeLocation;
			}
		}

		return $locations;
	}

	/**
	 * @param float $position
	 * @param array[] $ring Either the base or live ring
	 * @return int|null
	 */
	private function findNodeIndexForPosition( $position, $ring ) {
		$count = count( $ring );
		if ( $count === 0 ) {
			return null;
		}

		$index = null;
		$lowPos = 0;
		$highPos = $count;
		while ( true ) {
			$midPos = (int)( ( $lowPos + $highPos ) / 2 );
			if ( $midPos === $count ) {
				$index = 0;
				break;
			}

			$midVal = $ring[$midPos][self::KEY_POS];
			$midMinusOneVal = ( $midPos === 0 ) ? 0 : $ring[$midPos - 1][self::KEY_POS];
			if ( $position <= $midVal && $position > $midMinusOneVal ) {
				$index = $midPos;
				break;
			}

			if ( $midVal < $position ) {
				$lowPos = $midPos + 1;
			} else {
				$highPos = $midPos - 1;
			}

			if ( $lowPos > $highPos ) {
				$index = 0;
				break;
			}
		}

		return $index;
	}

	/**
	 * Get the map of locations to weight (does not include zero weight items)
	 *
	 * @return int[]
	 */
	public function getLocationWeights() {
		return $this->weightByLocation;
	}

	/**
	 * Remove a location from the "live" hash ring
	 *
	 * @param string $location
	 * @param int $ttl Seconds
	 * @return bool Whether some non-ejected locations are left
	 * @throws UnexpectedValueException
	 */
	public function ejectFromLiveRing( $location, $ttl ) {
		if ( !isset( $this->weightByLocation[$location] ) ) {
			throw new UnexpectedValueException( "No location '$location' in the ring." );
		}

		$expiry = $this->getCurrentTime() + $ttl;
		$this->ejectExpiryByLocation[$location] = $expiry;

		$this->liveRing = null; // invalidate ring cache

		return ( count( $this->ejectExpiryByLocation ) < count( $this->weightByLocation ) );
	}

	/**
	 * Get the location of an item on the "live" ring
	 *
	 * @param string $item
	 * @return string Location
	 * @throws UnexpectedValueException
	 */
	final public function getLiveLocation( $item ) {
		return $this->getLocations( $item, 1, self::RING_LIVE )[0];
	}

	/**
	 * Get the location of an item on the "live" ring, as well as the next locations
	 *
	 * @param string $item
	 * @param int $limit Maximum number of locations to return
	 * @return string[] List of locations
	 * @throws UnexpectedValueException
	 */
	final public function getLiveLocations( $item, $limit ) {
		return $this->getLocations( $item, $limit, self::RING_LIVE );
	}

	/**
	 * Get the map of "live" locations to weight (does not include zero weight items)
	 *
	 * @return int[]
	 * @throws UnexpectedValueException
	 */
	public function getLiveLocationWeights() {
		$now = $this->getCurrentTime();

		return array_diff_key(
			$this->weightByLocation,
			array_filter(
				$this->ejectExpiryByLocation,
				static function ( $expiry ) use ( $now ) {
					return ( $expiry > $now );
				}
			)
		);
	}

	/**
	 * @param int[] $weightByLocation
	 * @return array[]
	 */
	private function buildLocationRing( array $weightByLocation ) {
		$locationCount = count( $weightByLocation );
		$totalWeight = array_sum( $weightByLocation );

		$ring = [];
		// Assign nodes to all locations based on location weight
		$claimed = []; // (position as string => (node, index))
		foreach ( $weightByLocation as $location => $weight ) {
			$ratio = $weight / $totalWeight;
			// There $locationCount * (HASHES_PER_LOCATION * 4) nodes available;
			// assign a few groups of nodes to this location based on its weight.
			$nodesQuartets = intval( $ratio * self::HASHES_PER_LOCATION * $locationCount );
			for ( $qi = 0; $qi < $nodesQuartets; ++$qi ) {
				// For efficiency, get 4 points per hash call and 4X node count.
				// If $algo is MD5, then this matches that of with libketama.
				// See https://github.com/RJ/ketama/blob/master/libketama/ketama.c
				$positions = $this->getNodePositionQuartet( "{$location}-{$qi}" );
				foreach ( $positions as $gi => $position ) {
					$node = ( $qi * self::SECTORS_PER_HASH + $gi ) . "@$location";
					$posKey = (string)$position; // large integer
					if ( isset( $claimed[$posKey] ) ) {
						// Disallow duplicates  (name decides precedence)
						if ( $claimed[$posKey]['node'] > $node ) {
							continue;
						} else {
							unset( $ring[$claimed[$posKey]['index']] );
						}
					}
					$ring[] = [
						self::KEY_POS => $position,
						self::KEY_LOCATION => $location
					];
					$claimed[$posKey] = [ 'node' => $node, 'index' => count( $ring ) - 1 ];
				}
			}
		}
		// Sort the locations into clockwise order based on the hash ring position
		usort( $ring, static function ( $a, $b ) {
			if ( $a[self::KEY_POS] === $b[self::KEY_POS] ) {
				throw new UnexpectedValueException( 'Duplicate node positions.' );
			}

			return ( $a[self::KEY_POS] < $b[self::KEY_POS] ? -1 : 1 );
		} );

		return $ring;
	}

	/**
	 * @param string $item Key
	 * @return float Ring position; integral number in [0, 4294967296] (2^32)
	 */
	private function getItemPosition( $item ) {
		// If $algo is MD5, then this matches that of with libketama.
		// See https://github.com/RJ/ketama/blob/master/libketama/ketama.c
		$octets = substr( hash( $this->algo, (string)$item, true ), 0, 4 );
		if ( strlen( $octets ) != 4 ) {
			throw new UnexpectedValueException( __METHOD__ . ": {$this->algo} is < 32 bits." );
		}

		$pos = unpack( 'V', $octets )[1];
		if ( $pos < 0 ) {
			// Most-significant-bit is set, causing unpack() to return a negative integer due
			// to the fact that it returns a signed int. Cast it to an unsigned integer string.
			$pos = sprintf( '%u', $pos );
		}

		return (float)$pos;
	}

	/**
	 * @param string $nodeGroupName
	 * @return float[] Four ring positions on [0, 4294967296] (2^32)
	 */
	private function getNodePositionQuartet( $nodeGroupName ) {
		$octets = substr( hash( $this->algo, (string)$nodeGroupName, true ), 0, 16 );
		if ( strlen( $octets ) != 16 ) {
			throw new UnexpectedValueException( __METHOD__ . ": {$this->algo} is < 128 bits." );
		}

		$positions = [];
		foreach ( unpack( 'V4', $octets ) as $signed ) {
			$positions[] = (float)sprintf( '%u', $signed );
		}

		return $positions;
	}

	/**
	 * @param int $i Valid index for a node in the ring
	 * @param array[] $ring Either the base or live ring
	 * @return int Valid index for a node in the ring
	 */
	private function getNextClockwiseNodeIndex( $i, $ring ) {
		if ( !isset( $ring[$i] ) ) {
			throw new UnexpectedValueException( __METHOD__ . ": reference index is invalid." );
		}

		$next = $i + 1;

		return ( $next < count( $ring ) ) ? $next : 0;
	}

	/**
	 * Get the "live" hash ring (which does not include ejected locations)
	 *
	 * @return array[]
	 * @throws UnexpectedValueException
	 */
	protected function getLiveRing() {
		if ( !$this->ejectExpiryByLocation ) {
			return $this->baseRing; // nothing ejected
		}

		$now = $this->getCurrentTime();

		if ( $this->liveRing === null || min( $this->ejectExpiryByLocation ) <= $now ) {
			// Live ring needs to be regenerated...
			$this->ejectExpiryByLocation = array_filter(
				$this->ejectExpiryByLocation,
				static function ( $expiry ) use ( $now ) {
					return ( $expiry > $now );
				}
			);

			if ( count( $this->ejectExpiryByLocation ) ) {
				// Some locations are still ejected from the ring
				$liveRing = [];
				foreach ( $this->baseRing as $nodeInfo ) {
					$location = $nodeInfo[self::KEY_LOCATION];
					if ( !isset( $this->ejectExpiryByLocation[$location] ) ) {
						$liveRing[] = $nodeInfo;
					}
				}
			} else {
				$liveRing = $this->baseRing;
			}

			$this->liveRing = $liveRing;
		}

		if ( !$this->liveRing ) {
			throw new UnexpectedValueException( "The live ring is currently empty." );
		}

		return $this->liveRing;
	}

	/**
	 * @return int UNIX timestamp
	 */
	protected function getCurrentTime() {
		return time();
	}

	public function __serialize() {
		return [
			'algorithm' => $this->algo,
			'locations' => $this->weightByLocation,
			'ejections' => $this->ejectExpiryByLocation
		];
	}

	public function __unserialize( $data ) {
		if ( is_array( $data ) ) {
			$this->init( $data['locations'] ?? [], $data['algorithm'] ?? 'sha1', $data['ejections'] ?? [] );
		} else {
			throw new UnexpectedValueException( __METHOD__ . ": unable to decode JSON." );
		}
	}
}

/** @deprecated class alias since 1.44 */
class_alias( HashRing::class, 'HashRing' );
