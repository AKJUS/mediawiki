<?php
/**
 * Copyright © 2014 Wikimedia Foundation and contributors
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

use MediaWiki\Context\DerivativeContext;
use MediaWiki\Context\IContextSource;
use MediaWiki\Html\Html;
use MediaWiki\Html\HtmlHelper;
use MediaWiki\Json\FormatJson;
use MediaWiki\MainConfigNames;
use MediaWiki\MediaWikiServices;
use MediaWiki\Message\Message;
use MediaWiki\Output\OutputPage;
use MediaWiki\Skin\SkinFactory;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Specials\SpecialVersion;
use MediaWiki\Title\Title;
use MediaWiki\Utils\ExtensionInfo;
use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\Parsoid\Core\TOCData;
use Wikimedia\RemexHtml\Serializer\SerializerNode;

/**
 * Class to output help for an API module
 *
 * @since 1.25 completely rewritten
 * @ingroup API
 */
class ApiHelp extends ApiBase {
	private SkinFactory $skinFactory;

	public function __construct(
		ApiMain $main,
		string $action,
		SkinFactory $skinFactory
	) {
		parent::__construct( $main, $action );
		$this->skinFactory = $skinFactory;
	}

	public function execute() {
		$params = $this->extractRequestParams();
		$modules = [];

		foreach ( $params['modules'] as $path ) {
			$modules[] = $this->getModuleFromPath( $path );
		}

		// Get the help
		$context = new DerivativeContext( $this->getMain()->getContext() );
		$context->setSkin( $this->skinFactory->makeSkin( 'apioutput' ) );
		$context->setLanguage( $this->getMain()->getLanguage() );
		$context->setTitle( SpecialPage::getTitleFor( 'ApiHelp' ) );
		$out = new OutputPage( $context );
		$out->setRobotPolicy( 'noindex,nofollow' );
		$out->setCopyrightUrl( 'https://www.mediawiki.org/wiki/Special:MyLanguage/Copyright' );
		$out->disallowUserJs();
		$context->setOutput( $out );

		self::getHelp( $context, $modules, $params );

		// Grab the output from the skin
		ob_start();
		$context->getOutput()->output();
		$html = ob_get_clean();

		$result = $this->getResult();
		if ( $params['wrap'] ) {
			$data = [
				'mime' => 'text/html',
				'filename' => 'api-help.html',
				'help' => $html,
			];
			ApiResult::setSubelementsList( $data, 'help' );
			$result->addValue( null, $this->getModuleName(), $data );
		} else {
			// Show any errors at the top of the HTML
			$transform = [
				'Types' => [ 'AssocAsObject' => true ],
				'Strip' => 'all',
			];
			$errors = array_filter( [
				'errors' => $this->getResult()->getResultData( [ 'errors' ], $transform ),
				'warnings' => $this->getResult()->getResultData( [ 'warnings' ], $transform ),
			] );
			if ( $errors ) {
				$json = FormatJson::encode( $errors, true, FormatJson::UTF8_OK );
				// Escape any "--", some parsers might interpret that as end-of-comment.
				// The above already escaped any "<" and ">".
				$json = str_replace( '--', '-\u002D', $json );
				$html = "<!-- API warnings and errors:\n$json\n-->\n$html";
			}

			$result->reset();
			$result->addValue( null, 'text', $html, ApiResult::NO_SIZE_CHECK );
			$result->addValue( null, 'mime', 'text/html', ApiResult::NO_SIZE_CHECK );
			$result->addValue( null, 'filename', 'api-help.html', ApiResult::NO_SIZE_CHECK );
		}
	}

