/**
 * CHIP Payment Methods screen behaviour.
 *
 * Drives the add/edit drawer: family -> method selection, the live counters
 * and checks, and the checkout preview. Everything it decides is also enforced
 * server-side (the save handler intersects the selection with the family and
 * refuses an empty one), so this file only makes the screen honest about what
 * it is about to save.
 */
( function () {
	'use strict';

	var config = window.ChipBlocksAdmin || { families: {}, watch: [], strings: {} };
	var root = document.querySelector( '.chip-blocks' );
	if ( ! root ) {
		return;
	}

	var drawer = root.querySelector( '[data-chip-drawer]' );
	var scrim = root.querySelector( '[data-chip-scrim]' );
	var form = root.querySelector( '[data-chip-form]' );
	var familySelect = root.querySelector( '[data-chip-family]' );
	var blockIdField = root.querySelector( '[data-chip-block-id]' );
	var titleField = root.querySelector( '[data-chip-title]' );
	var descriptionField = root.querySelector( '[data-chip-description]' );
	var logoField = root.querySelector( '[data-chip-logo]' );
	var enabledField = root.querySelector( '[data-chip-enabled]' );
	var counter = root.querySelector( '[data-chip-counter]' );
	var status = root.querySelector( '[data-chip-status]' );
	var preview = root.querySelector( '[data-chip-preview]' );
	var drawerTitle = root.querySelector( '[data-chip-drawer-title]' );

	function each( nodes, callback ) {
		Array.prototype.forEach.call( nodes, callback );
	}

	function methodGroups() {
		return root.querySelectorAll( '[data-chip-family-methods]' );
	}

	function activeGroup() {
		return root.querySelector( '[data-chip-family-methods="' + familySelect.value + '"]' );
	}

	function checkedMethods() {
		var group = activeGroup();
		if ( ! group ) {
			return [];
		}
		return Array.prototype.filter.call( group.querySelectorAll( 'input[type="checkbox"]' ), function ( box ) {
			return box.checked;
		} ).map( function ( box ) {
			var label = box.parentNode.querySelector( 'span' );
			return { key: box.value, label: label ? label.textContent.trim() : box.value };
		} );
	}

	function syncFamily() {
		each( methodGroups(), function ( group ) {
			var isActive = group.getAttribute( 'data-chip-family-methods' ) === familySelect.value;
			group.classList.toggle( 'is-active', isActive );

			// A group that is not shown must not submit: a tick left behind in
			// another family would be intersected away on save, silently
			// dropping a method the merchant thought they had selected.
			each( group.querySelectorAll( 'input[type="checkbox"]' ), function ( box ) {
				box.disabled = ! isActive;
				if ( ! isActive ) {
					box.checked = false;
				}
			} );
		} );
	}

	function familyDefaults() {
		return config.families[ familySelect.value ] || {};
	}

	function escapeHtml( value ) {
		var div = document.createElement( 'div' );
		div.textContent = value;
		return div.innerHTML;
	}

	function previewRow( label, isNew ) {
		return '<div class="chip-blocks__preview-row' + ( isNew ? ' is-new' : '' ) + '">' +
			'<span class="dashicons dashicons-marker" style="color:#c3c4c7;font-size:12px;width:12px;height:12px"></span>' +
			'<span>' + escapeHtml( label ) + '</span></div>';
	}

	function previewMethods( labels ) {
		if ( ! labels.length ) {
			return '';
		}
		return '<div class="chip-blocks__preview-methods">' + escapeHtml( labels.join( ' · ' ) ) + '</div>';
	}

	function refresh() {
		var selected = checkedMethods();
		var labels = selected.map( function ( method ) {
			return method.label;
		} );
		var title = titleField.value.trim();
		var group = activeGroup();

		if ( counter ) {
			var total = group ? group.querySelectorAll( 'input[type="checkbox"]' ).length : 0;
			counter.textContent = ( config.strings.counter || '%1$s of %2$s methods selected' )
				.replace( '%1$s', String( selected.length ) )
				.replace( '%2$s', String( total ) );
		}

		if ( status ) {
			var named = ( config.watch || [] ).filter( function ( name ) {
				return title.toLowerCase().indexOf( name.toLowerCase() ) !== -1;
			} ).filter( function ( name ) {
				return ! labels.some( function ( label ) {
					return label.toLowerCase().indexOf( name.toLowerCase() ) !== -1;
				} );
			} );

			if ( 0 === selected.length ) {
				status.className = 'chip-blocks__status is-warn';
				status.textContent = config.strings.empty || 'Pick at least one method.';
			} else if ( named.length ) {
				status.className = 'chip-blocks__status is-warn';
				status.textContent = ( config.strings.mismatch || 'The checkout title names %s, but that is not in the methods above.' ).replace( '%s', named.join( ', ' ) );
			} else if ( 1 === selected.length ) {
				status.className = 'chip-blocks__status';
				status.textContent = ( config.strings.single || 'Single-method block: checkout submits %s directly.' ).replace( '%s', labels[ 0 ] );
			} else {
				status.className = 'chip-blocks__status';
				status.textContent = ( config.strings.multi || 'Dropdown block: %s methods, sent to CHIP as the payment-method whitelist.' ).replace( '%s', String( selected.length ) );
			}
		}

		if ( preview ) {
			var rows = root.querySelectorAll( '[data-chip-block-row]' );
			var html = '';
			var inserted = false;

			each( rows, function ( row ) {
				var isEdited = '' !== blockIdField.value && row.getAttribute( 'data-chip-block-id' ) === blockIdField.value;
				html += previewRow( row.getAttribute( 'data-title' ), false );
				if ( isEdited ) {
					html += previewMethods( labels );
					inserted = true;
				}
			} );

			if ( ! inserted ) {
				html += previewRow( title || familyDefaults().title || '', true );
				html += previewMethods( labels );
			}

			preview.innerHTML = html;
		}
	}

	function resetDrawer() {
		blockIdField.value = '';
		each( methodGroups(), function ( group ) {
			each( group.querySelectorAll( 'input[type="checkbox"]' ), function ( box ) {
				box.checked = false;
			} );
		} );
		familySelect.disabled = false;
		titleField.value = '';
		descriptionField.value = '';
		logoField.value = 'auto';
		enabledField.value = 'yes';
		drawerTitle.textContent = config.strings.addTitle || 'Add payment method';
	}

	function open() {
		drawer.classList.add( 'is-open' );
		drawer.setAttribute( 'aria-hidden', 'false' );
		scrim.classList.add( 'is-open' );
		refresh();
	}

	function close() {
		drawer.classList.remove( 'is-open' );
		drawer.setAttribute( 'aria-hidden', 'true' );
		scrim.classList.remove( 'is-open' );
	}

	function openForNew() {
		resetDrawer();
		syncFamily();
		var defaults = familyDefaults();
		titleField.value = defaults.title || '';
		descriptionField.value = defaults.description || '';
		open();
	}

	function openForEdit( button ) {
		resetDrawer();
		blockIdField.value = button.getAttribute( 'data-block-id' ) || '';
		familySelect.value = button.getAttribute( 'data-family' ) || familySelect.value;
		familySelect.disabled = true;
		syncFamily();

		// syncFamily() clears hidden groups on purpose; the saved selection has
		// to win for an edit, so tick it afterwards.
		var saved = ( button.getAttribute( 'data-methods' ) || '' ).split( ',' ).filter( Boolean );
		each( ( activeGroup() || document.createElement( 'div' ) ).querySelectorAll( 'input[type="checkbox"]' ), function ( box ) {
			box.checked = saved.indexOf( box.value ) !== -1;
		} );

		titleField.value = button.getAttribute( 'data-title' ) || '';
		descriptionField.value = button.getAttribute( 'data-description' ) || '';
		logoField.value = button.getAttribute( 'data-logo' ) || 'auto';
		enabledField.value = button.getAttribute( 'data-enabled' ) || 'yes';
		drawerTitle.textContent = config.strings.editTitle || 'Edit payment method';
		open();
	}

	each( root.querySelectorAll( '[data-chip-drawer-open]' ), function ( button ) {
		button.addEventListener( 'click', function ( event ) {
			event.preventDefault();
			if ( button.getAttribute( 'data-block-id' ) ) {
				openForEdit( button );
			} else {
				openForNew();
			}
		} );
	} );

	each( root.querySelectorAll( '[data-chip-drawer-close]' ), function ( button ) {
		button.addEventListener( 'click', close );
	} );

	if ( scrim ) {
		scrim.addEventListener( 'click', close );
	}

	document.addEventListener( 'keydown', function ( event ) {
		if ( 'Escape' === event.key && drawer && drawer.classList.contains( 'is-open' ) ) {
			close();
		}
	} );

	familySelect.addEventListener( 'change', function () {
		syncFamily();
		var defaults = familyDefaults();
		titleField.value = defaults.title || '';
		descriptionField.value = defaults.description || '';
		logoField.value = 'auto';
		refresh();
	} );

	titleField.addEventListener( 'input', refresh );

	form.addEventListener( 'submit', function ( event ) {
		if ( 0 === checkedMethods().length ) {
			event.preventDefault();
			if ( status ) {
				status.className = 'chip-blocks__status is-warn';
				status.textContent = config.strings.empty || 'Pick at least one method.';
			}
		}
	} );

	// The preview has to know which row is being edited, so copy the block id
	// from the row's Edit button onto the row itself.
	each( root.querySelectorAll( '[data-chip-block-row]' ), function ( row ) {
		var edit = row.querySelector( '[data-block-id]' );
		if ( edit && edit.getAttribute( 'data-block-id' ) ) {
			row.setAttribute( 'data-chip-block-id', edit.getAttribute( 'data-block-id' ) );
		}
	} );

	refresh();
}() );
