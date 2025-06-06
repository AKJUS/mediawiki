<?php

use Wikimedia\Rdbms\FakeResultWrapper;
use Wikimedia\Rdbms\Platform\SQLPlatform;
use Wikimedia\Rdbms\SelectQueryBuilder;

/**
 * Tests for BatchRowUpdate and its components
 *
 * @group db
 *
 * @covers \BatchRowUpdate
 * @covers \BatchRowIterator
 * @covers \BatchRowWriter
 */
class BatchRowUpdateTest extends MediaWikiIntegrationTestCase {

	public function testWriterBasicFunctionality() {
		$db = $this->mockDb( [ 'update' ] );
		$writer = new BatchRowWriter( $db, 'echo_event' );

		$updates = [
			self::mockUpdate( [ 'something' => 'changed' ] ),
			self::mockUpdate( [ 'otherthing' => 'changed' ] ),
			self::mockUpdate( [ 'and' => 'something', 'else' => 'changed' ] ),
		];

		$db->expects( $this->exactly( count( $updates ) ) )
			->method( 'update' );

		$writer->write( $updates );
	}

	protected static function mockUpdate( array $changes ) {
		static $i = 0;
		return [
			'primaryKey' => [ 'event_id' => $i++ ],
			'changes' => $changes,
		];
	}

	public function testReaderBasicIterate() {
		$batchSize = 2;
		$response = $this->genSelectResult( $batchSize, /*numRows*/ 5, static function () {
			static $i = 0;
			return [ 'id_field' => ++$i ];
		} );
		$db = $this->mockDbConsecutiveSelect( $response );
		$reader = new BatchRowIterator( $db, 'some_table', 'id_field', $batchSize );

		$pos = 0;
		foreach ( $reader as $rows ) {
			$this->assertEquals( $response[$pos], $rows, "Testing row in position $pos" );
			$pos++;
		}
		// -1 is because the final [] marks the end and isn't included
		$this->assertEquals( count( $response ) - 1, $pos );
	}

	public static function provider_readerGetPrimaryKey() {
		$row = [
			'id_field' => 42,
			'some_col' => 'dvorak',
			'other_col' => 'samurai',
		];
		return [

			[
				'Must return single column pk when requested',
				[ 'id_field' => 42 ],
				$row
			],

			[
				'Must return multiple column pks when requested',
				[ 'id_field' => 42, 'other_col' => 'samurai' ],
				$row
			],

		];
	}

	/**
	 * @dataProvider provider_readerGetPrimaryKey
	 */
	public function testReaderGetPrimaryKey( $message, array $expected, array $row ) {
		$reader = new BatchRowIterator( $this->mockDb(), 'some_table', array_keys( $expected ), 8675309 );
		$this->assertEquals( $expected, $reader->extractPrimaryKeys( (object)$row ), $message );
	}

	public static function provider_readerSetFetchColumns() {
		return [

			[
				'Must merge primary keys into select conditions',
				// Expected column select
				[ 'foo', 'bar' ],
				// primary keys
				[ 'foo' ],
				// setFetchColumn
				[ 'bar' ]
			],

			[
				'Must not merge primary keys into the all columns selector',
				// Expected column select
				[ '*' ],
				// primary keys
				[ 'foo' ],
				// setFetchColumn
				[ '*' ],
			],

			[
				'Must not duplicate primary keys into column selector',
				// Expected column select.
				[ 'foo', 'bar', 'baz' ],
				// primary keys
				[ 'foo', 'bar', ],
				// setFetchColumn
				[ 'bar', 'baz' ],
			],
		];
	}

	/**
	 * @dataProvider provider_readerSetFetchColumns
	 */
	public function testReaderSetFetchColumns(
		$message, array $columns, array $primaryKeys, array $fetchColumns
	) {
		$db = $this->mockDb( [ 'select' ] );
		$db->expects( $this->once() )
			->method( 'select' )
			// only testing second parameter of Database::select
			->with( [ 'some_table' ], $columns )
			->willReturn( new FakeResultWrapper( [] ) );

		$reader = new BatchRowIterator( $db, 'some_table', $primaryKeys, 22 );
		$reader->setFetchColumns( $fetchColumns );
		// triggers first database select
		$reader->rewind();
	}

