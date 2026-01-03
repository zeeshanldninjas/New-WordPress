(function( $ ) { 'use strict';

    $( document ).ready( function() {

        var FGR_Admin = {

            init: function() {

                this.fgrCourseGroupHideOption();
                this.toggleHideOption();
            },  

            /**
             * Toggle hide post option
             */
            toggleHideOption: function() {

                $( '.fgr-hide-post-checkbox' ).click( function() {
                    $( '.fgr-redirect-wrap, .fgr-exclude-wrap' ).slideToggle();
                } );
            },

            /**
             * Make tab is active
             */
            fgrCourseGroupHideOption: function() {
                
                $( '.fgr-course-groups-checkbox' ).click(function() {

                    let self = $( this );
                    let group_id = self.data( 'group_id' );
                    let hiddenvalue = $( '.fgr-course-groups-checkbox-'+group_id ).val();
                    if( 0 == hiddenvalue.charAt(0) || 1 == hiddenvalue.charAt(0) ) {
                        hiddenvalue = hiddenvalue.substring(1);
                    }

                    if( self.prop("checked") == true){
                        var prefix = 1;
                    }
                    else if( self.prop("checked") == false){
                        var prefix = 0;
                    }

                    $( '.fgr-course-groups-checkbox-'+group_id ).val( prefix+hiddenvalue );
                } );

                $( '.fgr-course-group' ).click( function() {

                    $( '.fgr-course-groups' ).slideToggle();
                } );
            },
        };

        FGR_Admin.init();
    });
})( jQuery );