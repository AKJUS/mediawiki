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
 * @ingroup Installer
 */

namespace MediaWiki\Installer;

use MediaWiki\Specials\SpecialVersion;

class WebInstallerWelcome extends WebInstallerPage {

	/**
	 * @return string
	 */
	public function execute() {
		if ( $this->parent->request->wasPosted() && $this->getVar( '_Environment' ) ) {
			return 'continue';
		}
		$this->parent->output->addWikiTextAsInterface( wfMessage( 'config-welcome' )->plain() );
		$status = $this->parent->doEnvironmentChecks();
		if ( $status->isGood() ) {
			$this->parent->showSuccess( 'config-env-good' );
			$this->parent->output->addWikiTextAsInterface(
				wfMessage( 'config-welcome-section-copyright',
					SpecialVersion::getCopyrightAndAuthorList(),
					$this->parent->getVar( 'wgServer' ) .
						$this->parent->getDocUrl( 'Copying' )
				)->plain()
			);
			$this->startForm();
			$this->endForm();
		} else {
			$this->parent->showStatusMessage( $status );
		}

		return '';
	}

}
