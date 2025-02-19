<template>
	<cdx-field
		:is-fieldset="true"
		:status="status"
		:messages="messages"
	>
		<cdx-lookup
			v-model:selected="selection"
			v-model:input-value="currentSearchTerm"
			class="mw-block-target"
			name="wpTarget"
			required
			:clearable="true"
			:menu-items="menuItems"
			:placeholder="$i18n( 'block-target-placeholder' ).text()"
			:start-icon="cdxIconSearch"
			@input="onInput"
			@change="onChange"
			@blur="onChange"
			@clear="onClear"
			@update:selected="onSelect"
		>
		</cdx-lookup>
		<template #label>
			{{ $i18n( 'block-target' ).text() }}
		</template>
		<div class="mw-block-conveniencelinks">
			<span v-if="status !== 'error' && targetExists">
				<a
					:href="mw.util.getUrl( contribsTitle )"
					:title="contribsTitle"
				>
					{{ $i18n( 'ipb-blocklist-contribs', targetUser ) }}
				</a>
			</span>
		</div>
		<component
			:is="customComponent"
			v-for="customComponent in customComponents"
			:key="customComponent.name"
			:target-user="targetExists ? targetUser : null"
		></component>
	</cdx-field>
</template>

<script>
const {
	computed,
	defineComponent,
	onMounted,
	ref,
	shallowRef,
	watch,
	DefineSetupFnComponent,
	Ref
} = require( 'vue' );
const { CdxLookup, CdxField } = require( '@wikimedia/codex' );
const { storeToRefs } = require( 'pinia' );
const { cdxIconSearch } = require( '../icons.json' );
const useBlockStore = require( '../stores/block.js' );
const api = new mw.Api();

/**
 * User lookup component for Special:Block.
 *
 * @todo Abstract for general use in MediaWiki (T375220)
 */
