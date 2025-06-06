<?php

namespace MediaWiki\Tests\Api\Query;

use MediaWiki\MainConfigNames;
use MediaWiki\Message\Message;
use MediaWiki\Tests\Api\ApiTestCase;

/**
 * @group API
 * @group medium
 * @covers \MediaWiki\Api\ApiQueryTokens
 */
class ApiQueryTokensTest extends ApiTestCase {

	public function testGetCsrfToken() {
		$params = [
			'action' => 'query',
			'meta' => 'tokens',
			'type' => 'csrf',
		];

		$apiResult = $this->doApiRequest( $params );
		$this->assertArrayHasKey( 'query', $apiResult[0] );
		$this->assertArrayHasKey( 'tokens', $apiResult[0]['query'] );
		$this->assertArrayHasKey( 'csrftoken', $apiResult[0]['query']['tokens'] );
		$this->assertStringEndsWith( '+\\', $apiResult[0]['query']['tokens']['csrftoken'] );
	}

	public function testGetAllTokens() {
		$params = [
			'action' => 'query',
			'meta' => 'tokens',
			'type' => '*',
		];

		$apiResult = $this->doApiRequest( $params );
		$this->assertArrayHasKey( 'query', $apiResult[0] );
		$this->assertArrayHasKey( 'tokens', $apiResult[0]['query'] );

		// MW core has 7 token types (createaccount, csrf, login, patrol, rollback, userrights, watch)
		$this->assertGreaterThanOrEqual( 7, count( $apiResult[0]['query']['tokens'] ) );
	}

	public function testContinuation(): void {
		// one token is 42 characters, so 100 is enough for 2 tokens but not 3
		$size = 100;
		$this->overrideConfigValue( MainConfigNames::APIMaxResultSize, $size );

		[ $result ] = $this->doApiRequest( [
			'action' => 'query',
			'meta' => 'tokens',
			'type' => 'csrf|patrol|watch',
		] );

		$this->assertSame(
			$this->apiContext->msg( 'apiwarn-truncatedresult', Message::numParam( $size ) )
				->text(),
			$result['warnings']['result']['warnings']
		);

		$this->assertSame( [ 'csrftoken', 'patroltoken' ], array_keys( $result['query']['tokens'] ) );
		$this->assertTrue( $result['batchcomplete'], 'batchcomplete should be true' );
		$this->assertSame( [ 'type' => 'watch', 'continue' => '-||' ], $result['continue'] );
	}

}
