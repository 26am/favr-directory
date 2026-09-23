/**
 * Favr Directory — block editor UI (no build step; uses WordPress globals).
 */
( function ( wp ) {
	'use strict';

	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var __ = wp.i18n.__;
	var registerBlockType = wp.blocks.registerBlockType;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var ServerSideRender = wp.serverSideRender;
	var useSelect = wp.data.useSelect;
	var C = wp.components;

	function withDefault( value, fallback ) {
		return typeof value === 'undefined' ? fallback : value;
	}

	function termOptions( terms, emptyLabel ) {
		var options = [ { label: emptyLabel, value: '' } ];
		( terms || [] ).forEach( function ( term ) {
			options.push( { label: term.name, value: term.slug } );
		} );
		return options;
	}

	registerBlockType( 'favr-directory/directory', {
		edit: function ( props ) {
			var a = props.attributes;
			var set = props.setAttributes;
			var blockProps = useBlockProps();

			var data = useSelect( function ( select ) {
				var core = select( 'core' );
				return {
					categories: core.getEntityRecords( 'taxonomy', 'favr_business_cat', { per_page: 100, hide_empty: false, _fields: 'id,name,slug' } ),
					levels: core.getEntityRecords( 'taxonomy', 'favr_member_level', { per_page: 50, hide_empty: false, _fields: 'id,name,slug' } )
				};
			}, [] );

			return el(
				Fragment,
				null,
				el(
					InspectorControls,
					null,
					el(
						C.PanelBody,
						{ title: __( 'Content', 'favr-directory' ), initialOpen: true },
						el( C.TextControl, {
							label: __( 'Heading (optional)', 'favr-directory' ),
							value: a.title || '',
							onChange: function ( v ) {
								set( { title: v } );
							}
						} ),
						el( C.SelectControl, {
							label: __( 'Only this category', 'favr-directory' ),
							value: a.category || '',
							options: termOptions( data.categories, __( 'All categories', 'favr-directory' ) ),
							onChange: function ( v ) {
								set( { category: v } );
							}
						} ),
						el( C.SelectControl, {
							label: __( 'Only this membership level', 'favr-directory' ),
							value: a.level || '',
							options: termOptions( data.levels, __( 'All levels', 'favr-directory' ) ),
							onChange: function ( v ) {
								set( { level: v } );
							}
						} ),
						el( C.ToggleControl, {
							label: __( 'Featured businesses only', 'favr-directory' ),
							checked: !! a.featuredOnly,
							onChange: function ( v ) {
								set( { featuredOnly: v } );
							}
						} ),
						el( C.RangeControl, {
							label: __( 'Businesses per page', 'favr-directory' ),
							value: a.perPage,
							min: 1,
							max: 60,
							allowReset: true,
							onChange: function ( v ) {
								set( { perPage: v } );
							}
						} ),
						el( C.SelectControl, {
							label: __( 'Order', 'favr-directory' ),
							value: a.order || 'rank',
							options: [
								{ label: __( 'Featured & membership level first', 'favr-directory' ), value: 'rank' },
								{ label: __( 'Alphabetical', 'favr-directory' ), value: 'name' },
								{ label: __( 'Newest first', 'favr-directory' ), value: 'newest' },
								{ label: __( 'Random', 'favr-directory' ), value: 'random' }
							],
							onChange: function ( v ) {
								set( { order: v } );
							}
						} )
					),
					el(
						C.PanelBody,
						{ title: __( 'Display', 'favr-directory' ), initialOpen: true },
						el( C.SelectControl, {
							label: __( 'Layout', 'favr-directory' ),
							value: a.layout || '',
							options: [
								{ label: __( 'Default (from settings)', 'favr-directory' ), value: '' },
								{ label: __( 'Grid of cards', 'favr-directory' ), value: 'grid' },
								{ label: __( 'Compact list', 'favr-directory' ), value: 'list' }
							],
							onChange: function ( v ) {
								set( { layout: v } );
							}
						} ),
						el( C.ToggleControl, {
							label: __( 'Search & category filter', 'favr-directory' ),
							checked: withDefault( a.showSearch, true ),
							onChange: function ( v ) {
								set( { showSearch: v } );
							}
						} ),
						el( C.ToggleControl, {
							label: __( 'A–Z letter filter', 'favr-directory' ),
							checked: withDefault( a.showLetters, true ),
							onChange: function ( v ) {
								set( { showLetters: v } );
							}
						} ),
						el( C.ToggleControl, {
							label: __( 'Membership level filter', 'favr-directory' ),
							checked: !! a.showLevelFilter,
							onChange: function ( v ) {
								set( { showLevelFilter: v } );
							}
						} ),
						el( C.ToggleControl, {
							label: __( 'Pagination', 'favr-directory' ),
							checked: withDefault( a.showPagination, true ),
							onChange: function ( v ) {
								set( { showPagination: v } );
							}
						} )
					)
				),
				el(
					'div',
					blockProps,
					el( C.Disabled, null, el( ServerSideRender, { block: 'favr-directory/directory', attributes: a } ) )
				)
			);
		},
		save: function () {
			return null;
		}
	} );

	registerBlockType( 'favr-directory/business-profile', {
		edit: function ( props ) {
			var blockProps = useBlockProps();
			var postId = props.context && props.context.postId;
			var postType = props.context && props.context.postType;

			if ( ! postId || 'favr_business' !== postType ) {
				return el(
					'div',
					blockProps,
					el( C.Placeholder, {
						icon: 'id-alt',
						label: __( 'Business Profile', 'favr-directory' ),
						instructions: __( 'Shows the full profile of the business being viewed: contact details, hours, map, gallery and more.', 'favr-directory' )
					} )
				);
			}
			return el(
				'div',
				blockProps,
				el( C.Disabled, null, el( ServerSideRender, { block: 'favr-directory/business-profile', urlQueryArgs: { post_id: postId } } ) )
			);
		},
		save: function () {
			return null;
		}
	} );

	registerBlockType( 'favr-directory/my-listing', {
		edit: function () {
			return el(
				'div',
				useBlockProps(),
				el( C.Placeholder, {
					icon: 'edit',
					label: __( 'My Listing', 'favr-directory' ),
					instructions: __( 'Logged-in business representatives see a form to update their directory listing here. Everyone else sees a login prompt.', 'favr-directory' )
				} )
			);
		},
		save: function () {
			return null;
		}
	} );
} )( window.wp );
