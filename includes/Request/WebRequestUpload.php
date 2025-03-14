<?php
/**
 * Object to access the $_FILES array
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

namespace MediaWiki\Request;

use MediaWiki\MediaWikiServices;
use MediaWiki\Parser\Sanitizer;

// The point of this class is to be a wrapper around super globals
// phpcs:disable MediaWiki.Usage.SuperGlobalsUsage.SuperGlobals

/**
 * Object to access the $_FILES array
 *
 * @ingroup HTTP
 */
class WebRequestUpload {
	/** All keys a fileinfo has to specific to work with this class */
	public const REQUIRED_FILEINFO_KEYS = [ 'name', 'size', 'tmp_name', 'type', 'error', ];
	/** @var WebRequest */
	protected $request;
	/** @var bool */
	protected $doesExist;
	/** @var array|null */
	protected $fileInfo;

	/**
	 * Constructor. Should only be called by WebRequest
	 *
	 * @param WebRequest $request The associated request
	 * @param string $key Key in $_FILES array (name of form field)
	 */
	public function __construct( $request, $key ) {
		$this->request = $request;
		$this->doesExist = isset( $_FILES[$key] );
		if ( $this->doesExist ) {
			$this->fileInfo = $_FILES[$key];
		}
	}

	/**
	 * Return whether a file with this name was uploaded.
	 *
	 * @return bool
	 */
	public function exists() {
		return $this->doesExist;
	}

	/**
	 * Return the original filename of the uploaded file
	 *
	 * @return string|null Filename or null if non-existent
	 */
	public function getName() {
		if ( !$this->exists() ) {
			return null;
		}

		// @phan-suppress-next-line PhanTypeArraySuspiciousNullable Okay after exists check
		$name = $this->fileInfo['name'];

		# Safari sends filenames in HTML-encoded Unicode form D...
		# Horrid and evil! Let's try to make some kind of sense of it.
		$name = Sanitizer::decodeCharReferences( $name );
		$name = MediaWikiServices::getInstance()->getContentLanguage()->normalize( $name );
		// @phan-suppress-next-line PhanTypeArraySuspiciousNullable Okay after exists check
		wfDebug( __METHOD__ . ": {$this->fileInfo['name']} normalized to '$name'" );
		return $name;
	}

	/**
	 * Return the file size of the uploaded file
	 *
	 * @return int File size or zero if non-existent
	 */
	public function getSize() {
		if ( !$this->exists() ) {
			return 0;
		}

		// @phan-suppress-next-line PhanTypeArraySuspiciousNullable Okay after exists check
		return $this->fileInfo['size'];
	}

	/**
	 * Return the path to the temporary file
	 *
	 * @return string|null Path or null if non-existent
	 */
	public function getTempName() {
		if ( !$this->exists() ) {
			return null;
		}

		// @phan-suppress-next-line PhanTypeArraySuspiciousNullable Okay after exists check
		return $this->fileInfo['tmp_name'];
	}

	/**
	 * Return the client specified content type
	 *
	 * @return string|null Type or null if non-existent
	 * @since 1.35
	 */
	public function getType() {
		if ( !$this->exists() ) {
			return null;
		}

		// @phan-suppress-next-line PhanTypeArraySuspiciousNullable Okay after exists check
		return $this->fileInfo['type'];
	}

	/**
	 * Return the upload error. See link for explanation
	 * https://www.php.net/manual/en/features.file-upload.errors.php
	 *
	 * @return int One of the UPLOAD_ constants, 0 if non-existent
	 */
	public function getError() {
		if ( !$this->exists() ) {
			return 0; # UPLOAD_ERR_OK
		}

		// @phan-suppress-next-line PhanTypeArraySuspiciousNullable Okay after exists check
		return $this->fileInfo['error'];
	}

	/**
	 * Returns whether this upload failed because of overflow of a maximum set
	 * in php.ini
	 *
	 * @return bool
	 */
	public function isIniSizeOverflow() {
		if ( $this->getError() == UPLOAD_ERR_INI_SIZE ) {
			# PHP indicated that upload_max_filesize is exceeded
			return true;
		}

		$contentLength = $this->request->getHeader( 'Content-Length' );
		$maxPostSize = wfShorthandToInteger( ini_get( 'post_max_size' ), 0 );

		if ( $maxPostSize && $contentLength > $maxPostSize ) {
			# post_max_size is exceeded
			return true;
		}

		return false;
	}
}