module.exports = exports = defineComponent( {
	name: 'UserLookup',
	components: { CdxLookup, CdxField },
	props: {
		modelValue: { type: [ String, null ], required: true }
	},
	emits: [
		'update:modelValue'
	],
	setup( props ) {
		const store = useBlockStore();
		const { targetExists, targetUser } = storeToRefs( store );
		/**
		 * Custom components to be added to the bottom of the field.
		 *
		 * @type {Ref<DefineSetupFnComponent>}
		 */
		const customComponents = shallowRef( [] );
		let htmlInput;

		onMounted( () => {
			// Get the input element.
			htmlInput = document.querySelector( 'input[name="wpTarget"]' );
			// Focus the input on mount.
			htmlInput.focus();
			/**
			 * Hook for custom components to be added to the UserLookup component.
			 *
			 * @event codex.userlookup
			 * @param {Ref<DefineSetupFnComponent[]>} customComponents
			 * @private
			 * @internal
			 */
			mw.hook( 'codex.userlookup' ).fire( customComponents );
		} );

		// Set a flag to keep track of pending API requests, so we can abort if
		// the target string changes
		let pending = false;

		// Codex Lookup component requires a v-modeled `selected` prop.
		// Until a selection is made, the value may be set to null.
		// We instead want to only update the targetUser for non-null values
		// (made either via selection, or the 'change' event).
		const selection = ref( props.modelValue || '' );
		// This is the source of truth for what should be the target user,
		// but it should only change on 'change' or 'select' events,
		// otherwise we'd fire off API queries for the block log unnecessarily.
		const currentSearchTerm = ref(
			props.modelValue || mw.config.get( 'blockTargetUserInput' ) || ''
		);
		const menuItems = ref( [] );
		const status = ref( 'default' );
		const messages = ref( {} );

		watch( targetUser, ( newValue ) => {
			if ( newValue ) {
				currentSearchTerm.value = newValue;
			}
		} );

		/**
		 * Check if a given target is valid
		 *
		 * @param {string} target
		 */
		function checkTargetExists( target ) {
			// Check if the target is a valid IP
			if ( mw.util.isIPAddress( target, true ) ) {
				status.value = 'default';
				store.formErrors = [];
				targetExists.value = true;
				return;
			}
			if ( !target ) {
				status.value = 'default';
				store.formErrors = [];
				targetExists.value = false;
				return;
			}
			// Check if the target is a valid user
			getUser( target ).then( ( data ) => {
				if ( !data || !data.users[ 0 ] || data.users[ 0 ].missing === true ) {
					status.value = 'error';
					store.formErrors = [ mw.message( 'nosuchusershort', target ).text() ];
					targetExists.value = false;
				} else {
					status.value = 'default';
					store.formErrors = [];
					targetExists.value = true;
				}
			} );
		}

		/**
		 * Get a single user
		 *
		 * @param {string} target
		 * @return {Promise}
		 */
		function getUser( target ) {
			const params = {
				action: 'query',
				format: 'json',
				formatversion: 2,
				list: 'users',
				ususers: target
			};

			return api.get( params )
				.then( ( response ) => response.query );
		}

		/**
		 * Get search results.
		 *
		 * @param {string} searchTerm
		 * @return {Promise}
		 */
		function fetchResults( searchTerm ) {
			const params = {
				action: 'query',
				format: 'json',
				formatversion: 2,
				list: 'allusers',
				aulimit: '10',
				auprefix: searchTerm
			};

			return api.get( params )
				.then( ( response ) => response.query );
		}

		/**
		 * Handle lookup input.
		 *
		 * @param {string} value
		 */
		function onInput( value ) {
			// Abort any existing request if one is still pending
			if ( pending ) {
				pending = false;
				api.abort();
			}

			// Internally track the current search term.
			currentSearchTerm.value = value;

			// Do nothing if we have no input.
			if ( !value ) {
				menuItems.value = [];
				return;
			}

			fetchResults( value )
				.then( ( data ) => {
					pending = false;

					// Make sure this data is still relevant first.
					if ( currentSearchTerm.value !== value ) {
						return;
					}

					// Reset the menu items if there are no results.
					if ( !data.allusers || data.allusers.length === 0 ) {
						menuItems.value = [];
						return;
					}

					// Build an array of menu items.
					menuItems.value = data.allusers.map( ( result ) => ( {
						label: result.name,
						value: result.name
					} ) );
				} )
				.catch( () => {
					// On error, set results to empty.
					menuItems.value = [];
				} );
		}

		/**
		 * Validate the input element.
		 *
		 * @param {HTMLInputElement} el
		 */
		function validate( el ) {
			if ( el.checkValidity() ) {
				status.value = 'default';
				messages.value = {};
			} else {
				status.value = 'error';
				messages.value = { error: el.validationMessage };
			}
		}

		/**
		 * Handle lookup change.
		 */
		function onChange() {
			// Use the currentSearchTerm value instead of the event target value,
			// since the event can be fired before the watcher updates the value.
			setTarget( currentSearchTerm.value );
		}

		/**
		 * When the clear button is clicked.
		 */
		function onClear() {
			store.resetForm( true );
			htmlInput.focus();
		}

		/**
		 * Handle lookup selection.
		 */
		function onSelect() {
			if ( selection.value !== null ) {
				setTarget( selection.value );
			}
		}

		/**
		 * Set the target user and trigger validation.
		 *
		 * @param {string} value
		 */
		function setTarget( value ) {
			checkTargetExists( value );
			validate( htmlInput );
			targetUser.value = value;
		}

		// Change the address bar to reflect the newly-selected target (while keeping all URL parameters).
		// Do this when the targetUser changes, which is not necessarily when the CdxLookup selection changes.
		watch( () => targetUser.value, () => {
			const specialBlockUrl = mw.util.getUrl( 'Special:Block' + ( targetUser.value ? '/' + targetUser.value : '' ) );
			if ( window.location.pathname !== specialBlockUrl ) {
				const newUrl = ( new URL( `${ specialBlockUrl }${ window.location.search }`, window.location.origin ) ).toString();
				window.history.replaceState( null, '', newUrl );
			}
		} );

		const contribsTitle = computed( () => `Special:Contributions/${ targetUser.value }` );

		return {
			mw,
			contribsTitle,
			targetExists,
			targetUser,
			menuItems,
			onChange,
			onInput,
			onClear,
			onSelect,
			cdxIconSearch,
			currentSearchTerm,
			selection,
			status,
			messages,
			customComponents
		};
	}
} );
</script>

<style lang="less">
.mw-block-conveniencelinks {
	a {
		font-size: 90%;
	}
}
</style>
