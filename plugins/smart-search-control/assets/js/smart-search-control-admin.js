( function ( $ ) {
    'use strict';
    $( document ).ready( function () {

        let AdminSearchSetting = {

            /**
             * Initialize functions
             */
            init: function () {

                this.cacheSelectors();
                this.bindEvents();
                this.adminNotice();
                this.handlePostTypeChange();
                this.integrateSelect2();
            },

            /**
             * Integrate select2 for categories and tags dropdown
             */
            integrateSelect2: function () {
                
                $( this.categoriesSelect ).select2({
                    placeholder: "Select categories",
                    allowClear: true,
                });

                $( this.tagsSelect ).select2( {
                    placeholder: "Select tags",
                    allowClear: true,
                });
            },

            /**
             * Handle post type checkbox change
             * Update categories & tags via AJAX
             */
            handlePostTypeChange: function () {
                
                let categoriesDropdownSelector = this.categoriesSelect;
                let tagsDropdownSelector = this.tagsSelect;

                $(document).on('change', '.custom-checkbox', function () {
                    
                    let self = $(this);
                    let form = self.closest('form#searchForm');
                    let postType = self.val();
                    let checked  = self.is(':checked');

                    let categoriesDropdown = form.find( categoriesDropdownSelector );
                    let tagsDropdown       = form.find( tagsDropdownSelector );

                    $.ajax({
                        url: SMARSECO_SETTING.ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'smarseco_get_taxonomies_by_post_type',
                            nonce: SMARSECO_SETTING.nonce_tax_query,
                            post_type: postType
                        },
                        success: function(response) {

                            if (!response.success) return;

                            // ----- Categories -----
                            if (response.data.categories_html) {
                                if (checked) {
                                    categoriesDropdown.append(response.data.categories_html).change();
                                } else {
                                    categoriesDropdown.find('optgroup[data-post-type="' + postType + '"]').remove();
                                }
                                categoriesDropdown.prop('disabled', false);
                            }

                            // ----- Tags -----
                            if (response.data.tags_html) {
                                if (checked) {
                                    tagsDropdown.append(response.data.tags_html).change();
                                } else {
                                    tagsDropdown.find('optgroup[data-post-type="' + postType + '"]').remove();
                                }
                                tagsDropdown.prop('disabled', false);
                            }

                        },
                        error: function() {
                            console.log('Error loading categories/tags for', postType);
                        }
                    });

                });
            },

            /**
             * Cache DOM selectors
             */
            cacheSelectors: function () {
                
                this.createtable      = $( ".create-table" );
                this.advanceSetting   = $( "#advance-toggle" )
                this.codeCopy         = $( ".shortcode-container" )
                this.selectAll        = $( "#select-all" )
                this.singleOptions    = $( ".custom-checkbox" )
                this.addBtn           = $( "#openModal" );
                this.editBtn          = $( ".edit-setting" );
                this.deleteBtn        = $( ".delete-setting" );
                this.shortcode        = $( ".shortcode-container" )
                this.modal            = $( "#searchModal" );
                this.closeModal       = $( ".closeModal" );
                this.shortcodeid      = $( ".code-id" );
                this.modelPlaceHolder = $( "#place_holder" );
                this.modelClass       = $( "#class" );
                this.modelID          = $( "#id" );
                this.modelPostType    = $( "input[name='post_type[]']" );
                this.modelBtn         = $( ".model-btn" );
                this.modalForm        = $( "#searchForm" );
                this.categoriesSelect = $( "#smarseco-categories" );
                this.tagsSelect       = $( "#smarseco-tags" );
            },

            /**
             * Bind events
             */
            bindEvents: function () {
                
                this.addBtn.on( "click", this.addNewSetting.bind( this ) );
                this.editBtn.on( "click", this.editSetting.bind( this ) );
                this.deleteBtn.on( "click", this.deleteSetting.bind( this ) );
                this.closeModal.on( "click", this.closeModalHandler.bind( this ) );
                this.createtable.on( "click", this.createDatabaseTable.bind( this ) );
                this.advanceSetting.on( "click", this.showAdvanceSetting.bind( this) );
                this.codeCopy.on( "click", this.copyShortCode.bind( this) );
                this.selectAll.on( "change", this.selectAllPost.bind( this) );
                this.singleOptions.on( "change", this.allsingleoptions.bind( this) );
            },

            /**
             * Add new setting and store it into database
             */
            addNewSetting: function () {

                this.modalForm.trigger( "reset" ).change();
                this.categoriesSelect.html('').change();
                this.categoriesSelect.prop('disabled', true).change();
                this.tagsSelect.html('').change();
                this.tagsSelect.prop('disabled', true).change();

                let categoriesDropdownSelector = this.categoriesSelect;
                let tagsDropdownSelector       = this.tagsSelect;

                this.modalForm.off( 'submit' ).on( 'submit', function ( e ) {
                    e.preventDefault();

                    let categories = AdminSearchSetting.collectTermsByTaxonomy( categoriesDropdownSelector );
                    let tags       = AdminSearchSetting.collectTermsByTaxonomy( tagsDropdownSelector );

                    let formData = {
                        action: 'smarseco_smart_search_control_setting',
                        nonce: SMARSECO_SETTING.nonce_add,
                        place_holder: AdminSearchSetting.modelPlaceHolder.val(),
                        class: AdminSearchSetting.modelClass.val(),
                        css_id:  AdminSearchSetting.modelID.val(),
                        post_type: $( 'input[name="post_type[]"]:checked' ).map( function () {
                            return $( this ).val();
                        } ).get(),
                        categories: categories,
                        tags: tags
                    };

                    let $submitBtn = $( '#submit-btn' );
                    let $loader = $( '#edit-loader' );
                    let $btnText = $submitBtn.find( '.btn-text' );
                
                    $btnText.hide();
                    $loader.show();
                    $submitBtn.prop("disabled", true);

                    $.ajax( {
                        type: 'POST',
                        url: SMARSECO_SETTING.ajaxurl,
                        data: formData,
                        dataType: 'json',
                        
                        success: function ( response ) {
                            $loader.hide().change();
                            $btnText.show().change();
                            AdminSearchSetting.modalForm.trigger( "reset" ).change();
                            AdminSearchSetting.modal.css( "display", "none" ).change();
                            localStorage.setItem( "admin_notice", JSON.stringify( response.data ) );
                            location.reload();
                            $submitBtn.prop("disabled", false);
                            
                        },
                        error: function () {
                            $loader.hide().change();
                            $btnText.show().change();
                            AdminSearchSetting.modal.css( "display", "none" ).change();
                            localStorage.setItem( "admin_notice", JSON.stringify( response.data ) );
                            location.reload();
                            $submitBtn.prop("disabled", false);
                        }
                    });
                });

                this.openModal();
            },

            /**
             * Edit the specific setting
             */
            editSetting: function ( event ) {

                let entry = JSON.parse( $( event.currentTarget ).attr( "data-entry" ) );

                let parsedData = JSON.parse( entry.data );

                let taxonomies = $(event.currentTarget).attr("data-taxonomies");
                let decoded = atob(taxonomies);
                let parsed  = JSON.parse(decoded);
                let categoriesHTML = parsed.categories;
                let tagsHTML       = parsed.tags;

                // Convert stdClass to plain object
                parsedData.categories = parsedData.categories ? Object.fromEntries(Object.entries(parsedData.categories)) : {};
                parsedData.tags       = parsedData.tags ? Object.fromEntries(Object.entries(parsedData.tags)) : {};
                
                this.shortcodeid.text( entry.id );
                this.modelPlaceHolder.val( parsedData.place_holder );
                this.modelClass.val( parsedData.class );
                this.modelID.val( parsedData.css_id );
                
                this.shortcode.css( "display", "inline-block" ).change();
                
                this.modelPostType.prop( "checked", false );
                
                if ( Array.isArray( parsedData.post_type ) ) { 

                    this.modelPostType.each( function () {
                        if ( parsedData.post_type.includes( $( this ).val() ) ) {
                            $( this ).prop( "checked", true );
                        }
                    });
                }
                
                if ( $ ( ".custom-checkbox:checked" ).length === $( ".custom-checkbox" ).length ) {

                    $( "#select-all" ).prop( "checked", true );
                } else {
                    $( "#select-all" ).prop( "checked", false );
                }

                if( categoriesHTML ) {
                    this.categoriesSelect.prop('disabled', false);
                    this.categoriesSelect.html( '' );
                    this.categoriesSelect.html( categoriesHTML ).change();
                }

                if( tagsHTML ) {
                    this.tagsSelect.prop('disabled', false);
                    this.tagsSelect.html( '' );
                    this.tagsSelect.html( tagsHTML ).change();
                }

                // Categories & Tags selection
                if ( parsedData.categories ) {
                    AdminSearchSetting.setSelectBySavedData( this.categoriesSelect, parsedData.categories);
                }

                if ( parsedData.tags ) {
                    AdminSearchSetting.setSelectBySavedData(this.tagsSelect, parsedData.tags);
                }

                let categoriesDropdownSelector = this.categoriesSelect;
                let tagsDropdownSelector       = this.tagsSelect;
                
                this.modalForm.off( "submit" ).on( "submit", function ( e ) {

                    e.preventDefault();

                    let categories = AdminSearchSetting.collectTermsByTaxonomy( categoriesDropdownSelector );
                    let tags       = AdminSearchSetting.collectTermsByTaxonomy( tagsDropdownSelector );

                    let formData = {
                        action: "smarseco_smart_search_control_setting_edit",
                        nonce: SMARSECO_SETTING.nonce_edit,
                        id: entry.id,
                        place_holder: AdminSearchSetting.modelPlaceHolder.val(),
                        class: AdminSearchSetting.modelClass.val(),
                        css_id:  AdminSearchSetting.modelID.val(),
                        post_type: $( 'input[name="post_type[]"]:checked' ).map( function () {
                            return $( this ).val();
                        } ).get(),
                        categories: categories,
                        tags: tags
                    };

                    let $submitBtn = $( '#submit-btn' );
                    let $loader = $( '#edit-loader' );
                    let $btnText = $submitBtn.find( '.btn-text' );
                
                    $btnText.hide().change();
                    $loader.show().change();
                    $submitBtn.prop("disabled", true);

                    $.ajax( {
                        type: "POST",
                        url: SMARSECO_SETTING.ajaxurl,
                        data: formData,
                        dataType: "json",
                        success: function ( response ) {
                            $loader.hide().change();
                            $btnText.show().change();
                            AdminSearchSetting.modal.css( "display", "none" ).change();
                            localStorage.setItem( "admin_notice", JSON.stringify( response.data ) );
                            location.reload();
                            $submitBtn.prop("disabled", false);

                        },
                        error: function () {
                            $loader.hide().change();
                            $btnText.show().change();
                            AdminSearchSetting.modal.css( "display", "none" ).chnge();
                            localStorage.setItem( "admin_notice", JSON.stringify( response.data ) );
                            location.reload();
                            $submitBtn.prop("disabled", false);

                        }
                    });
                });

                this.openModal();
            },

            /**
             * Set select by save Data
             */
            setSelectBySavedData: function( selectSelector, savedData ) {

                const $select = $( selectSelector );
    
                // Reset all options
                $select.find('option').prop('selected', false);

                for ( let taxonomy in savedData ) {
                    if ( ! savedData.hasOwnProperty(taxonomy) ) continue;

                    savedData[taxonomy].forEach( termId => {
                        $select
                            .find('optgroup[data-taxonomy="' + taxonomy + '"] option[value="' + termId.toString() + '"]')
                            .prop('selected', true);
                    });
                }

                // Trigger change for any plugin
                $select.trigger('change');
            },

            /**
             * Collect terms by texonomy
             */
            collectTermsByTaxonomy: function( selectSelector ) {

                let result = {};

                $( selectSelector ).find( 'option:selected' ).each( function () {
                    let termId   = $( this ).val();
                    let taxonomy = $( this ).closest( 'optgroup' ).data( 'taxonomy' );

                    if ( ! taxonomy || ! termId ) {
                        return;
                    }

                    if ( ! result[ taxonomy ] ) {
                        result[ taxonomy ] = [];
                    }

                    result[ taxonomy ].push( parseInt( termId, 10 ) );
                });

                return result;
            },

            /**
             * Delete the specific setting
             */
            deleteSetting: function ( event ) {

                event.preventDefault();

                let $btn = $( event.currentTarget );
                let id = $btn.data( 'id' );

                if ( confirm( SMARSECO_SETTING.confirm_msg ) ) {

                    let $row = $btn.closest( 'tr' );
                    let $loader = $row.find( '#delete-loader' );
                    let $btnText = $btn.find( '#delete-row' );

                    $btnText.hide().change();
                    $loader.show().change();

                    $.ajax( {

                        type: 'POST',
                        url: SMARSECO_SETTING.ajaxurl,
                        data: {
                            action: 'smarseco_smart_search_control_setting_delete',
                            nonce: SMARSECO_SETTING.nonce_delete,
                            id: id
                        },
                        success: function ( response ) {
                            $loader.hide().change();
                            $btnText.show().change();
                            localStorage.setItem( "admin_notice", JSON.stringify( response.data ) );
                            location.reload();
                        
                        },
                        error: function () {
                            $loader.hide().change();
                            $btnText.show().change();
                            localStorage.setItem( "admin_notice", JSON.stringify( response.data ) );
                            location.reload();
                        }
                    });
                }
            },

            /**
             * Open the modal form
             */
            openModal: function () {
                this.modal.css( "display", "flex" ).change();
            },

            /**
             * Close the modal form
             */
            closeModalHandler: function ( event ) {

                event.preventDefault();
                this.modal.css( "display", "none" ).change();
                this.shortcode.css( "display", "none" ).change();
                this.modalForm.trigger( "reset" ).change();
            },

            /**
             * Create Database table on click
             */
            createDatabaseTable: function(){

                let $loader = $( '#database-loader' );
                $loader.show().change();

                $.ajax( {
                    url: SMARSECO_SETTING.ajaxurl,
                    type: "POST",
                    data: {
                        action: "smarseco_create_database_table",
                        nonce: SMARSECO_SETTING.nonce_table,
                    },
                    success: function ( response ) {
                        $loader.hide().change();
                        localStorage.setItem( "admin_notice", JSON.stringify( response.data ) );
                        location.reload();
                    
                    },
                    error: function () {
                        $loader.hide().change();
                        localStorage.setItem( "admin_notice", JSON.stringify( response.data ) );
                        location.reload();
                    }
                });
            },

            /**
             * Show Advance Setting in model
             */
            showAdvanceSetting: function () {

                $( "#advance-content" ).slideToggle( 300 ).change();
                this.advanceSetting.toggleClass( "active" );
            },

            /**
             * Copy short code
             */
            copyShortCode: function() {
                
                let text = $( "#shortcode-text" ).text().trim();

                navigator.clipboard.writeText( text ).then( function() {

                    let icon = $( "#copy-code .dashicons" );
                    let container = $( "#copy-code" );

                    icon.removeClass( "dashicons-clipboard" ).addClass( "dashicons-yes" );
                    $( '.copy-msg' ).css("display", "inline-block");
                    container.addClass( "copied" );

                    setTimeout( function() {
                        icon.removeClass( "dashicons-yes" ).addClass( "dashicons-clipboard" );
                        container.removeClass( "copied" );
                        $( '.copy-msg' ).css( "display", "none" );
                    }, 5000 );
                });
            },

            /**
             * When check Select all then select all option
             */
            selectAllPost: function() {

                $(".custom-checkbox").prop("checked", $(this.selectAll).is(":checked")).trigger("change");
            },

            /**
             * when check all option then check the select All checkbox
             */
            allsingleoptions: function() {

                if ( $( ".custom-checkbox:checked" ).length === $( ".custom-checkbox" ).length ) {
                    $( "#select-all" ).prop( "checked", true );
                } else {
                    $( "#select-all" ).prop( "checked", false );
                }
            },

            /**
             * Show the admin Notice
             */
            adminNotice: function() {

                let adminNotice = localStorage.getItem( "admin_notice" );

                if ( adminNotice ) {
                    let notice = JSON.parse( adminNotice );
            
                    let noticeHtml = `
                        <div class="notice notice-${ notice.type } is-dismissible">
                            <p>${ notice.message }</p>
                            <button type="button" class="notice-dismiss">
                                <span class="screen-reader-text">Dismiss this notice.</span>
                            </button>
                        </div>
                    `;

                    $( ".wrap" ).prepend(noticeHtml);
            
                    localStorage.removeItem( "admin_notice" );

                    $( document ).on( "click", ".notice-dismiss", function () {
                        $( this ).closest( ".notice" ).fadeOut().change();
                    });
                }
            }

        };

        AdminSearchSetting.init();
    });
})( jQuery );