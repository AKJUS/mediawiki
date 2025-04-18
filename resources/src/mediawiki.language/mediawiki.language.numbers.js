/*
 * Number-related utilities for mediawiki.language.
 */
( function () {

	/**
	 * Pad a string to guarantee that it is at least `size` length by
	 * filling with the character `ch` at either the start or end of the
	 * string. Pads at the start, by default.
	 *
	 * Example: Fill the string to length 10 with '+' characters on the right.
	 *
	 *     pad( 'blah', 10, '+', true ); // => 'blah++++++'
	 *
	 * @private
	 * @param {string} text The string to pad
	 * @param {number} size The length to pad to
	 * @param {string} [ch='0'] Character to pad with
	 * @param {boolean} [end=false] Adds padding at the end if true, otherwise pads at start
	 * @return {string}
	 */
	function pad( text, size, ch, end ) {
		if ( !ch ) {
			ch = '0';
		}

		const out = String( text );
		const count = Math.ceil( ( size - out.length ) / ch.length );
		const padStr = ch.repeat( Math.max( 0, count ) );

		return end ? out + padStr : padStr + out;
	}

	/**
	 * Apply numeric pattern to absolute value using options. Gives no
	 * consideration to local customs.
	 *
	 * Adapted from dojo/number library with thanks
	 * <http://dojotoolkit.org/reference-guide/1.8/dojo/number.html>
	 *
	 * @private
	 * @param {number} value the number to be formatted, ignores sign
	 * @param {string} pattern the number portion of a pattern (e.g. `#,##0.00`)
	 * @param {Object} [options] If provided, all option keys must be present:
	 * @param {string} options.decimal The decimal separator. Defaults to: `'.'`.
	 * @param {string} options.group The group separator. Defaults to: `','`.
	 * @param {number|null} options.minimumGroupingDigits
	 * @return {string}
	 */
	function commafyNumber( value, pattern, options ) {
		const patternParts = pattern.split( '.' ),
			maxPlaces = ( patternParts[ 1 ] || [] ).length,
			valueParts = String( Math.abs( value ) ).split( '.' ),
			fractional = valueParts[ 1 ] || '',
			pieces = [];
		let groupSize = 0,
			groupSize2 = 0;

		options = options || {
			group: ',',
			decimal: '.'
		};

		if ( isNaN( value ) ) {
			return value;
		}

		let padLength;
		if ( patternParts[ 1 ] ) {
			// Pad fractional with trailing zeros
			padLength = ( patternParts[ 1 ] && patternParts[ 1 ].lastIndexOf( '0' ) + 1 );

			if ( padLength > fractional.length ) {
				valueParts[ 1 ] = pad( fractional, padLength, '0', true );
			}

			// Truncate fractional
			if ( maxPlaces < fractional.length ) {
				valueParts[ 1 ] = fractional.slice( 0, maxPlaces );
			}
		} else {
			if ( valueParts[ 1 ] ) {
				valueParts.pop();
			}
		}

		// Pad whole with leading zeros
		const patternDigits = patternParts[ 0 ].replace( ',', '' );

		padLength = patternDigits.indexOf( '0' );

		if ( padLength !== -1 ) {
			padLength = patternDigits.length - padLength;

			if ( padLength > valueParts[ 0 ].length ) {
				valueParts[ 0 ] = pad( valueParts[ 0 ], padLength );
			}

			// Truncate whole
			if ( !patternDigits.includes( '#' ) && padLength ) {
				valueParts[ 0 ] = valueParts[ 0 ].slice( -padLength );
			}
		}

		// Add group separators
		let index = patternParts[ 0 ].lastIndexOf( ',' );

		if ( index !== -1 ) {
			groupSize = patternParts[ 0 ].length - index - 1;
			const remainder = patternParts[ 0 ].slice( 0, index );
			index = remainder.lastIndexOf( ',' );
			if ( index !== -1 ) {
				groupSize2 = remainder.length - index - 1;
			}
		}

		if (
			options.minimumGroupingDigits === null ||
			valueParts[ 0 ].length >= groupSize + options.minimumGroupingDigits
		) {
			for ( let whole = valueParts[ 0 ]; whole; ) {
				const off = groupSize ? whole.length - groupSize : 0;
				pieces.push( ( off > 0 ) ? whole.slice( off ) : whole );
				whole = ( off > 0 ) ? whole.slice( 0, off ) : '';

				if ( groupSize2 ) {
					groupSize = groupSize2;
					groupSize2 = null;
				}
			}
			valueParts[ 0 ] = pieces.reverse().join( options.group );
		}

		return valueParts.join( options.decimal );
	}

	/**
	 * Apply pattern to format value as a string.
	 *
	 * Using patterns from [Unicode TR35](https://www.unicode.org/reports/tr35/#Number_Format_Patterns).
	 *
	 * @param {number} value
	 * @param {string} pattern Pattern string as described by Unicode TR35
	 * @param {number|null} [minimumGroupingDigits=null]
	 * @throws {Error} If unable to find a number expression in `pattern`.
	 * @return {string}
	 * @private
	 */
	function commafyInternal( value, pattern, minimumGroupingDigits ) {
		const transformTable = mw.language.getSeparatorTransformTable(),
			group = transformTable[ ',' ] || ',',

			numberPatternRE = /[#0,]*[#0](?:\.0*#*)?/, // not precise, but good enough
			decimal = transformTable[ '.' ] || '.',
			patternList = pattern.split( ';' ),
			positivePattern = patternList[ 0 ];

		pattern = patternList[ ( value < 0 ) ? 1 : 0 ] || ( '-' + positivePattern );
		const numberPattern = positivePattern.match( numberPatternRE );

		minimumGroupingDigits = minimumGroupingDigits !== undefined ? minimumGroupingDigits : null;

		if ( !numberPattern ) {
			throw new Error( 'unable to find a number expression in pattern: ' + pattern );
		}

		return pattern.replace( numberPatternRE, commafyNumber( value, numberPattern[ 0 ], {
			minimumGroupingDigits: minimumGroupingDigits,
			decimal: decimal,
			group: group
		} ) );
	}

	/**
	 * Helper function to flip transformation tables.
	 *
	 * @param {...Object} Transformation tables
	 * @return {Object}
	 */
	function flipTransform() {
		const flipped = {};

		// Ensure we strip thousand separators. This might be overwritten.
		flipped[ ',' ] = '';

		for ( let i = 0; i < arguments.length; i++ ) {
			const table = arguments[ i ];
			for ( const key in table ) {
				// The thousand separator should be deleted
				flipped[ table[ key ] ] = key === ',' ? '' : key;
			}
		}

		return flipped;
	}

	Object.assign( mw.language, {

		/**
		 * Converts a number using `getDigitTransformTable()`.
		 *
		 * @memberof mw.language
		 * @param {number} num Value to be converted
		 * @param {boolean} [integer=false] Whether to convert the return value to an integer
		 * @return {number|string} Formatted number
		 */
		convertNumber: function ( num, integer ) {
			// Quick shortcut for plain numbers
			if ( integer && parseInt( num, 10 ) === num ) {
				return num;
			}

			// Load the transformation tables (can be empty)
			const digitTransformTable = mw.language.getDigitTransformTable();
			const separatorTransformTable = mw.language.getSeparatorTransformTable();

			let transformTable, numberString;
			if ( integer ) {
				// Reverse the digit transformation tables if we are doing unformatting
				transformTable = flipTransform( separatorTransformTable, digitTransformTable );
				numberString = String( num );
			} else {
				// This check being here means that digits can still be unformatted
				// even if we do not produce them.
				if ( mw.config.get( 'wgTranslateNumerals' ) ) {
					transformTable = digitTransformTable;
				}

				// Commaying is more complex, so we handle it here separately.
				// When unformatting, we just use separatorTransformTable.
				const pattern = mw.language.getData( mw.config.get( 'wgUserLanguage' ),
					'digitGroupingPattern' ) || '#,##0.###';
				const minimumGroupingDigits = mw.language.getData( mw.config.get( 'wgUserLanguage' ),
					'minimumGroupingDigits' ) || null;
				numberString = commafyInternal( num, pattern, minimumGroupingDigits );
			}

			let convertedNumber;
			if ( transformTable ) {
				convertedNumber = '';
				for ( let i = 0; i < numberString.length; i++ ) {
					if ( Object.prototype.hasOwnProperty.call( transformTable, numberString[ i ] ) ) {
						convertedNumber += transformTable[ numberString[ i ] ];
					} else {
						convertedNumber += numberString[ i ];
					}
				}
			} else {
				convertedNumber = numberString;
			}

			if ( integer ) {
				// Parse string to integer. This loses decimals!
				convertedNumber = parseInt( convertedNumber, 10 );
			}

			return convertedNumber;
		},

		/**
		 * Get the digit transform table for current UI language.
		 *
		 * @ignore
		 * @return {Object|Array}
		 */
		getDigitTransformTable: function () {
			return mw.language.getData( mw.config.get( 'wgUserLanguage' ),
				'digitTransformTable' ) || [];
		},

		/**
		 * Get the separator transform table for current UI language.
		 *
		 * @ignore
		 * @return {Object|Array}
		 */
		getSeparatorTransformTable: function () {
			return mw.language.getData( mw.config.get( 'wgUserLanguage' ),
				'separatorTransformTable' ) || [];
		}

	} );

}() );
