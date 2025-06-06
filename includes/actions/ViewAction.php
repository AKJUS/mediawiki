<?php
/**
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA
 *
 * @file
 * @ingroup Actions
 */

namespace MediaWiki\Actions;

use MediaWiki\MainConfigNames;
use MediaWiki\MediaWikiServices;

/**
 * An action that views article content
 *
 * This is a wrapper that will call Article::view().
 *
 * @ingroup Actions
 */
class ViewAction extends FormlessAction {

	/** @inheritDoc */
	public function getName() {
		return 'view';
	}

	/** @inheritDoc */
	public function onView() {
		return null;
	}

	/** @inheritDoc */
	public function needsReadRights() {
		// Pages in $wgWhitelistRead can be viewed without having the 'read'
		// right. We rely on Article::view() to properly check read access.
		return false;
	}

	/** @inheritDoc */
	public function show() {
		$config = $this->context->getConfig();

		// Emit deprecated hook warnings.
		// We do this only in the view action so that it reliably shows up in
		// the debug toolbar without unduly impacting the performance of API and
		// ResourceLoader requests.
		MediaWikiServices::getInstance()->getHookContainer()->emitDeprecationWarnings();

		if (
			!$config->get( MainConfigNames::DebugToolbar ) && // don't let this get stuck on pages
			$this->getWikiPage()->checkTouched() // page exists and is not a redirect
		) {
			// Include any redirect in the last-modified calculation
			$redirFromTitle = $this->getArticle()->getRedirectedFrom();
			if ( !$redirFromTitle ) {
				$touched = $this->getWikiPage()->getTouched();
			} else {
				$touched = max(
					$this->getWikiPage()->getTouched(),
					$redirFromTitle->getTouched()
				);
			}

			// Send HTTP 304 if the IMS matches or otherwise set expiry/last-modified headers
			if ( $touched && $this->getOutput()->checkLastModified( $touched ) ) {
				wfDebug( __METHOD__ . ": done 304" );
				return;
			}
		}

		$this->getArticle()->view();
	}
}

/** @deprecated class alias since 1.44 */
class_alias( ViewAction::class, 'ViewAction' );
