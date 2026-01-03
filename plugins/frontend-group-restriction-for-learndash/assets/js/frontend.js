(function( $ ) { 'use strict';
    
    $( document ).ready( function() {

        var FGR_Frontend = {

            init: function() {

                this.setDefaultActive();
                this.buttonActive();
                this.applySelect2();
                this.applyPermission();
                this.FGRTogglerElements();
                this.fetchGroupDetails();
                this.applyRestrictionOnChild();
            },            
            
            /**
             * apply restriction on child
             */
            applyRestrictionOnChild: function() {

                $( document ).on( 'click', '.fgr-restriction-checkbox', function() {

                    let self = $(this);
                    let postType = self.attr( 'data-post_type' );
                    
                    if( 'sfwd-courses' == postType ) {
 
                        let courseID = self.attr( 'data-course_id' );
                        
                        if (self.is(':checked')) {
                            $('.fgr-course-wrapper-' + courseID + ' input[type="checkbox"]').prop('checked', true);
                        } else {
                            $('.fgr-course-wrapper-' + courseID + ' input[type="checkbox"]').prop('checked', false);
                        }
                    }
                } );
            },

            /**
             * Sets the first button active on load
             */
            setDefaultActive: function() {

                let firstLink = $( '.fgr-sidebar-list ul li a' ).first();
                if ( firstLink.length ) {
                    firstLink.addClass( 'active' );
                }
            },

            /**
             * Sidebar button active on click
             */
            buttonActive: function() {
                $( '.fgr-sidebar-list ul li a' ).on( 'click', function( e ) {

                    e.preventDefault();
                    $( '.fgr-sidebar-list ul li a' ).removeClass( 'active' );
                    $( this ).addClass( 'active' );
                } );
            },

            /**
             * Applying Select2 for the Permission's input & multiple user selecting
             */
            applySelect2: function() {
                $( '.fgr-group-permitted-users-ddl' ).select2( { multiple:true, width: '100%', minimumInputLength: 1 } );
            },

            /**
             * Saving permission for the multiple users &
             * switch to restrict/un-restrict courses/lessons/topics/quizzes
             */
            applyPermission: function() {
                $( document ).off( 'click', '.fgr-btn-apply-permission' ).on( 'click', '.fgr-btn-apply-permission', function() {
                    
                    let self = $( this );
                    let dashicon = self.prev( '.fgr-dashicon-loading' );
                    let type = $( '.fgr-group-permitted-users td .fgr-group-permitted-type-ddl' ).val();
                    let users = $( '.fgr-group-permitted-users td .fgr-group-permitted-users-ddl' ).select2( 'val' );
                    let groupID = self.data( 'id' );

                    let restrictionData = [];
                    $( '.switch-wrapper input[type=checkbox]' ).each( function() {
                        let checkbox = $( this );
                        let postID = parseInt( checkbox.attr( 'id' ).replace( 'fgr_', '' ) );
                        let courseID = parseInt( checkbox.attr( 'data-course_id' ) );
                        let postType = checkbox.attr( 'data-post_type' );
                        let isRestricted = checkbox.is( ':checked' );
            
                        restrictionData.push( {
                            'post_id': postID,
                            'course_id': courseID,
                            'post_type': postType,
                            'is_restrict': isRestricted
                        } );
                    } );
            
                    if ( restrictionData.length === 0 ) return;
            
                    let data = {
                        'action': 'fgr_save_group_users_permissions',
                        'type': type,
                        'users': users,
                        'group_id': groupID,
                        'restrictions': restrictionData
                    };
            
                    dashicon.show().change();
            
                    jQuery.post(FGR.ajaxURL, data, function( response ) {
                        if ( response.success ) {
                            let message = response.data.message || "Success!";
                            FGR_Frontend.successMessageAlert( message );
                            restrictionData.forEach( item => {
                                let checkbox = $( `#fgr_${item.post_id}` );
                                if ( !item.is_restrict ) {
                                    checkbox.prop( 'checked', false );
                                }
                                checkbox.data( 'changed', false );
                            } );
                        }
                    } ).always( function() {
                        dashicon.hide().change();
                    } );
                });
            
                $( '.switch-wrapper input[type=checkbox]' ).change( function() {
                    let checkbox = $( this );
                    checkbox.data( 'changed', true );
                } );
            },                               

            /**
             * Logic to toggle element
             *
             * @param dataID
             * @param element
             * @param target
             * @param target2
             * @constructor
             */
            FGRToggle: function( dataID, element, target, target2 = '' ) {
                
                var dataIDValue = element.attr( dataID );

                if( element.hasClass( 'dashicons-arrow-down-alt2' ) ) {
                    element.removeClass( 'dashicons-arrow-down-alt2' ).addClass( 'dashicons-arrow-up-alt2' );
                } else {
                    element.removeClass( 'dashicons-arrow-up-alt2' ).addClass( 'dashicons-arrow-down-alt2' );
                }

                var parent = element.parents( '.fgr-group-container' );
                if( dataIDValue ) {
                    $( parent ).find( target + '-' + dataIDValue ).slideToggle();
                }

                if( target2 != '' ) {
                    $( parent ).find( target2 + '-' + dataIDValue ).slideToggle();
                }
            },

            /**
             * Create a function to append success/fetch alert html 
             * @param text
             */
            successMessageAlert: function ( text, message = "", status = "" ) {
        
                if ( $( ".fgr-alert-container" ).length === 0 ) {

                    $( "body" ).append( '<div class="fgr-alert-container"></div>' );
                }
                let html = "";
                if ( message === "fetch" ) {
                    
                    html = `
                        <div style="background: linear-gradient(133.63deg, rgb(87, 86, 86) -34.48%, rgb(222, 222, 222) 221.73%);" class="fgr-modal-alert">
                            <div class="fgr-content">
                                <span class="dashicons dashicons-image-rotate fgr-dashicon-fetch-loading"></span> 
                                <h6>${text}</h6>
                            </div>
                        </div>`;

                    $( ".fgr-alert-container" ).append( html );
                } else if ( status === "remove" ) {

                    $( ".fgr-modal-alert" ).fadeOut( "slow", function () {
                        $( this ).remove();
                    } );

                    return;
                } else {

                    html = `
                        <div style="background: linear-gradient(133.63deg, rgb(40, 176, 72) -34.48%, rgb(20, 96, 38) 221.73%);" class="fgr-modal-alert">
                            <div class="fgr-content">
                                <h6>${text}</h6>
                            </div>
                            <div class="fgr-bar"></div>
                        </div>`;
                    $( ".fgr-alert-container" ).append( html );
                }
        
                let modal = $( ".fgr-modal-alert" ).last();
                $( modal ).fadeIn( "ease", function () {
                    
                    if ( message !== "fetch" ) {

                        $( modal ).find( ".fgr-bar" ).addClass( "fgr-progress" );
                        setTimeout( () => {
                            $( modal ).fadeOut( "slow", function () {
                                $( this ).remove();
                            } );
                        }, 5000 );
                    }
                });
            },

            /**
             * Toggle For courses,lessons,topics
             * Calling in TogglerElement & fetchGroup details function
             */
            FGRChildTogglerElements: function() {
                /**
                 * Course Toggle
                */
                if ( $( '.course-lesson-open-icn' ).length > 0 ) {
                    $( document ).off( 'click', '.course-lesson-open-icn' ).on( 'click', '.course-lesson-open-icn', function( e ) {
                        
                        e.preventDefault();
                        var self = $( this );
                        FGR_Frontend.FGRToggle( 'data-course_id', self, '.fgr-lesson-container', '.fgr-quiz-container' );
                    });
                }

                /**
                 * Lesson Toggle
                 */
                if ( $( '.lesson-topic-open-icn' ).length > 0 ) {
                    $( document ).off( 'click', '.lesson-topic-open-icn' ).on( 'click', '.lesson-topic-open-icn', function( e ) {

                        e.preventDefault();
                        var self = $( this );
                        FGR_Frontend.FGRToggle( 'data-lesson_id', self, '.fgr-topic-container' );
                    });
                }

                /**
                 * topic Toggle
                 */
                if ( $( '.topic-quiz-open-icn' ).length > 0 ) {
                    $( document ).off( 'click', '.topic-quiz-open-icn' ).on( 'click', '.topic-quiz-open-icn', function( e ) {

                        e.preventDefault();
                        var self = $( this );
                        FGR_Frontend.FGRToggle( 'data-topic_id', self, '.fgr-quiz-container' );
                    });
                }
            },

            /**
             * Open/hide group/course/lessons/topics/quizzes
             */
            FGRTogglerElements: function() {

                /**
                 * Group Toggle
                 */
                if( $( '.group-course-open-icn' ).length > 0 ) {

                    $('.fgr-course-container').show().change();
                    $( document ).off( 'click', '.group-course-open-icn' ).on( 'click', '.group-course-open-icn', function( e ) {
                        e.preventDefault();
                        var self = $( this );
                        FGR_Frontend.FGRToggle( 'data-group_id', self, '.fgr-course-container' );
                    });
                }

                FGR_Frontend.FGRChildTogglerElements();
            },

            /**
             * Fetching Group details
             */
            fetchGroupDetails: function() {
                $( document ).on( "click", "#fgr-sidebar-group", function ( e ) {

                    e.preventDefault();
                    let groupId = $( this ).data( "group-id" );
            
                    if ( !groupId ) {
                        console.error( "Group ID is missing." );
                        return;
                    }
            
                    FGR_Frontend.successMessageAlert( "Fetching group details...", "fetch" );
                    
                    let requestData = {
                        action: "fgr_group_records",
                        group_id: groupId,
                        nonce: FGR.group_records_nonce,
                    };
            
                    $.ajax( {
                        url: FGR.ajaxURL,
                        method: "POST",
                        data: requestData,
                        success: function ( response ) {
                            if ( response.success ) {
                                
                                FGR_Frontend.successMessageAlert( "", "", "remove" );
                                $( ".fgr-course-container" ).remove();
                                let element = $( ".fgr-group-icon span" );
                                if ( !element.hasClass( 'dashicons-arrow-up-alt2' ) ) {
                                    element.removeClass( 'dashicons-arrow-down-alt2' ).addClass( 'dashicons-arrow-up-alt2' );
                                }
                                let groupTitle = response.data.group_title;
                                let groupId = response.data.group_id;
                                let type = response.data.type;
                                if( type ) {
                                    $('.fgr-group-permitted-type-ddl').val( type ).trigger('change');
                                }
                                let users = response.data.users;
                                
                                let user_ids = response.data.user_ids;
                                user_ids = typeof user_ids === 'string'
                                ? user_ids.split(',').map(id => id.trim())
                                : user_ids;
                                if( ! user_ids ) {
                                    user_ids = [];
                                }
                                let courses = response.data.courses;
                                $( ".fgr-group-container" ).attr( "id", `fgr-group-container-${groupId}` ).attr( "class", `fgr-group-container fgr-group-container-${groupId}` );
                                $( ".fgr-group-heading h3" ).html( groupTitle ).change();
                                $( ".fgr-group-icon span" ).data( "group_id", groupId ).attr( "data-group_id", groupId );
                                let usersDropdown = $( ".fgr-group-permitted-users-ddl" ).change();
                                usersDropdown.empty();
                                users.forEach( user => {
                                    let isSelected = user_ids.includes(String(user.id)) ? 'selected' : '';
                                    usersDropdown.append( `<option value="${user.id}" ${isSelected}>${user.name}</option>` );
                                } );
                                $( ".fgr-btn-apply-permission" ).data( "id", groupId ).attr( "data-id", groupId );
                                $( ".fgr-group-container" ).append( courses ).change();
                                $( ".fgr-course-container" ).show().change();
                                FGR_Frontend.applyPermission();
                                FGR_Frontend.FGRChildTogglerElements();
        
                            } else {
                                FGR_Frontend.successMessageAlert( "", "", "remove" );
                                console.error( response.data || "No records found." );
                                $( ".fgr-group-container" ).html( `<div class="error">No records found.</div>` );
                            }
                        },
                        error: function ( xhr, status, error ) {
                            FGR_Frontend.successMessageAlert( "", "", "remove" );
                            console.error( `Error: ${error}` );
                            $( ".fgr-group-container" ).html( `<div class="error">Failed to fetch group details. Please try again.</div>` );
                        },
                    });
                });
            },
        };

        FGR_Frontend.init();
    });
})( jQuery );