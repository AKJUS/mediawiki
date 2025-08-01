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
 */

namespace MediaWiki\Api;

use MediaWiki\Auth\AuthenticationRequest;
use MediaWiki\Auth\AuthManager;
use MediaWiki\MainConfigNames;

/**
 * Remove authentication data from AuthManager
 *
 * @ingroup API
 */
class ApiRemoveAuthenticationData extends ApiBase {

	/** @var string */
	private $authAction;
	/** @var string */
	private $operation;

	private AuthManager $authManager;

	public function __construct(
		ApiMain $main,
		string $action,
		AuthManager $authManager
	) {
		parent::__construct( $main, $action );

		$this->authAction = $action === 'unlinkaccount'
			? AuthManager::ACTION_UNLINK
			: AuthManager::ACTION_REMOVE;
		$this->operation = $action === 'unlinkaccount'
			? 'UnlinkAccount'
			: 'RemoveCredentials';

		$this->authManager = $authManager;
	}

	public function execute() {
		if ( !$this->getUser()->isNamed() ) {
			$this->dieWithError( 'apierror-mustbeloggedin-removeauth', 'notloggedin' );
		}

		$params = $this->extractRequestParams();

		// Check security-sensitive operation status
		ApiAuthManagerHelper::newForModule( $this, $this->authManager )
			->securitySensitiveOperation( $this->operation );

		// Fetch the request. No need to load from the request, so don't use
		// ApiAuthManagerHelper's method.
		$remove = $this->authAction === AuthManager::ACTION_REMOVE
			? array_fill_keys( $this->getConfig()->get(
				MainConfigNames::RemoveCredentialsBlacklist ), true )
			: [];
		$reqs = array_filter(
			$this->authManager->getAuthenticationRequests( $this->authAction, $this->getUser() ),
			static function ( AuthenticationRequest $req ) use ( $params, $remove ) {
				return $req->getUniqueId() === $params['request'] &&
					!isset( $remove[get_class( $req )] );
			}
		);
		if ( count( $reqs ) !== 1 ) {
			$this->dieWithError( 'apierror-changeauth-norequest', 'badrequest' );
		}
		$req = reset( $reqs );

		// Perform the removal
		$status = $this->authManager->allowsAuthenticationDataChange( $req, true );
		$this->getHookRunner()->onChangeAuthenticationDataAudit( $req, $status );
		if ( !$status->isGood() ) {
			$this->dieStatus( $status );
		}
		$this->authManager->changeAuthenticationData( $req );

		$this->getResult()->addValue( null, $this->getModuleName(), [ 'status' => 'success' ] );
	}

	/** @inheritDoc */
	public function isWriteMode() {
		return true;
	}

	/** @inheritDoc */
	public function needsToken() {
		return 'csrf';
	}

	/** @inheritDoc */
	public function getAllowedParams() {
		return ApiAuthManagerHelper::getStandardParams( $this->authAction,
			'request'
		);
	}

	/** @inheritDoc */
	protected function getExamplesMessages() {
		$path = $this->getModulePath();
		$action = $this->getModuleName();
		return [
			"action={$action}&request=FooAuthenticationRequest&token=123ABC"
				=> "apihelp-{$path}-example-simple",
		];
	}

	/** @inheritDoc */
	public function getHelpUrls() {
		return 'https://www.mediawiki.org/wiki/Special:MyLanguage/API:Manage_authentication_data';
	}
}

/** @deprecated class alias since 1.43 */
class_alias( ApiRemoveAuthenticationData::class, 'ApiRemoveAuthenticationData' );