	/**
	 * Generate help for the specified modules
	 *
	 * Help is placed into the OutputPage object returned by
	 * $context->getOutput().
	 *
	 * Recognized options include:
	 *  - headerlevel: (int) Header tag level
	 *  - nolead: (bool) Skip the inclusion of api-help-lead
	 *  - noheader: (bool) Skip the inclusion of the top-level section headers
	 *  - submodules: (bool) Include help for submodules of the current module
	 *  - recursivesubmodules: (bool) Include help for submodules recursively
	 *  - helptitle: (string) Title to link for additional modules' help. Should contain $1.
	 *  - toc: (bool) Include a table of contents
	 *
	 * @param IContextSource $context
	 * @param ApiBase[]|ApiBase $modules
	 * @param array $options Formatting options (described above)
	 */
	public static function getHelp( IContextSource $context, $modules, array $options ) {
		if ( !is_array( $modules ) ) {
			$modules = [ $modules ];
		}

		$out = $context->getOutput();
		$out->addModuleStyles( [
			'mediawiki.hlist',
			'mediawiki.apipretty',
		] );
		$out->setPageTitleMsg( $context->msg( 'api-help-title' ) );

		$services = MediaWikiServices::getInstance();
		$cache = $services->getMainWANObjectCache();
		$cacheKey = null;
		if ( count( $modules ) == 1 && $modules[0] instanceof ApiMain &&
			$options['recursivesubmodules'] &&
			$context->getLanguage()->equals( $services->getContentLanguage() )
		) {
			$cacheHelpTimeout = $context->getConfig()->get( MainConfigNames::APICacheHelpTimeout );
			if ( $cacheHelpTimeout > 0 ) {
				// Get help text from cache if present
				$cacheKey = $cache->makeKey( 'apihelp', $modules[0]->getModulePath(),
					(int)!empty( $options['toc'] ),
					str_replace( ' ', '_', SpecialVersion::getVersion( 'nodb' ) ) );
				$cached = $cache->get( $cacheKey );
				if ( $cached ) {
					$out->addHTML( $cached );
					return;
				}
			}
		}
		if ( $out->getHTML() !== '' ) {
			// Don't save to cache, there's someone else's content in the page
			// already
			$cacheKey = null;
		}

		$options['recursivesubmodules'] = !empty( $options['recursivesubmodules'] );
		$options['submodules'] = $options['recursivesubmodules'] || !empty( $options['submodules'] );

		// Prepend lead
		if ( empty( $options['nolead'] ) ) {
			$msg = $context->msg( 'api-help-lead' );
			if ( !$msg->isDisabled() ) {
				$out->addHTML( $msg->parseAsBlock() );
			}
		}

		$haveModules = [];
		$html = self::getHelpInternal( $context, $modules, $options, $haveModules );
		if ( !empty( $options['toc'] ) && $haveModules ) {
			$out->addTOCPlaceholder( TOCData::fromLegacy( array_values( $haveModules ) ) );
		}
		$out->addHTML( $html );

		$helptitle = $options['helptitle'] ?? null;
		$html = self::fixHelpLinks( $out->getHTML(), $helptitle, $haveModules );
		$out->clearHTML();
		$out->addHTML( $html );

		if ( $cacheKey !== null ) {
			// @phan-suppress-next-line PhanPossiblyUndeclaredVariable $cacheHelpTimeout declared when $cacheKey is set
			$cache->set( $cacheKey, $out->getHTML(), $cacheHelpTimeout );
		}
	}

	/**
	 * Replace Special:ApiHelp links with links to api.php
	 *
	 * @param string $html
	 * @param string|null $helptitle Title to link to rather than api.php, must contain '$1'
	 * @param array $localModules Keys are modules to link within the current page, values are ignored
	 * @return string
	 */
	public static function fixHelpLinks( $html, $helptitle = null, $localModules = [] ) {
		return HtmlHelper::modifyElements(
			$html,
			static function ( SerializerNode $node ): bool {
				return $node->name === 'a'
					&& isset( $node->attrs['href'] )
					&& !str_contains( $node->attrs['class'] ?? '', 'apihelp-linktrail' );
			},
			static function ( SerializerNode $node ) use ( $helptitle, $localModules ): SerializerNode {
				$href = $node->attrs['href'];
				// FIXME This can't be right to do this in a loop
				do {
					$old = $href;
					$href = rawurldecode( $href );
				} while ( $old !== $href );
				if ( preg_match( '!Special:ApiHelp/([^&/|#]+)((?:#.*)?)!', $href, $m ) ) {
					if ( isset( $localModules[$m[1]] ) ) {
						$href = $m[2] === '' ? '#' . $m[1] : $m[2];
					} elseif ( $helptitle !== null ) {
						$href = Title::newFromText( str_replace( '$1', $m[1], $helptitle ) . $m[2] )
							->getFullURL();
					} else {
						$href = wfAppendQuery( wfScript( 'api' ), [
							'action' => 'help',
							'modules' => $m[1],
						] ) . $m[2];
					}
					$node->attrs['href'] = $href;
					unset( $node->attrs['title'] );
				}

				return $node;
			}
		);
	}

	/**
	 * Wrap a message in HTML with a class.
	 *
	 * @param Message $msg
	 * @param string $class
	 * @param string $tag
	 * @return string
	 */
	private static function wrap( Message $msg, $class, $tag = 'span' ) {
		return Html::rawElement( $tag, [ 'class' => $class ],
			$msg->parse()
		);
	}

