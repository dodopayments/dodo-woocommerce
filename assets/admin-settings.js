/**
 * Dodo Payments -- gateway settings screen behaviour.
 *
 * Three responsibilities: fold the long settings form into collapsible sections,
 * attach the WordPress colour picker to the theme colour grids, and drive the
 * repeatable "extra checkout questions" table.
 */
( function ( $ ) {
	'use strict';

	var settings = window.dodoPaymentsSettings || {};
	var STORAGE_KEY = 'dodoPaymentsOpenSections';

	/**
	 * Reads the set of sections the admin last left open.
	 *
	 * @return {Object} Map of section id to true.
	 */
	function readOpenSections() {
		try {
			return JSON.parse( window.localStorage.getItem( STORAGE_KEY ) ) || {};
		} catch ( e ) {
			return {};
		}
	}

	/**
	 * Persists the set of open sections.
	 *
	 * @param {Object} state Map of section id to true.
	 */
	function writeOpenSections( state ) {
		try {
			window.localStorage.setItem( STORAGE_KEY, JSON.stringify( state ) );
		} catch ( e ) {
			// Private browsing and full quotas are not worth failing over.
		}
	}

	/**
	 * Wraps each of the plugin's section headings and its fields in a collapsible panel.
	 *
	 * WooCommerce renders a `title` form field as an `<h3>` followed by an optional
	 * description and a fresh `<table class="form-table">`, all siblings. Each run
	 * of those is gathered into one panel.
	 */
	function buildPanels() {
		var open = readOpenSections();

		$( 'h3.wc-settings-sub-title' ).each( function () {
			var $heading = $( this );
			var id = $heading.attr( 'id' ) || '';

			if ( id.indexOf( settings.sectionPrefix ) !== 0 ) {
				return;
			}

			var $content = $heading.nextUntil( 'h3.wc-settings-sub-title' );
			var fieldCount = $content.find( '.form-table > tbody > tr' ).length;
			var isOpen = !! open[ id ];

			var $panel = $( '<div/>', { 'class': 'dodo-panel' + ( isOpen ? ' is-open' : '' ) } );
			var $body = $( '<div/>', { 'class': 'dodo-panel__body', id: id + '_body' } );

			var $toggle = $( '<button/>', {
				type: 'button',
				'class': 'dodo-panel__toggle',
				'aria-expanded': isOpen ? 'true' : 'false',
				'aria-controls': id + '_body'
			} );

			$toggle.append( $( '<span/>', { 'class': 'dodo-panel__caret', 'aria-hidden': 'true', text: '▸' } ) );
			$toggle.append( $( '<span/>', { text: $heading.text() } ) );

			if ( fieldCount ) {
				$toggle.append(
					$( '<span/>', {
						'class': 'dodo-panel__count',
						text: fieldCount === 1 ? settings.i18n.oneField : settings.i18n.manyFields.replace( '%d', fieldCount )
					} )
				);
			}

			$heading.before( $panel );
			$panel.append( $toggle ).append( $body );
			$body.append( $content );
			$heading.remove();

			$toggle.on( 'click', function () {
				var nowOpen = ! $panel.hasClass( 'is-open' );
				var state = readOpenSections();

				$panel.toggleClass( 'is-open', nowOpen );
				$toggle.attr( 'aria-expanded', nowOpen ? 'true' : 'false' );

				if ( nowOpen ) {
					state[ id ] = true;
				} else {
					delete state[ id ];
				}

				writeOpenSections( state );
			} );
		} );
	}

	/**
	 * Attaches the WordPress colour picker to every colour input.
	 *
	 * @param {jQuery} $scope Element to search within.
	 */
	function initColorPickers( $scope ) {
		var $inputs = $scope.find( '.dodo-color-input' );

		if ( ! $inputs.length || ! $.fn.wpColorPicker ) {
			return;
		}

		$inputs.wpColorPicker();
	}

	/**
	 * Reindexes the question rows so their input names stay contiguous after removals.
	 *
	 * @param {jQuery} $fieldset The questions fieldset.
	 */
	function reindexQuestions( $fieldset ) {
		var fieldKey = $fieldset.data( 'field-key' );

		$fieldset.find( '.dodo-questions__row' ).each( function ( index ) {
			$( this ).find( 'input, select' ).each( function () {
				var name = $( this ).attr( 'name' );

				if ( ! name ) {
					return;
				}

				$( this ).attr(
					'name',
					name.replace( /^(.*)\[[^\]]*\](\[[^\]]+\])$/, fieldKey + '[' + index + ']$2' )
				);
			} );
		} );
	}

	/**
	 * Reflects the current row count in the add button and empty-state message.
	 *
	 * @param {jQuery} $fieldset The questions fieldset.
	 */
	function refreshQuestionsState( $fieldset ) {
		var max = parseInt( $fieldset.data( 'max' ), 10 ) || 5;
		var count = $fieldset.find( '.dodo-questions__row' ).length;
		var atLimit = count >= max;

		$fieldset.find( '.dodo-questions__add' ).prop( 'disabled', atLimit );
		$fieldset.find( '.dodo-questions__limit' ).prop( 'hidden', ! atLimit );
		$fieldset.find( '.dodo-questions__empty' ).prop( 'hidden', count > 0 );
		$fieldset.find( '.dodo-questions__head' ).prop( 'hidden', count === 0 );
	}

	/**
	 * Shows the dropdown-choices input only for rows whose type is "dropdown".
	 *
	 * @param {jQuery} $row The question row.
	 */
	function syncRowOptions( $row ) {
		var isDropdown = $row.find( '.dodo-questions__type' ).val() === 'dropdown';
		$row.find( '.dodo-questions__options' ).prop( 'hidden', ! isDropdown );
	}

	/**
	 * Wires up the repeatable questions table.
	 */
	function initQuestions() {
		var $fieldset = $( '.dodo-questions' );

		if ( ! $fieldset.length ) {
			return;
		}

		refreshQuestionsState( $fieldset );
		$fieldset.find( '.dodo-questions__row' ).each( function () {
			syncRowOptions( $( this ) );
		} );

		$fieldset.on( 'click', '.dodo-questions__add', function () {
			var max = parseInt( $fieldset.data( 'max' ), 10 ) || 5;

			if ( $fieldset.find( '.dodo-questions__row' ).length >= max ) {
				return;
			}

			var index = $fieldset.find( '.dodo-questions__row' ).length;
			var markup = $fieldset.find( '.dodo-questions__template' ).html().replace( /__INDEX__/g, index );
			var $row = $( markup );

			$fieldset.find( '.dodo-questions__rows' ).append( $row );
			syncRowOptions( $row );
			refreshQuestionsState( $fieldset );
			$row.find( '.dodo-questions__key' ).trigger( 'focus' );
		} );

		$fieldset.on( 'click', '.dodo-questions__remove', function () {
			$( this ).closest( '.dodo-questions__row' ).remove();
			reindexQuestions( $fieldset );
			refreshQuestionsState( $fieldset );
		} );

		$fieldset.on( 'change', '.dodo-questions__type', function () {
			syncRowOptions( $( this ).closest( '.dodo-questions__row' ) );
		} );
	}

	/**
	 * Shows the custom cancel URL field only when the custom mode is selected.
	 */
	function initCancelUrlToggle() {
		var $mode = $( '#' + settings.cancelModeField );
		var $custom = $( '#' + settings.cancelCustomField );

		if ( ! $mode.length || ! $custom.length ) {
			return;
		}

		var $row = $custom.closest( 'tr' );

		function sync() {
			$row.toggle( $mode.val() === 'custom' );
		}

		$mode.on( 'change', sync );
		sync();
	}

	$( function () {
		buildPanels();
		initColorPickers( $( document ) );
		initQuestions();
		initCancelUrlToggle();
	} );
} )( jQuery );
