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
                this.initSelect2();
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
                this.categoriesSelect = $( "#categories" );
                this.tagsSelect       = $( "#tags" );
                this.modelBtn         = $( ".model-btn" );
                this.modalForm        = $( "#searchForm" );
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
                this.modelPostType.on( "change", this.loadCategoriesTags.bind( this ) );
            },

            /**
             * Add new setting and store it into database
             */
            addNewSetting: function () {

                this.modalForm.trigger( "reset" ).change();
                
                // Clear and disable categories/tags for new setting
                this.categoriesSelect.empty().prop('disabled', true).trigger('change');
                this.tagsSelect.empty().prop('disabled', true).trigger('change');
                this.modalForm.off( 'submit' ).on( 'submit', function ( e ) {
                    e.preventDefault();

                    let formData = {
                        action: 'smarseco_smart_search_control_setting',
                        nonce: SMARSECO_SETTING.nonce_add,
                        place_holder: AdminSearchSetting.modelPlaceHolder.val(),
                        class: AdminSearchSetting.modelClass.val(),
                        css_id:  AdminSearchSetting.modelID.val(),
                        post_type: $( 'input[name="post_type[]"]:checked' ).map( function () {
                            return $( this ).val();
                        } ).get(),
                        categories: AdminSearchSetting.categoriesSelect.val() || [],
                        tags: AdminSearchSetting.tagsSelect.val() || []
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

                // Load categories and tags for selected post types, then populate saved values
                this.loadCategoriesTags();
                
                // Set timeout to allow AJAX to complete before setting values
                setTimeout(() => {
                    if (parsedData.categories && Array.isArray(parsedData.categories)) {
                        this.categoriesSelect.val(parsedData.categories).trigger('change');
                    }
                    if (parsedData.tags && Array.isArray(parsedData.tags)) {
                        this.tagsSelect.val(parsedData.tags).trigger('change');
                    }
                }, 500);
                
                this.modalForm.off( "submit" ).on( "submit", function ( e ) {

                    e.preventDefault();
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
                        categories: AdminSearchSetting.categoriesSelect.val() || [],
                        tags: AdminSearchSetting.tagsSelect.val() || []
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
                
                $( ".custom-checkbox" ).prop( "checked", $( this.selectAll ).prop( "checked" ) );
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
            },

            /**
             * Initialize Select2 for categories and tags
             */
            initSelect2: function () {
                this.categoriesSelect.select2({
                    placeholder: 'Select categories...',
                    allowClear: true,
                    width: '100%'
                });

                this.tagsSelect.select2({
                    placeholder: 'Select tags...',
                    allowClear: true,
                    width: '100%'
                });
            },

            /**
             * Load categories and tags based on selected post types
             */
            loadCategoriesTags: function () {
                const selectedPostTypes = [];
                this.modelPostType.filter(':checked').each(function() {
                    selectedPostTypes.push($(this).val());
                });

                if (selectedPostTypes.length === 0) {
                    // Disable and clear categories/tags if no post types selected
                    this.categoriesSelect.prop('disabled', true).empty().trigger('change');
                    this.tagsSelect.prop('disabled', true).empty().trigger('change');
                    return;
                }

                // Show loading state
                this.categoriesSelect.prop('disabled', true);
                this.tagsSelect.prop('disabled', true);

                $.ajax({
                    url: SMARSECO_SETTING.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'smarseco_get_categories_tags',
                        nonce: SMARSECO_SETTING.nonce_categories_tags,
                        post_types: selectedPostTypes
                    },
                    success: (response) => {
                        if (response.success) {
                            // Populate categories
                            this.categoriesSelect.empty();
                            if (response.data.categories && Object.keys(response.data.categories).length > 0) {
                                $.each(response.data.categories, (key, category) => {
                                    this.categoriesSelect.append(new Option(
                                        `${category.name} (${category.taxonomy_label})`,
                                        key,
                                        false,
                                        false
                                    ));
                                });
                                this.categoriesSelect.prop('disabled', false);
                            }

                            // Populate tags
                            this.tagsSelect.empty();
                            if (response.data.tags && Object.keys(response.data.tags).length > 0) {
                                $.each(response.data.tags, (key, tag) => {
                                    this.tagsSelect.append(new Option(
                                        `${tag.name} (${tag.taxonomy_label})`,
                                        key,
                                        false,
                                        false
                                    ));
                                });
                                this.tagsSelect.prop('disabled', false);
                            }

                            // Trigger change to update Select2
                            this.categoriesSelect.trigger('change');
                            this.tagsSelect.trigger('change');
                        } else {
                            console.error('Failed to load categories and tags:', response.data.message);
                        }
                    },
                    error: (xhr, status, error) => {
                        console.error('AJAX error:', error);
                        // Re-enable selects even on error
                        this.categoriesSelect.prop('disabled', false);
                        this.tagsSelect.prop('disabled', false);
                    }
                });
            }

        };

        AdminSearchSetting.init();
    });
})( jQuery );
