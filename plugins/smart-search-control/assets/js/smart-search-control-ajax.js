( function ( $ ) {
    'use strict';
    $( document ).ready( function () {

        let SearchForm = {

            lastQuery: '',
            ajaxRequest: null,
            selectedIndex: -1,

            /**
             * Initialize functions
             */
            init: function () {

                this.handleSearchInput();
                this.handleSearchFormSubmit();
                this.handleSuggestionNavigation();
                this.handleSeeMore();
                this.handleViewOption();
            },

            /**
             * handle list and grid view option
             */
            handleViewOption: function() {

                $( document ).on( 'click', '.ssc-button', function() {

                    let self = $(this);
                    $( '.ssc-button' ).removeClass( 'ssc-btn-background' );
                    self.addClass( 'ssc-btn-background' );
                } );

                /**
                 * when someone clicks on grid button
                 */
                $( document ).on( 'click', '.ssc-grid-btn', function() {

                    $( '.ssc-search-result-item' ).removeClass( 'ssc-list-view' );
                    $( '.ssc-search-result-item' ).addClass( 'ssc-grid-view' );
                    $( '.ssc-custom-search-results' ).addClass( 'ssc-flex' );
                    $( '.ssc-search-result-item .ssc-post-featured-wrapper' ).removeClass( 'ssc-responsive-width' );
                    $( '.ssc-search-result-item .ssc-post-featured-wrapper' ).addClass( 'ssc-full-width' );
                } );

                /**
                 * when someone clicks on list view button
                 */
                $( document ).on( 'click', '.ssc-list-btn', function() {

                    $( '.ssc-search-result-item' ).addClass( 'ssc-list-view' );
                    $( '.ssc-search-result-item' ).removeClass( 'ssc-grid-view' );
                    $( '.ssc-custom-search-results' ).removeClass( 'ssc-flex' );
                    $( '.ssc-search-result-item .ssc-post-featured-wrapper' ).removeClass( 'ssc-full-width' );
                    $( '.ssc-search-result-item .ssc-post-featured-wrapper' ).addClass( 'ssc-responsive-width' );
                } );
            },

            /**
             * Handle search input field interactions
             */
            handleSearchInput: function () {

                $( '.search-query' ).on( 'input', function () {

                    let self = $( this );
                    let parentContainer = self.closest( '.parent-container' );
                    let searchSuggestion = parentContainer.find( '.search-suggestions' );
                    let searchQuery = self.val().trim();

                    if ( searchQuery.length === 0 ) {

                        searchSuggestion.empty().hide().change();
                        return;
                    }

                    if ( searchQuery.length <= 2 ) {

                        searchSuggestion.empty().hide().change();
                        SearchForm.lastQuery = '';

                        if ( SearchForm.ajaxRequest ) {

                            SearchForm.ajaxRequest.abort();
                            SearchForm.ajaxRequest = null;
                        }

                        return;
                    }

                    if ( searchQuery !== SearchForm.lastQuery && searchQuery.length >= 3 ) {

                        SearchForm.lastQuery = searchQuery;
                        SearchForm.performSearchSuggestion( searchQuery, searchSuggestion , parentContainer );
                    }
                
                });
            },

            /**
             * Handles search form submission
             */
            handleSearchFormSubmit: function () {

                /**
                 * Handle clicking on search suggestions
                 */
                $( document ).on( 'click', '.suggestion-item', function () {

                    let parentContainer = $( this ).closest( '.parent-container' );
                    let searchQuery = parentContainer.find( '.search-query' );
                    let selectedText = $( this ).text();
                    let selectedLink = $( this ).find( 'a' ).attr( 'href' );

                    searchQuery.val( selectedText );
                    $( '.search-suggestions' ).fadeOut( 200 ).change();

                    if ( selectedLink ) {

                        window.location.href = selectedLink;
                    }
                });

                /**
                 * Close suggestions when clicking outside
                 */
                $( document ).on( 'click', function ( e ) {

                    if ( $( e.target ).closest( '.search-container' ).length === 0 ) {
                        $( '.search-suggestions' ).fadeOut( 200 ).change();
                    }
                });
            },

            /**
             * Perform AJAX search Suggestions
             */
            performSearchSuggestion: function ( searchQuery , searchSuggestionContainer , parentContainer ) {

                if ( !searchQuery ) return;

                let ssc_id = parentContainer.find(  'input[ name="smartsearch" ]' ).val();
                let block_post_types = parentContainer.find( 'input[ name="block_post_types" ]' ).val();
                
                // Get categories and tags from hidden inputs
                let categories = [];
                let tags = [];
                parentContainer.find( 'input[ name="categories[]" ]' ).each( function() {
                    categories.push( $( this ).val() );
                });
                parentContainer.find( 'input[ name="tags[]" ]' ).each( function() {
                    tags.push( $( this ).val() );
                });

                if ( SearchForm.ajaxRequest ) {

                    SearchForm.ajaxRequest.abort();
                }

                SearchForm.ajaxRequest = $.ajax( {

                    url: SMART_SEARCH_CONTROL.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'smarseco_smart_search_control_suggestion',
                        nonce: SMART_SEARCH_CONTROL.nonce,
                        search_query: searchQuery,
                        ssc_id: ssc_id,
                        block_post_types: block_post_types,
                        categories: categories,
                        tags: tags,
                    },

                    beforeSend: function () {

                        searchSuggestionContainer.html(
                            `<div class="no-results">${ SMART_SEARCH_CONTROL.search_msg }</div>` 
                        ).show().change();
                    },

                    success: function( response ) {

                        searchSuggestionContainer.empty().change();
                        SearchForm.selectedIndex = -1;

                        if ( response.success && response.data.search_results ) {

                            
                            let searchResults = response.data.search_results;
                            
                            let suggestionsHTML = searchResults
                                .slice( 0, 10 )
                                .map(( item ) => 

                                    `<li class="suggestion-item"><a href="${ item.permalink }">${ item.title }</a></li>`
                                )
                                .join( '' );

                                if ( searchResults.length > 9 ) {

                                    suggestionsHTML += `<a  href="javascript:void( 0 );" class="see-more" >${ SMART_SEARCH_CONTROL.more_msg }</a>`;

                                }

                            searchSuggestionContainer.html( `<ul class="suggestions-list">${ suggestionsHTML }</ul>` ).show().change();

                        } else {

                            let errorMessage = response.data.message;
                            searchSuggestionContainer.html( `<div class="no-results">${ errorMessage }</div>` ).show().change();
                        }
                    },

                    error: function ( xhr, status, error ) {

                        if ( status !== 'abort' ) {
                            console.error( 'AJAX Error:', xhr, status, error );
                        }
                    },
                });
            },

            /**
             * Handle keyboard navigation in search suggestions
             */
            handleSuggestionNavigation: function () {

                $( document ).on( 'keydown', '.smarseco-default-search-input', function ( e ) {
                    if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {

                        let searchSuggestions = $( this ).closest( '.parent-container' ).find( '.search-suggestions' );
                        let items = searchSuggestions.find( '.suggestion-item' );

                        if ( items.length === 0 ) return;

                        if ( e.key === 'ArrowDown' ) {

                            e.preventDefault();
                            SearchForm.selectedIndex = ( SearchForm.selectedIndex + 1 ) % items.length;

                        } else if ( e.key === 'ArrowUp' ) {

                            e.preventDefault();
                            SearchForm.selectedIndex = ( SearchForm.selectedIndex - 1 + items.length ) % items.length;
                        } 

                        items.removeClass( 'highlighted' );
                        items.eq( SearchForm.selectedIndex ).addClass( 'highlighted' ).change();
                        $( '.smarseco-default-search-input' ).val( items.eq( SearchForm.selectedIndex ).text() ).change();
                    }
                });

            },

            /**
             * Handle clicking on "See More" link
             */
            handleSeeMore: function () {

                $( document ).on( 'click', '.see-more', function ( e ) {

                    e.preventDefault();

                    let parentContainer = $( this ).closest( '.parent-container' );

                    parentContainer.find( '.smarseco-search-form' ).submit();

                });
            }
            
        };

        SearchForm.init();
    });

})( jQuery );
