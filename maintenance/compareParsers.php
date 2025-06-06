<?php
/**
 * Take page text out of an XML dump file and render basic HTML out to files.
 * This is *NOT* suitable for publishing or offline use; it's intended for
 * running comparative tests of parsing behavior using real-world data.
 *
 * Templates etc are pulled from the local wiki database, not from the dump.
 *
 * Copyright © 2011 Platonides
 * https://www.mediawiki.org/
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
 * @ingroup Maintenance
 */

use MediaWiki\Content\WikitextContent;
use MediaWiki\Parser\ParserOptions;
use MediaWiki\User\User;
use Wikimedia\Diff\Diff;
use Wikimedia\Diff\UnifiedDiffFormatter;

// @codeCoverageIgnoreStart
require_once __DIR__ . '/dumpIterator.php';
// @codeCoverageIgnoreEnd

/**
 * Maintenance script to take page text out of an XML dump file and render
 * basic HTML out to files.
 *
 * @ingroup Maintenance
 */
class CompareParsers extends DumpIterator {

	/** @var int */
	private $count = 0;
	/** @var string|false */
	private $saveFailed = false;
	/** @var bool */
	private $stripParametersEnabled;
	/** @var bool */
	private $showParsedOutput;
	/** @var bool */
	private $showDiff;
	/** @var ParserOptions */
	private $options;
	/** @var int */
	private $failed;

	public function __construct() {
		parent::__construct();
		$this->addDescription( 'Run a file or dump with several parsers' );
		$this->addOption( 'parser1', 'The first parser to compare.', true, true );
		$this->addOption( 'parser2', 'The second parser to compare.', true, true );
		$this->addOption(
			'save-failed',
			'Folder in which articles which differ will be stored.',
			false,
			true
		);
		$this->addOption( 'show-diff', 'Show a diff of the two renderings.', false, false );
		$this->addOption(
			'diff-bin',
			'Binary to use for diffing (can also be provided by DIFF env var).',
			false,
			false
		);
		$this->addOption(
			'strip-parameters',
			'Remove parameters of html tags to increase readability.',
			false,
			false
		);
		$this->addOption(
			'show-parsed-output',
			'Show the parsed html if both Parsers give the same output.',
			false,
			false
		);
	}

	public function checkOptions() {
		if ( $this->hasOption( 'save-failed' ) ) {
			$this->saveFailed = $this->getOption( 'save-failed' );
		}

		$this->stripParametersEnabled = $this->hasOption( 'strip-parameters' );
		$this->showParsedOutput = $this->hasOption( 'show-parsed-output' );

		$this->showDiff = $this->hasOption( 'show-diff' );
		if ( $this->showDiff ) {
			$bin = $this->getOption( 'diff-bin', getenv( 'DIFF' ) );
			if ( $bin != '' ) {
				global $wgDiff;
				$wgDiff = $bin;
			}
		}

		$user = new User();
		$this->options = ParserOptions::newFromUser( $user );

		$this->failed = 0;
	}

	public function conclusions() {
		$this->error( "{$this->failed} failed revisions out of {$this->count}" );
		if ( $this->count > 0 ) {
			$this->output( " (" . ( $this->failed / $this->count ) . "%)\n" );
		}
	}

	private function stripParameters( string $text ): string {
		if ( !$this->stripParametersEnabled ) {
			return $text;
		}

		return preg_replace( '/(<a) [^>]+>/', '$1>', $text );
	}

	/**
	 * Callback function for each revision, parse with both parsers and compare
	 */
	public function processRevision( WikiRevision $rev ) {
		$title = $rev->getTitle();

		$parser1Name = $this->getOption( 'parser1' );
		$parser2Name = $this->getOption( 'parser2' );

		self::checkParserLocally( $parser1Name );
		self::checkParserLocally( $parser2Name );

		$parser1 = new $parser1Name();
		$parser2 = new $parser2Name();

		$content = $rev->getContent();

		if ( $content->getModel() !== CONTENT_MODEL_WIKITEXT ) {
			$this->error( "Page {$title->getPrefixedText()} does not contain wikitext "
				. "but {$content->getModel()}\n" );

			return;
		}

		/** @var WikitextContent $content */
		'@phan-var WikitextContent $content';
		$text = strval( $content->getText() );

		$output1 = $parser1->parse( $text, $title, $this->options );
		$output2 = $parser2->parse( $text, $title, $this->options );

		if ( $output1->getText() != $output2->getText() ) {
			$this->failed++;
			$this->error( "Parsing for {$title->getPrefixedText()} differs\n" );

			if ( $this->saveFailed ) {
				file_put_contents(
					$this->saveFailed . '/' . rawurlencode( $title->getPrefixedText() ) . ".txt",
					$text
				);
			}
			if ( $this->showDiff ) {
				$diffs = new Diff(
					explode( "\n", $this->stripParameters( $output1->getText() ) ),
					explode( "\n", $this->stripParameters( $output2->getText() ) )
				);
				$formatter = new UnifiedDiffFormatter();
				$unifiedDiff = $formatter->format( $diffs );

				$this->output( $unifiedDiff );
			}
		} else {
			$this->output( $title->getPrefixedText() . "\tOK\n" );

			if ( $this->showParsedOutput ) {
				$this->output( $this->stripParameters( $output1->getText() ) );
			}
		}
	}

	private static function checkParserLocally( string $parserName ) {
		/* Look for the parser in a file appropriately named in the current folder */
		if ( !class_exists( $parserName ) && file_exists( "$parserName.php" ) ) {
			global $wgAutoloadClasses;
			$wgAutoloadClasses[$parserName] = realpath( '.' ) . "/$parserName.php";
		}
	}
}

// @codeCoverageIgnoreStart
$maintClass = CompareParsers::class;
require_once RUN_MAINTENANCE_IF_MAIN;
// @codeCoverageIgnoreEnd