	/**
	 * Recursively-called function to actually construct the help
	 *
	 * @param IContextSource $context
	 * @param ApiBase[] $modules
	 * @param array $options
	 * @param array &$haveModules
	 * @return string
	 */
	private static function getHelpInternal( IContextSource $context, array $modules,
		array $options, &$haveModules
	) {
		$out = '';

		$level = empty( $options['headerlevel'] ) ? 2 : $options['headerlevel'];
		if ( empty( $options['tocnumber'] ) ) {
			$tocnumber = [ 2 => 0 ];
		} else {
			$tocnumber = &$options['tocnumber'];
		}

		foreach ( $modules as $module ) {
			$paramValidator = $module->getMain()->getParamValidator();
			$tocnumber[$level]++;
			$path = $module->getModulePath();
			$module->setContext( $context );
			$help = [
				'header' => '',
				'flags' => '',
				'description' => '',
				'help-urls' => '',
				'parameters' => '',
				'examples' => '',
				'submodules' => '',
			];

			if ( empty( $options['noheader'] ) || !empty( $options['toc'] ) ) {
				$anchor = $path;
				$i = 1;
				while ( isset( $haveModules[$anchor] ) ) {
					$anchor = $path . '|' . ++$i;
				}

				if ( $module->isMain() ) {
					$headerContent = $context->msg( 'api-help-main-header' )->parse();
					$headerAttr = [
						'class' => 'apihelp-header',
					];
				} else {
					$name = $module->getModuleName();
					$headerContent = htmlspecialchars(
						$module->getParent()->getModuleManager()->getModuleGroup( $name ) . "=$name"
					);
					if ( $module->getModulePrefix() !== '' ) {
						$headerContent .= ' ' .
							$context->msg( 'parentheses', $module->getModulePrefix() )->parse();
					}
					// Module names are always in English and not localized,
					// so English language and direction must be set explicitly,
					// otherwise parentheses will get broken in RTL wikis
					$headerAttr = [
						'class' => [ 'apihelp-header', 'apihelp-module-name' ],
						'dir' => 'ltr',
						'lang' => 'en',
					];
				}

				$headerAttr['id'] = $anchor;

				// T326687: Maybe transition to using a SectionMetadata object?
				$haveModules[$anchor] = [
					'toclevel' => count( $tocnumber ),
					'level' => $level,
					'anchor' => $anchor,
					'line' => $headerContent,
					'number' => implode( '.', $tocnumber ),
					'index' => '',
				];
				if ( empty( $options['noheader'] ) ) {
					$help['header'] .= Html::rawElement(
						'h' . min( 6, $level ),
						$headerAttr,
						$headerContent
					);
				}
			} else {
				$haveModules[$path] = true;
			}

			$links = [];
			$any = false;
			for ( $m = $module; $m !== null; $m = $m->getParent() ) {
				$name = $m->getModuleName();
				if ( $name === 'main_int' ) {
					$name = 'main';
				}

				if ( count( $modules ) === 1 && $m === $modules[0] &&
					!( !empty( $options['submodules'] ) && $m->getModuleManager() )
				) {
					$link = Html::element( 'b', [ 'dir' => 'ltr', 'lang' => 'en' ], $name );
				} else {
					$link = SpecialPage::getTitleFor( 'ApiHelp', $m->getModulePath() )->getLocalURL();
					$link = Html::element( 'a',
						[ 'href' => $link, 'class' => 'apihelp-linktrail', 'dir' => 'ltr', 'lang' => 'en' ],
						$name
					);
					$any = true;
				}
				array_unshift( $links, $link );
			}
			if ( $any ) {
				$help['header'] .= self::wrap(
					$context->msg( 'parentheses' )
						->rawParams( $context->getLanguage()->pipeList( $links ) ),
					'apihelp-linktrail', 'div'
				);
			}

			$flags = $module->getHelpFlags();
			$help['flags'] .= Html::openElement( 'div',
				[ 'class' => [ 'apihelp-block', 'apihelp-flags' ] ] );
			$msg = $context->msg( 'api-help-flags' );
			if ( !$msg->isDisabled() ) {
				$help['flags'] .= self::wrap(
					$msg->numParams( count( $flags ) ), 'apihelp-block-head', 'div'
				);
			}
			$help['flags'] .= Html::openElement( 'ul' );
			foreach ( $flags as $flag ) {
				$help['flags'] .= Html::rawElement( 'li', [],
					// The follow classes are used here:
					// * apihelp-flag-generator
					// * apihelp-flag-internal
					// * apihelp-flag-mustbeposted
					// * apihelp-flag-readrights
					// * apihelp-flag-writerights
					self::wrap( $context->msg( "api-help-flag-$flag" ), "apihelp-flag-$flag" )
				);
			}
			$sourceInfo = $module->getModuleSourceInfo();
			if ( $sourceInfo ) {
				if ( isset( $sourceInfo['namemsg'] ) ) {
					$extname = $context->msg( $sourceInfo['namemsg'] )->text();
				} else {
					// Probably English, so wrap it.
					$extname = Html::element( 'span', [ 'dir' => 'ltr', 'lang' => 'en' ], $sourceInfo['name'] );
				}
				$help['flags'] .= Html::rawElement( 'li', [],
					self::wrap(
						$context->msg( 'api-help-source', $extname, $sourceInfo['name'] ),
						'apihelp-source'
					)
				);

				$link = SpecialPage::getTitleFor( 'Version', 'License/' . $sourceInfo['name'] );
				if ( isset( $sourceInfo['license-name'] ) ) {
					$msg = $context->msg( 'api-help-license', $link,
						Html::element( 'span', [ 'dir' => 'ltr', 'lang' => 'en' ], $sourceInfo['license-name'] )
					);
				} elseif ( ExtensionInfo::getLicenseFileNames( dirname( $sourceInfo['path'] ) ) ) {
					$msg = $context->msg( 'api-help-license-noname', $link );
				} else {
					$msg = $context->msg( 'api-help-license-unknown' );
				}
				$help['flags'] .= Html::rawElement( 'li', [],
					self::wrap( $msg, 'apihelp-license' )
				);
			} else {
				$help['flags'] .= Html::rawElement( 'li', [],
					self::wrap( $context->msg( 'api-help-source-unknown' ), 'apihelp-source' )
				);
				$help['flags'] .= Html::rawElement( 'li', [],
					self::wrap( $context->msg( 'api-help-license-unknown' ), 'apihelp-license' )
				);
			}
			$help['flags'] .= Html::closeElement( 'ul' );
			$help['flags'] .= Html::closeElement( 'div' );

			foreach ( $module->getFinalDescription() as $msg ) {
				$msg->setContext( $context );
				$help['description'] .= $msg->parseAsBlock();
			}

			$urls = $module->getHelpUrls();
			if ( $urls ) {
				if ( !is_array( $urls ) ) {
					$urls = [ $urls ];
				}
				$help['help-urls'] .= Html::openElement( 'div',
					[ 'class' => [ 'apihelp-block', 'apihelp-help-urls' ] ]
				);
				$msg = $context->msg( 'api-help-help-urls' );
				if ( !$msg->isDisabled() ) {
					$help['help-urls'] .= self::wrap(
						$msg->numParams( count( $urls ) ), 'apihelp-block-head', 'div'
					);
				}
				$help['help-urls'] .= Html::openElement( 'ul' );
				foreach ( $urls as $url ) {
					$help['help-urls'] .= Html::rawElement( 'li', [],
						Html::element( 'a', [ 'href' => $url, 'dir' => 'ltr' ], $url )
					);
				}
				$help['help-urls'] .= Html::closeElement( 'ul' );
				$help['help-urls'] .= Html::closeElement( 'div' );
			}

			$params = $module->getFinalParams( ApiBase::GET_VALUES_FOR_HELP );
			$dynamicParams = $module->dynamicParameterDocumentation();
			$groups = [];
			if ( $params || $dynamicParams !== null ) {
				$help['parameters'] .= Html::openElement( 'div',
					[ 'class' => [ 'apihelp-block', 'apihelp-parameters' ] ]
				);
				$msg = $context->msg( 'api-help-parameters' );
				if ( !$msg->isDisabled() ) {
					$help['parameters'] .= self::wrap(
						$msg->numParams( count( $params ) ), 'apihelp-block-head', 'div'
					);
					if ( !$module->isMain() ) {
						// Add a note explaining that other parameters may exist.
						$help['parameters'] .= self::wrap(
							$context->msg( 'api-help-parameters-note' ), 'apihelp-block-header', 'div'
						);
					}
				}
				$help['parameters'] .= Html::openElement( 'dl' );

				$descriptions = $module->getFinalParamDescription();

				foreach ( $params as $name => $settings ) {
					$settings = $paramValidator->normalizeSettings( $settings );

					if ( $settings[ParamValidator::PARAM_TYPE] === 'submodule' ) {
						$groups[] = $name;
					}

					$encodedParamName = $module->encodeParamName( $name );
					$paramNameAttribs = [ 'dir' => 'ltr', 'lang' => 'en' ];
					if ( isset( $anchor ) ) {
						$paramNameAttribs['id'] = "$anchor:$encodedParamName";
					}
					$help['parameters'] .= Html::rawElement( 'dt', [],
						Html::element( 'span', $paramNameAttribs, $encodedParamName )
					);

					// Add description
					$description = [];
					if ( isset( $descriptions[$name] ) ) {
						foreach ( $descriptions[$name] as $msg ) {
							$msg->setContext( $context );
							$description[] = $msg->parseAsBlock();
						}
					}
					if ( !array_filter( $description ) ) {
						$description = [ self::wrap(
							$context->msg( 'api-help-param-no-description' ),
							'apihelp-empty'
						) ];
					}

					// Add "deprecated" flag
					if ( !empty( $settings[ParamValidator::PARAM_DEPRECATED] ) ) {
						$help['parameters'] .= Html::openElement( 'dd',
							[ 'class' => 'info' ] );
						$help['parameters'] .= self::wrap(
							$context->msg( 'api-help-param-deprecated' ),
							'apihelp-deprecated', 'strong'
						);
						$help['parameters'] .= Html::closeElement( 'dd' );
					}

					if ( $description ) {
						$description = implode( '', $description );
						$description = preg_replace( '!\s*</([oud]l)>\s*<\1>\s*!', "\n", $description );
						$help['parameters'] .= Html::rawElement( 'dd',
							[ 'class' => 'description' ], $description );
					}

					// Add usage info
					$info = [];
					$paramHelp = $paramValidator->getHelpInfo( $module, $name, $settings, [] );

					unset( $paramHelp[ParamValidator::PARAM_DEPRECATED] );

					if ( isset( $paramHelp[ParamValidator::PARAM_REQUIRED] ) ) {
						$paramHelp[ParamValidator::PARAM_REQUIRED]->setContext( $context );
						$info[] = $paramHelp[ParamValidator::PARAM_REQUIRED];
						unset( $paramHelp[ParamValidator::PARAM_REQUIRED] );
					}

					// Custom info?
					if ( !empty( $settings[ApiBase::PARAM_HELP_MSG_INFO] ) ) {
						foreach ( $settings[ApiBase::PARAM_HELP_MSG_INFO] as $i ) {
							$tag = array_shift( $i );
							$info[] = $context->msg( "apihelp-{$path}-paraminfo-{$tag}" )
								->numParams( count( $i ) )
								->params( $context->getLanguage()->commaList( $i ) )
								->params( $module->getModulePrefix() )
								->parse();
						}
					}

					// Templated?
					if ( !empty( $settings[ApiBase::PARAM_TEMPLATE_VARS] ) ) {
						$vars = [];
						$msg = 'api-help-param-templated-var-first';
						foreach ( $settings[ApiBase::PARAM_TEMPLATE_VARS] as $k => $v ) {
							$vars[] = $context->msg( $msg, $k, $module->encodeParamName( $v ) );
							$msg = 'api-help-param-templated-var';
						}
						$info[] = $context->msg( 'api-help-param-templated' )
							->numParams( count( $vars ) )
							->params( Message::listParam( $vars ) )
							->parse();
					}

					// Type documentation
					foreach ( $paramHelp as $m ) {
						$m->setContext( $context );
						$info[] = $m->parse();
					}

					foreach ( $info as $i ) {
						$help['parameters'] .= Html::rawElement( 'dd', [ 'class' => 'info' ], $i );
					}
				}

				if ( $dynamicParams !== null ) {
					$dynamicParams = $context->msg(
						Message::newFromSpecifier( $dynamicParams ),
						$module->getModulePrefix(),
						$module->getModuleName(),
						$module->getModulePath()
					);
					$help['parameters'] .= Html::element( 'dt', [], '*' );
					$help['parameters'] .= Html::rawElement( 'dd',
						[ 'class' => 'description' ], $dynamicParams->parse() );
				}

				$help['parameters'] .= Html::closeElement( 'dl' );
				$help['parameters'] .= Html::closeElement( 'div' );
			}

			$examples = $module->getExamplesMessages();
			if ( $examples ) {
				$help['examples'] .= Html::openElement( 'div',
					[ 'class' => [ 'apihelp-block', 'apihelp-examples' ] ] );
				$msg = $context->msg( 'api-help-examples' );
				if ( !$msg->isDisabled() ) {
					$help['examples'] .= self::wrap(
						$msg->numParams( count( $examples ) ), 'apihelp-block-head', 'div'
					);
				}

				$help['examples'] .= Html::openElement( 'dl' );
				foreach ( $examples as $qs => $msg ) {
					$msg = $context->msg(
						Message::newFromSpecifier( $msg ),
						$module->getModulePrefix(),
						$module->getModuleName(),
						$module->getModulePath()
					);

					$link = wfAppendQuery( wfScript( 'api' ), $qs );
					$sandbox = SpecialPage::getTitleFor( 'ApiSandbox' )->getLocalURL() . '#' . $qs;
					$help['examples'] .= Html::rawElement( 'dt', [], $msg->parse() );
					$help['examples'] .= Html::rawElement( 'dd', [],
						Html::element( 'a', [
							'href' => $link,
							'dir' => 'ltr',
							'rel' => 'nofollow',
						], "api.php?$qs" ) . ' ' .
						Html::rawElement( 'a', [ 'href' => $sandbox ],
							$context->msg( 'api-help-open-in-apisandbox' )->parse() )
					);
				}
				$help['examples'] .= Html::closeElement( 'dl' );
				$help['examples'] .= Html::closeElement( 'div' );
			}

			$subtocnumber = $tocnumber;
			$subtocnumber[$level + 1] = 0;
			$suboptions = [
				'submodules' => $options['recursivesubmodules'],
				'headerlevel' => $level + 1,
				'tocnumber' => &$subtocnumber,
				'noheader' => false,
			] + $options;

			// @phan-suppress-next-line PhanTypePossiblyInvalidDimOffset False positive
			if ( $options['submodules'] && $module->getModuleManager() ) {
				$manager = $module->getModuleManager();
				$submodules = [];
				foreach ( $groups as $group ) {
					$names = $manager->getNames( $group );
					sort( $names );
					foreach ( $names as $name ) {
						$submodules[] = $manager->getModule( $name );
					}
				}
				$help['submodules'] .= self::getHelpInternal(
					$context,
					$submodules,
					$suboptions,
					$haveModules
				);
			}

			$module->modifyHelp( $help, $suboptions, $haveModules );

			$module->getHookRunner()->onAPIHelpModifyOutput( $module, $help,
				$suboptions, $haveModules );

			$out .= implode( "\n", $help );
		}

		return $out;
	}

