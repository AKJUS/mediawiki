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

use MediaWiki\HTMLForm\OOUIHTMLForm;

/**
 * Extend OOUIHTMLForm purely so we can have a more sensible way of getting the section headers
 */
class EditWatchlistNormalHTMLForm extends OOUIHTMLForm {
	/** @inheritDoc */
	public function getLegend( $namespace ) {
		$namespace = (int)substr( $namespace, 2 );

		return $namespace == NS_MAIN
			? $this->msg( 'blanknamespace' )->text()
			: $this->getContext()->getLanguage()->getFormattedNsText( $namespace );
	}

	/** @inheritDoc */
	public function displaySection(
		$fields, $sectionName = '', $fieldsetIDPrefix = '', &$hasUserVisibleFields = false
	) {
		return parent::displaySection( $fields, $sectionName, 'editwatchlist-', $hasUserVisibleFields );
	}
}
