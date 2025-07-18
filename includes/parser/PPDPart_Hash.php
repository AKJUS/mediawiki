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
 * @ingroup Parser
 */

namespace MediaWiki\Parser;

/**
 * @ingroup Parser
 */
// phpcs:ignore Squiz.Classes.ValidClassName.NotCamelCaps
class PPDPart_Hash {
	/**
	 * @var string[] Output accumulator
	 */
	public $out;

	/**
	 * @var int|null Index of equals sign, if found
	 */
	public $eqpos;

	/**
	 * @var int|null
	 */
	public $commentEnd;

	/**
	 * @var int|null
	 */
	public $visualEnd;

	public function __construct( string $out = '' ) {
		$this->out = [];

		if ( $out !== '' ) {
			$this->out[] = $out;
		}
	}
}

/** @deprecated class alias since 1.43 */
class_alias( PPDPart_Hash::class, 'PPDPart_Hash' );