	/** @inheritDoc */
	public function shouldCheckMaxlag() {
		return false;
	}

	/** @inheritDoc */
	public function isReadMode() {
		return false;
	}

	/** @inheritDoc */
	public function getCustomPrinter() {
		$params = $this->extractRequestParams();
		if ( $params['wrap'] ) {
			return null;
		}

		$main = $this->getMain();
		$errorPrinter = $main->createPrinterByName( $main->getParameter( 'format' ) );
		return new ApiFormatRaw( $main, $errorPrinter );
	}

	/** @inheritDoc */
	public function getAllowedParams() {
		return [
			'modules' => [
				ParamValidator::PARAM_DEFAULT => 'main',
				ParamValidator::PARAM_ISMULTI => true,
			],
			'submodules' => false,
			'recursivesubmodules' => false,
			'wrap' => false,
			'toc' => false,
		];
	}

	/** @inheritDoc */
	protected function getExamplesMessages() {
		return [
			'action=help'
				=> 'apihelp-help-example-main',
			'action=help&modules=query&submodules=1'
				=> 'apihelp-help-example-submodules',
			'action=help&recursivesubmodules=1'
				=> 'apihelp-help-example-recursive',
			'action=help&modules=help'
				=> 'apihelp-help-example-help',
			'action=help&modules=query+info|query+categorymembers'
				=> 'apihelp-help-example-query',
		];
	}

	/** @inheritDoc */
	public function getHelpUrls() {
		return [
			'https://www.mediawiki.org/wiki/Special:MyLanguage/API:Main_page',
			'https://www.mediawiki.org/wiki/Special:MyLanguage/API:FAQ',
			'https://www.mediawiki.org/wiki/Special:MyLanguage/API:Quick_start_guide',
		];
	}
}

/** @deprecated class alias since 1.43 */
class_alias( ApiHelp::class, 'ApiHelp' );
