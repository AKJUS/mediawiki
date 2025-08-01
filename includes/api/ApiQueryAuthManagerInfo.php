<?php
/**
 * Copyright © 2016 Wikimedia Foundation and contributors
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
 * @since 1.27
 */

namespace MediaWiki\Api;

use MediaWiki\Auth\AuthManager;
use MediaWiki\MainConfigNames;
use Wikimedia\ParamValidator\ParamValidator;

/**
 * A query action to return meta information about AuthManager state.
 *
 * @ingroup API
 */
class ApiQueryAuthManagerInfo extends ApiQueryBase {

	private AuthManager $authManager;

	public function __construct(
		ApiQuery $query,
		string $moduleName,
		AuthManager $authManager
	) {
		parent::__construct( $query, $moduleName, 'ami' );
		$this->authManager = $authManager;
	}

	public function execute() {
		$params = $this->extractRequestParams();
		$helper = new ApiAuthManagerHelper( $this, $this->authManager );
		$ret = [
			'canauthenticatenow' => $this->authManager->canAuthenticateNow(),
			'cancreateaccounts' => $this->authManager->canCreateAccounts(),
			'canlinkaccounts' => $this->authManager->canLinkAccounts(),
		];

		if ( $params['securitysensitiveoperation'] !== null ) {
			$ret['securitysensitiveoperationstatus'] = $this->authManager->securitySensitiveOperationStatus(
				$params['securitysensitiveoperation']
			);
		}

		if ( $params['requestsfor'] ) {
			$action = $params['requestsfor'];

			$preservedReq = $helper->getPreservedRequest();
			if ( $preservedReq ) {
				$ret += [
					'haspreservedstate' => $preservedReq->hasStateForAction( $action ),
					'hasprimarypreservedstate' => $preservedReq->hasPrimaryStateForAction( $action ),
					'preservedusername' => (string)$preservedReq->username,
				];
			} else {
				$ret += [
					'haspreservedstate' => false,
					'hasprimarypreservedstate' => false,
					'preservedusername' => '',
				];
			}

			$reqs = $this->authManager->getAuthenticationRequests( $action, $this->getUser() );

			// Filter out blacklisted requests, depending on the action
			switch ( $action ) {
				case AuthManager::ACTION_CHANGE:
					$reqs = ApiAuthManagerHelper::blacklistAuthenticationRequests( $reqs,
						$this->getConfig()->get( MainConfigNames::ChangeCredentialsBlacklist )
					);
					break;
				case AuthManager::ACTION_REMOVE:
					$reqs = ApiAuthManagerHelper::blacklistAuthenticationRequests( $reqs,
						$this->getConfig()->get( MainConfigNames::RemoveCredentialsBlacklist )
					);
					break;
			}

			$ret += $helper->formatRequests( $reqs );
		}

		$this->getResult()->addValue( [ 'query' ], $this->getModuleName(), $ret );
	}

	/** @inheritDoc */
	public function isReadMode() {
		return false;
	}

	/** @inheritDoc */
	public function getAllowedParams() {
		return [
			'securitysensitiveoperation' => null,
			'requestsfor' => [
				ParamValidator::PARAM_TYPE => [
					AuthManager::ACTION_LOGIN,
					AuthManager::ACTION_LOGIN_CONTINUE,
					AuthManager::ACTION_CREATE,
					AuthManager::ACTION_CREATE_CONTINUE,
					AuthManager::ACTION_LINK,
					AuthManager::ACTION_LINK_CONTINUE,
					AuthManager::ACTION_CHANGE,
					AuthManager::ACTION_REMOVE,
					AuthManager::ACTION_UNLINK,
				],
			],
		] + ApiAuthManagerHelper::getStandardParams( '', 'mergerequestfields', 'messageformat' );
	}

	/** @inheritDoc */
	protected function getExamplesMessages() {
		return [
			'action=query&meta=authmanagerinfo&amirequestsfor=' . urlencode( AuthManager::ACTION_LOGIN )
				=> 'apihelp-query+authmanagerinfo-example-login',
			'action=query&meta=authmanagerinfo&amirequestsfor=' . urlencode( AuthManager::ACTION_LOGIN ) .
				'&amimergerequestfields=1'
				=> 'apihelp-query+authmanagerinfo-example-login-merged',
			'action=query&meta=authmanagerinfo&amisecuritysensitiveoperation=foo'
				=> 'apihelp-query+authmanagerinfo-example-securitysensitiveoperation',
		];
	}

	/** @inheritDoc */
	public function getHelpUrls() {
		return 'https://www.mediawiki.org/wiki/Special:MyLanguage/API:Authmanagerinfo';
	}
}

/** @deprecated class alias since 1.43 */
class_alias( ApiQueryAuthManagerInfo::class, 'ApiQueryAuthManagerInfo' );
