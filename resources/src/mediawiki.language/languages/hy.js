/*!
 * Armenian (Հայերեն) language functions
 */

mw.language.convertGrammarMapping.hy = function ( word, form ) {
	// These rules are not perfect, but they are currently only used for site names so it doesn't
	// matter if they are wrong sometimes. Just add a special case for your site name if necessary.

	switch ( form ) {
		case 'genitive': // սեռական հոլով
			if ( word.slice( -1 ) === 'ա' ) {
				word = word.slice( 0, -1 ) + 'այի';
			} else if ( word.slice( -1 ) === 'ո' ) {
				word = word.slice( 0, -1 ) + 'ոյի';
			} else if ( word.slice( -4 ) === 'գիրք' ) {
				word = word.slice( 0, -4 ) + 'գրքի';
			} else {
				word = word + 'ի';
			}
			break;
	}
	return word;
};