	public static function provider_readerSelectConditions() {
		return [

			[
				"With single primary key must generate id > 'value'",
				// Expected second iteration
				[ "id_field > '3'" ],
				// Primary key(s)
				'id_field',
			],

			[
				'With multiple primary keys the first conditions ' .
					'must use >= and the final condition must use >',
				// Expected second iteration
				[ "id_field > '3' OR (id_field = '3' AND (foo > '103'))" ],
				// Primary key(s)
				[ 'id_field', 'foo' ],
			],

		];
	}

	/**
	 * Slightly hackish to use reflection, but asserting different parameters
	 * to consecutive calls of Database::select in phpunit is error prone
	 *
	 * @dataProvider provider_readerSelectConditions
	 */
	public function testReaderSelectConditionsMultiplePrimaryKeys(
		$message, $expectedSecondIteration, $primaryKeys, $batchSize = 3
	) {
		$results = $this->genSelectResult( $batchSize, $batchSize * 3, static function () {
			static $i = 0, $j = 100, $k = 1000;
			return [ 'id_field' => ++$i, 'foo' => ++$j, 'bar' => ++$k ];
		} );
		$db = $this->mockDbConsecutiveSelect( $results );

		$conditions = [ 'bar' => 42, 'baz' => 'hai' ];
		$reader = new BatchRowIterator( $db, 'some_table', $primaryKeys, $batchSize );
		$reader->addConditions( $conditions );

		$buildConditions = new ReflectionMethod( $reader, 'buildConditions' );
		$buildConditions->setAccessible( true );

		// On first iteration only the passed conditions must be used
		$this->assertEquals( [], $buildConditions->invoke( $reader ),
			'First iteration must return no extra conditions' );
		$reader->rewind();

		// Second iteration must use the maximum primary key of last set
		$this->assertEquals(
			$expectedSecondIteration,
			$buildConditions->invoke( $reader ),
			$message
		);
	}

	protected function mockDbConsecutiveSelect( array $retvals ) {
		$db = $this->mockDb( [ 'select', 'newSelectQueryBuilder', 'addQuotes' ] );
		$db->method( 'newSelectQueryBuilder' )->willReturnCallback( static function () use ( $db ) {
			return new SelectQueryBuilder( $db );
		} );
		$db->method( 'select' )
			->will( $this->consecutivelyReturnFromSelect( $retvals ) );
		$db->method( 'addQuotes' )
			->willReturnCallback( static function ( $value ) {
				return "'$value'"; // not real quoting: doesn't matter in test
			} );

		return $db;
	}

	protected function consecutivelyReturnFromSelect( array $results ) {
		$retvals = [];
		foreach ( $results as $rows ) {
			// The Database::select method returns result wrapper, so we do too.
			$retvals[] = $this->returnValue( new FakeResultWrapper( $rows ) );
		}

		return $this->onConsecutiveCalls( ...$retvals );
	}

	protected function genSelectResult( $batchSize, $numRows, $rowGenerator ) {
		$res = [];
		for ( $i = 0; $i < $numRows; $i += $batchSize ) {
			$rows = [];
			for ( $j = 0; $j < $batchSize && $i + $j < $numRows; $j++ ) {
				$rows[] = (object)$rowGenerator();
			}
			$res[] = $rows;
		}
		$res[] = []; // termination condition requires empty result for last row
		return $res;
	}

	protected function mockDb( $methods = [] ) {
		// @TODO: mock from Database
		// FIXME: the constructor normally sets mAtomicLevels and mSrvCache, and platform
		$databaseMysql = $this->getMockBuilder( Wikimedia\Rdbms\DatabaseMySQL::class )
			->disableOriginalConstructor()
			->onlyMethods( array_merge( [ 'isOpen' ], $methods ) )
			->getMock();

		$reflection = new ReflectionClass( $databaseMysql );
		$reflectionProperty = $reflection->getProperty( 'platform' );
		$reflectionProperty->setAccessible( true );
		$reflectionProperty->setValue( $databaseMysql, new SQLPlatform( $databaseMysql ) );

		$databaseMysql->method( 'isOpen' )
			->willReturn( true );
		return $databaseMysql;
	}
}
