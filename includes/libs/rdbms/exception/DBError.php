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
namespace Wikimedia\Rdbms;

use RuntimeException;

/**
 * Database error base class.
 *
 * Catching and silencing this class or its subclasses is strongly discouraged.
 * Most code should not catch DB errors at all,
 * but let them bubble to the MediaWiki exception handler.
 * If necessary, cleanup can be done in a finally block;
 * catching the exception and then rethrowing it is also acceptable.
 *
 * @newable
 * @ingroup Database
 */
class DBError extends RuntimeException {
	/** @var IDatabase|null */
	public $db;

	/**
	 * Construct a database error
	 * @stable to call
	 * @param IDatabase|null $db Object which threw the error
	 * @param string $error A simple error message to be used for debugging
	 * @param \Throwable|null $prev Previous throwable
	 */
	public function __construct( ?IDatabase $db, $error, ?\Throwable $prev = null ) {
		parent::__construct( $error, 0, $prev );
		$this->db = $db;
	}
}
