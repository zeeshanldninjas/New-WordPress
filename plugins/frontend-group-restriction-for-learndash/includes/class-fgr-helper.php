<?php

/**
 * Admin template for group restirction
 */
if( ! defined( 'ABSPATH' ) ) exit;

/**
 * Class FGR_Helper
 */
class FGR_Helper {
    
    /**
     * create a function to get user capability  
     */
    public static function fgr_get_user_capability( $user_id ) {

        $user = get_userdata( $user_id );
        if ( ! $user || empty( $user->roles ) ) {
            return false;
        }

        return in_array( 'administrator', $user->roles, true )
            || in_array( 'group_leader', $user->roles, true );
    }

    /**
     * create a function to get admin/groupleader option for restriction
     */
    public static function fgr_get_admin_groupleader_resriction_option() {

        $get_admin_settings = get_option( 'fgr-restriction' );
        $get_admin_rest_opt = isset( $get_admin_settings['is-admin-groupleader'] ) ? $get_admin_settings['is-admin-groupleader'] : '';

        $return = false;

        if( 'on' == $get_admin_rest_opt ) {
            $return = true;
        }
        return $return;
    }
    
    /**
     * create a function to get restricted course id if all group courses option is enabled
     * 
     * @param $restricted_course_id 
     */
    public static function fgr_get_restricted_course_ids_based_on_group_courses( $restricted_course_id ) {

        $course_ids = [];

        if( $restricted_course_id && is_array( $restricted_course_id ) ) {

            foreach( $restricted_course_id as $c_id ) {

                $cid = intval( $c_id );
                $get_post_type = get_post_type( $cid );
                $p_status = get_post_status( $cid );

                if( 'sfwd-courses' != $get_post_type || 'publish' != $p_status ) {
                    continue;
                }
                $course_ids[] = $cid;
            }
        }

        $restricted_course_id = $course_ids;
        return $restricted_course_id;
    }

    /**
     * create a function to get restricted post ids if post type is sfwd-courses
     */
    public static function fgr_get_restricted_course_ids() {

        global $wpdb;

        $post_ids = $wpdb->get_col(
            $wpdb->prepare(
                "
                SELECT post_id
                FROM {$wpdb->prefix}fgr_group_permissions
                WHERE post_type = %s
                AND restriction = %s
                ",
                'sfwd-courses',
                'true'
            )
        );

        return $post_ids;
    }

    /**
     * Create a function to check user in group or not
     * 
     * @param $user_id
     * @param $group_id
     */
    public static function fgr_check_user_in_group( $user_id, $group_id ) {

        global $wpdb;

        if( ! $user_id || ! $group_id ) {
            return false;
        }

        $table_name = $wpdb->prefix.'usermeta';
        $user_groups = $wpdb->get_results( "SELECT meta_value FROM $table_name WHERE meta_key LIKE '%learndash_group_users_%' AND user_id = $user_id", ARRAY_A );

        if( ! $user_groups ) {
            return false;
        }

        $user_groups = array_column( $user_groups, 'meta_value' );
        return in_array( $group_id, $user_groups );        
    }

    /**
     * check database key is restrict or fgr_restrict
     * 
     */
    public static function fgr_check_restriction_key( $post_id, $course_id, $group_id ) {

        $key = 'restrict_' . $group_id.'_'. $course_id;
        $is_meta_key_exits = metadata_exists( 'post', $post_id, $key );

        if( ! $is_meta_key_exits ) {
            $key = 'fgr_restrict_' . $group_id.'_'. $course_id;   
        }
        return $key;
    }
    /**
     * Check if parent post id disabled
     *
     * @param $post_id
     * @param $group_id
     * @param $post_type
     * @return bool|mixed
     */
    public static function fgr_parent_restriction( $post_id, $group_id, $post_type ) {

        $is_group_courses_enabled = get_option( 'fgr-restriction' );
        $is_group_courses_enabled = isset( $is_group_courses_enabled['fgr-group-courses-option'] ) ? $is_group_courses_enabled['fgr-group-courses-option'] : '';

        if( 'sfwd-lessons' == $post_type ) {
            
            $course_id = learndash_get_course_id( $post_id );
            $key = FGR_Helper::fgr_check_restriction_key( $post_id, $course_id, $group_id );
            $post_restriction = get_post_meta( $course_id, $key, true );
              
            if( ! empty( $post_restriction ) ) {
                   
                if( 'false' != $post_restriction ) {
                    return true;
                }
                    
                return false;
            }

            if( empty( $post_restriction ) ) {

                if( 'on' == $is_group_courses_enabled ) {
                    return true;
                }

                return false;
            }

        } else if( 'sfwd-topic' == $post_type ) {

            $lesson_id = learndash_get_lesson_id( $post_id );
            $course_id = learndash_get_course_id( $post_id );

            if( $lesson_id ) {

                $key = FGR_Helper::fgr_check_restriction_key( $lesson_id, $course_id, $group_id );
                $return = get_post_meta( $lesson_id, $key, true );

                if( ! empty( $return ) ) {
                    $return = 'false' == $return ? false : true;    
                }              
                
                if( empty( $return ) ) {

                    $key = FGR_Helper::fgr_check_restriction_key( $course_id, $course_id, $group_id );
                    $return = get_post_meta( $course_id, $key, true );
                    if( ! empty( $return ) ) {
                        $return = 'false' == $return ? false : true;    
                    }

                    if( empty( $return ) && 'on' == $is_group_courses_enabled ) {
                        $return = true;
                    }
                }

                return $return;
            }

            return false;

        } else if( 'sfwd-quiz' == $post_type ) {

            $lesson_id = learndash_get_lesson_id( $post_id );
            $course_id = learndash_get_course_id( $post_id );
            if( $lesson_id ) {

                $key = FGR_Helper::fgr_check_restriction_key( $lesson_id, $course_id, $group_id );
                $return = get_post_meta( $lesson_id, $key, true );
                
                if( empty( $return ) || 'false' == $return ) {

                    $post_type = get_post_type( $lesson_id );

                    if( 'sfwd-topic' == $post_type || 'sfwd-lessons' == $post_type ) {

                        $lesson_id_c = (int) learndash_get_lesson_id( $lesson_id );
                        $key = FGR_Helper::fgr_check_restriction_key( $lesson_id_c, $course_id, $group_id );
                        $return = get_post_meta( $lesson_id_c, $key, true );

                        if( 'false' == $return || empty( $return ) ) {
                            $key = FGR_Helper::fgr_check_restriction_key( $course_id, $course_id, $group_id );
                            $return = get_post_meta( $course_id, $key, true );
                        }
                    } else {
                        if( empty( $return ) || 'false' == $return ) {
                            $key = FGR_Helper::fgr_check_restriction_key( $course_id, $course_id, $group_id );
                            $return = get_post_meta( $course_id, $key, true );
                        }
                    }
                }
            } else {
                $key = FGR_Helper::fgr_check_restriction_key( $course_id, $course_id, $group_id );
                $return = get_post_meta( $course_id, $key, true );
            }

            if( ( 'false' == $return || empty( $return ) ) && 'on' == $is_group_courses_enabled ) {

                $return = true;
            }else if( ( 'false' == $return || empty( $return ) )  && 'off' == $is_group_courses_enabled ) {
               
                $return = false;
            }

            return $return;
        }
    }

    /**
     * Checked whether post is restricted
     *
     * @param $course_id
     * @param $user_id
     * @param $post_id
     * @return bool
     */
    public static function fgr_is_restricted( $course_id, $user_id, $post_id, $post_type ) {

        $group_ids = learndash_get_course_groups( $course_id, false );
        $is_group_courses_enabled = get_option( 'fgr-restriction' );
        $is_group_courses_enabled = isset( $is_group_courses_enabled['fgr-group-courses-option'] ) ? $is_group_courses_enabled['fgr-group-courses-option'] : '';
        if( $group_ids ) {
            foreach( $group_ids as $group_id ) {

                if( ! learndash_is_user_in_group( $user_id, $group_id ) ) {
                    continue;
                }

                $key = 'restrict_' . $group_id.'_'. $course_id;
                
                $is_meta_key_exits = metadata_exists( 'post', $post_id, $key );

                if( ! $is_meta_key_exits ) {
                    $key = 'fgr_restrict_' . $group_id.'_'. $course_id;    
                }

                $post_restriction = get_post_meta( $post_id, $key, true );

                $is_post_restriction_enabled = get_post_meta( $post_id, 'fgr_post_restriction' , true );
 
                if( empty( $post_restriction ) || 'false' == $post_restriction ) {
    
                    if( $post_type != 'sfwd-courses' ) {

                        if( 'off' == $is_post_restriction_enabled || empty( $is_post_restriction_enabled ) ) {

                            return FGR_Helper::fgr_parent_restriction( $post_id, $group_id, $post_type );
                        } elseif( 'on' == $is_post_restriction_enabled ) {
                            return true;
                        }
                    }

                    if( ( 'on' == $is_group_courses_enabled && 'false' != $post_restriction ) || ( 'on' == $is_post_restriction_enabled && 'false' != $post_restriction ) ) {

                        return true;
                    }

                    return false;
                } else {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check Restrictions
     *
     * @param $user_id
     * @param $post_id
     * @return bool
     */
    public static function fgr_display_restriction( $user_id, $post_id ) {

        $post_type = get_post_type( $post_id );
        if( 'sfwd-courses' == $post_type ) {
            $return = FGR_Helper::fgr_is_restricted( $post_id, $user_id, $post_id, 'sfwd-courses' );
        } else if( 'sfwd-lessons' == $post_type ) {

            $course_id = learndash_get_course_id( $post_id );
            $return = FGR_Helper::fgr_is_restricted( $course_id, $user_id, $post_id, 'sfwd-lessons' );
        } else if( 'sfwd-topic' == $post_type ) {

            $course_id = learndash_get_course_id( $post_id );
            $return = FGR_Helper::fgr_is_restricted( $course_id, $user_id, $post_id, 'sfwd-topic' );
        } else if( 'sfwd-quiz' == $post_type ) {

            $course_id = learndash_get_course_id( $post_id );
            $return = FGR_Helper::fgr_is_restricted( $course_id, $user_id, $post_id, 'sfwd-quiz' );
        }

        return $return;
    }

    /**
     * Display Line Items
     *
     * @param $post_id
     * @param $name
     * @param $target
     */
    public static function fgr_display_line_item( $group_id, $target, $target2, $target3, $course_id ) {
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'fgr_group_permissions';

        $post_type = get_post_type( $target );
        $is_current_checked = false;
        $restriction = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT restriction FROM $table_name WHERE group_id = %d AND post_id = %d AND post_type = %s",
                $group_id, $target, $post_type
            )
        );

        if( $restriction === 'true' ) {
            $is_current_checked = true;
        } 

        switch( $post_type ) {

            case 'sfwd-courses': $class = 'fgr-course-title'; 
            break;
            case 'sfwd-lessons': $class = 'fgr-lesson-titles';
            break;
            case 'sfwd-quiz': $class = 'fgr-course-quiz-title';
            break;
            case 'sfwd-topic': $class = 'fgr-topic-titles'; 
            break;
            default: $class = '';
        }
        ?>
        <div class="switch-wrapper">
            <label class="switch">
                <input type="checkbox" class="fgr-restriction-checkbox"
                       data-course_id="<?php echo intval( $course_id ); ?>"
                       data-group_id="<?php echo intval( $group_id ); ?>"
                       id="fgr_<?php echo intval( $target ); ?>"
                       data-post_type="<?php echo esc_attr( $post_type ); ?>"
                       data-post_name = "<?php echo get_the_title( (int)$target ); ?>"
                       <?php checked( $is_current_checked , true , true ); ?>
                >
                <span class="slider round"></span>
            </label>
        </div>
        <div class="suject-titles">
            <h5 class="fgr-titles <?php echo sanitize_html_class( $class ); ?>">
                <?php
                $course_permalink = get_permalink( $target );
                ?>
                <a href="<?php echo $course_permalink; ?>" class="fgr-course-permalink" target="_blank"><?php echo esc_html( get_the_title( $target ) ); ?></a>

                <?php if( !empty( $target2 ) && 'sfwd-courses' == $post_type || 'sfwd-lessons' == $post_type || 'sfwd-topic' == $post_type ) {

                    ?>
                    <span class="dashicons dashicons-arrow-down-alt2 <?php echo sanitize_html_class( $target3 ); ?>"
                    <?php echo $target2.'='.$target; ?>
                    ></span>
                    <?php
                }
                ?>
            </h5>
        </div>
        <div class="fgr-normalize"></div>
        <?php
    }

    /**
     * Get the group list in the frontend sidebar
     * @param mixed $group_id
     * @return void
     */
    public static function fgr_get_group_list( $group_id ) {
        ?>
            <li>
                <a href="" id="fgr-sidebar-group" data-group-id="<?php echo intval( $group_id ); ?>">
                    <?php 
                        echo esc_html( get_the_title( $group_id ) ); 
                    ?>
                </a>
            </li>
        <?php
    }

    /**
     * Getting group details
     * @param mixed $group_id
     * @param mixed $group_courses
     * @return bool|string
     */
    public static function fgr_get_group_details( $group_id, $group_courses ) {

        ob_start();
        if( $group_courses ) {
            $no = 0;
            foreach( $group_courses as $grp_course_ids ) {
                $no++;
                ?>
                <!-- course container -->
                <div class="fgr-course-container fgr-course-container-<?php echo intval( $group_id ); ?> fgr-course-wrapper-<?php echo intval( $grp_course_ids ); ?>">
                    <?php if ( 1 == $no ) { ?>     
                        <div class="fgr-courses-title fgr-post-label-<?php echo $group_id; ?>">
                            <?php
                            $course_title = LearnDash_Custom_Label::get_label( 'course' );
                                echo $course_title. __( 's', 'frontend-group-restriction-for-LearnDash' ); ?>
                        </div>
                    <?php 
                    }
                    ?>
                    <!-- course toggler html -->
                    <div class="fgr-course-contents">
                        <?php
                        FGR_Helper::fgr_display_line_item( $group_id, $grp_course_ids, 'data-course_id', 'course-lesson-open-icn', $grp_course_ids );
                        ?>
                    </div>
                    <!-- end course toggle html -->

                    <?php
                    $lesson_list = learndash_get_lesson_list( $grp_course_ids );
                    ?>
                    <!-- lesson container starts here -->
                    <div class="fgr-lesson-container fgr-lesson-container-<?php echo intval( $grp_course_ids ); ?>">
                        <?php
                        if ( $lesson_list ) {
                            $l_no = 0;
                            foreach( $lesson_list as $lessons ) {
                                $l_no++;
                                ?>
                                <!-- lesson toggler html starts here -->
                                <?php 
                                    if( 1 == $l_no  ) {
                                ?>
                                        <div class="fgr-lesson-title">
                                            <span class="dashicons dashicons-arrow-down"></span>
                                            <?php
                                            $lesson_title = LearnDash_Custom_Label::get_label( 'lesson' );
                                            echo $lesson_title. __( 's', 'frontend-group-restriction-for-LearnDash' ); 
                                            ?>
                                        </div>
                                <?php 
                                    }
                                ?>
                                <div class="fgr-lesson-contents fgr-lesson-container-<?php echo $lessons->ID; ?>">
                                    <?php
                                    FGR_Helper::fgr_display_line_item( $group_id, $lessons->ID, 'data-lesson_id', 'lesson-topic-open-icn', $grp_course_ids );
                                    ?>
                                </div>
                                <?php
                                    $topic_lists = learndash_get_topic_list( $lessons->ID, $grp_course_ids );
                                    if ( ! empty( $topic_lists ) && is_array( $topic_lists ) ) {

                                        $topic_lists = array_column( $topic_lists, 'ID' );
                                        $t_no = 0;
                                        foreach( $topic_lists as $topic_list ) {
                                            $t_no++;
                                            if( 1 == $t_no ) {
                                                ?>
                                                <div class="fgr-topic-title fgr-topic-container-<?php echo $lessons->ID; ?>">
                                                    <span class="dashicons dashicons-marker"></span>
                                                    <?php
                                                    $topic_title = LearnDash_Custom_Label::get_label( 'topic' );
                                                    echo $topic_title. __( 's', 'frontend-group-restriction-for-LearnDash' ); 
                                                    ?>
                                                </div>
                                                <?php
                                            }
                                            ?>
                                            <div class="fgr-lesson-topics fgr-topic-container-<?php echo $lessons->ID; ?>">
                                                <?php
                                                FGR_Helper::fgr_display_line_item( $group_id, $topic_list, 'data-topic_id', 'topic-quiz-open-icn', $grp_course_ids );
                                                ?>
                                            </div>
                                            <?php
                                            $topic_quiz_lists = learndash_get_lesson_quiz_list( $topic_list ,$grp_course_ids );

                                            if( ! empty( $topic_quiz_lists ) && is_array( $topic_quiz_lists ) ) {

                                                $topic_quizzess = array_column( $topic_quiz_lists, 'id' );
                                                $q_no = 0;
                                                foreach( $topic_quizzess as $topic_quiz ) {
                                                    $q_no++;
                                                    if ( 1 == $q_no ) {
                                                        ?>
                                                        <div class="fgr-topic-quiz-title fgr-quiz-container-<?php echo $topic_list; ?>">
                                                        <span class="dashicons dashicons-marker"></span>
                                                            <?php echo __( 'Topic Quizzes', 'frontend-group-restriction-for-LearnDash' ); ?>
                                                        </div>
                                                        <?php
                                                    }
                                                    ?>
                                                    <div class="fgr-topic-quiz fgr-quiz-container-<?php echo $topic_list; ?>">
                                                        <?php
                                                        FGR_Helper::fgr_display_line_item( $group_id, $topic_quiz, 'data-topic_quiz_id', '', $grp_course_ids );
                                                        ?>  
                                                    </div>
                                                    <?php
                                                }
                                            } else {
                                        
                                                ?>
                                                <div class="fgr-topic-quiz-title fgr-quiz-container-<?php echo $topic_list; ?> fgr-no-quiz">
                                                    <span class="dashicons dashicons-marker"></span>
                                                    <?php echo __( 'Topic Quizzes', 'frontend-group-restriction-for-LearnDash' ); ?>
                                                </div>
                                                <div class="fgr-topic-quiz fgr-quiz-container-<?php echo $topic_list; ?> fgr-no-quiz">
                                                    <?php
                                                    echo __( 'No quiz found', 'frontend-group-restriction-for-LearnDash' );
                                                    ?>  
                                                </div>
                                                <?php
                                            }
                                        }
                                    } else {
                                        ?>
                                        <div class="fgr-topic-title fgr-topic-container-<?php echo $lessons->ID; ?>">
                                            <span class="dashicons dashicons-marker"></span>
                                            <?php
                                            $topic_title = LearnDash_Custom_Label::get_label( 'topic' );
                                            echo $topic_title. __( 's', 'frontend-group-restriction-for-LearnDash' ); 
                                            ?>
                                        </div>
                                        <div class="fgr-lesson-topics fgr-topic-container-<?php echo $lessons->ID; ?>">
                                            <?php 
                                            echo __( 'No Topic found', 'frontend-group-restriction-for-LearnDash' );
                                            ?>
                                        </div>
                                        <?php
                                    }

                                    $fgr_lesson_quizzess = learndash_get_lesson_quiz_list(  $lessons->ID, $grp_course_ids );
                                    if( ! empty( $fgr_lesson_quizzess ) && is_array( $fgr_lesson_quizzess ) ) {

                                        $fgr_lesson_quizzess = array_column( $fgr_lesson_quizzess, 'id' );
                                        $q_no = 0;
                                        foreach( $fgr_lesson_quizzess as $fgr_lesson_quiz ) {
                                            $q_no++;
                                            if ( 1 == $q_no  ) {
                                                ?>
                                                <div class="fgr-lesson-quiz-title fgr-topic-container-<?php echo $lessons->ID; ?>">
                                                    <span class="dashicons dashicons-marker"></span>
                                                    <?php echo __( 'Quizzes', 'frontend-group-restriction-for-LearnDash' ); ?>
                                                </div>
                                                <?php 
                                                }
                                            ?>
                                            <div class="fgr-lesson-quizzess fgr-topic-container-<?php echo $lessons->ID; ?>">
                                                <?php      
                                                FGR_Helper::fgr_display_line_item( $group_id, $fgr_lesson_quiz, 'data-lesson_quiz_id', '', $grp_course_ids );
                                                ?>
                                            </div>
                                            <?php
                                        }
                                    } else {
                                        ?>
                                        <div class="fgr-lesson-quiz-title fgr-topic-container-<?php echo $lessons->ID; ?>">
                                            <span class="dashicons dashicons-marker"></span>
                                            <?php echo __( 'Quizzes', 'frontend-group-restriction-for-LearnDash' ); ?>
                                        </div>
                                        <div class="fgr-lesson-quizzess fgr-topic-container-<?php echo $lessons->ID; ?>">
                                            <?php      
                                            echo __( 'No quiz found', 'frontend-group-restriction-for-LearnDash' );
                                            ?>
                                        </div>  
                                        <?php
                                    }
                                ?>
                                <!-- end lesson toggler html -->
                                <?php
                            }
                        } else {
                            ?>
                            <div class="fgr-lesson-title">
                                <span class="dashicons dashicons-arrow-down-alt2"></span>
                                <?php echo __( 'Lessons', 'frontend-group-restriction-for-LearnDash' ); ?>
                            </div>
                            <div class="fgr-lesson-contents fgr-lesson-container-">

                                <?php
                                echo __( 'No lesson found', 'frontend-group-restriction-for-LearnDash' );
                                ?>
                            </div>
                            <?php
                        }
                        ?>
                    </div>
                    <!-- end lesson container -->
                    <?php 
                    $course_quiz_ids = learndash_get_course_quiz_list( $grp_course_ids );
                    if ( ! empty( $course_quiz_ids ) && is_array( $course_quiz_ids )  ) {

                        $course_quiz_ids = array_column( $course_quiz_ids, 'id' );
                        $c_no = 0;
                        foreach( $course_quiz_ids as $course_quiz_id  ) {
                            $c_no++;
                            if ( 1 == $c_no++ ) {
                            ?>
                            <div class="fgr-course-quiz fgr-lesson-container-<?php echo $grp_course_ids ?>">
                            <?php echo __( 'Course Quizzes', 'frontend-group-restriction-for-LearnDash' ); ?>
                            </div>
                            <?php } ?>
                            <div class="fgr-course-quiz-content fgr-lesson-container-<?php echo $grp_course_ids; ?>">
                            <?php
                            FGR_Helper::fgr_display_line_item( $group_id, $course_quiz_id, 'data-course_quiz_id', '', $grp_course_ids );
                            ?>
                            </div>
                            <?php
                        }
                    } else {
                        ?>
                        <div class="fgr-course-quiz fgr-lesson-container-<?php echo $grp_course_ids ?>">
                            <?php echo __( 'Course Quizzes', 'frontend-group-restriction-for-LearnDash' ); ?>
                        </div>
                        <div class="fgr-course-quiz-content fgr-lesson-container-<?php echo $grp_course_ids; ?>">
                            <?php
                            echo __( 'No quiz found', 'frontend-group-restriction-for-LearnDash' );
                            ?>
                        </div>
                        <?php
                    }
                    ?>
                </div>
                <!-- end course container -->
                <?php
            }
        } else{
            ?>
            <!-- course container -->
            <div class="fgr-course-container fgr-course-container-<?php echo intval( $group_id ); ?>">     
                <p class="no-course-found">
                    <?php echo __( 'No course is attached with this group', 'frontend-group-restriction-for-LearnDash' ); ?>
                </p>
            </div>
            <?php
        }
        return ob_get_clean();
    }

    /**
     * function to create group row
     * 
     * @param $group_id
     */    
    public static function fgr_get_group_row( $group_id ) {

        global $wpdb;
        $users = FGR_Helper::fgr_get_group_users( $group_id );
        $table_name = $wpdb->prefix.'fgr_group_permissions';
        $results = $wpdb->get_results( $wpdb->prepare( "SELECT type, user_ids FROM ".$table_name." WHERE group_id = %d", $group_id ) );

        $type = 'include';
        if ( count( $results ) > 0 ) {
            
            $type = $results[0]->type;
        }

        $user_ids = [];
        foreach( $results as $user ) {
            $user_ids[] = $user->user_ids;
        }

        $user_ids = isset( $user_ids[0] ) ? explode( ',', $user_ids[0] ) : [];

        ?>
        
        <div class="fgr-group-container fgr-group-container-<?php echo intval( $group_id ); ?>" id="fgr-group-container-<?php echo intval( $group_id ); ?>">
            <!-- group toggler html -->
            <div class="fgr-group-contents">
                <div class="fgr-titles fgr-group-titles">
                    <div class="fgr-group-heading">
                        <span class="dashicons dashicons-buddicons-groups"></span>
                        <h3>
                            <?php 
                            $group_heading = LearnDash_Custom_Label::get_label( 'group' );
                            echo esc_html( $group_heading ) . ': ' . esc_html( get_the_title( $group_id ) ); 
                            ?>
                        </h3>
                    </div>
                    <div class="fgr-group-icon">
                        <span class="dashicons dashicons-image-rotate fgr-dashicon-loading" style="display: none;"></span>
                        <input type="button" data-id="<?php echo $group_id;?>" name="fgr-btn-apply-permission" value="<?php _e( 'Save Changes', 'frontend-group-restriction-for-LearnDash' );?>" class="btn btn-primary button fgr-btn-apply-permission" />
                        <span class="dashicons dashicons-arrow-up-alt2 group-course-open-icn" data-group_id="<?php echo intval( $group_id ); ?>"></span>
                    </div>
                </div>
                <table class="fgr-group-permitted-users" width="100%">
                    <tbody>
                        <tr>
                            <td>
                                <?php _e( 'Permission', 'frontend-group-restriction-for-LearnDash' );?>
                            </td>
                            <td>
                                <select class="fgr-group-permitted-type-ddl" name="fgr-permission-ddl" style="width:100%;">
                                    <option value="include" <?php echo selected( $type, 'include', true );?>>
                                        <?php _e( 'Include', 'frontend-group-restriction-for-LearnDash' );?>
                                    </option>
                                    <option value="exclude" <?php echo selected( $type, 'exclude', true );?>>
                                        <?php _e( 'Exclude', 'frontend-group-restriction-for-LearnDash' );?>
                                    </option>
                                </select>
                            </td>
                            <td>
                                <select class="fgr-group-permitted-users-ddl" name="fgr-permission-ddl" style="width:100%;" multiple>
                                    <?php foreach( $users as $key => $user ) {

                                        $selected = '';
                                        if( in_array( $user , $user_ids ) ) {
                                            $selected = 'selected';
                                        }
                                        ?>
                                        <option value="<?php echo $user; ?>" <?php echo $selected; ?>><?php echo FGR_Helper::fgr_get_username_by_user_id( $user ); ?></option>
                                    <?php } ?>
                                </select>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <!-- end group toggler html -->
            <?php
            $group_courses = FGR_Helper::fgr_get_group_courses( $group_id );

            if ( $group_courses ) {
                $no = 0;
                foreach( $group_courses as $grp_course_ids ) {
                    $no++;
                    ?>
                    <!-- course container -->
                    <div class="fgr-course-container fgr-course-container-<?php echo intval( $group_id ); ?> fgr-course-wrapper-<?php echo intval( $grp_course_ids ); ?>">
                        <?php if( 1 == $no ) { ?>     
                        <div class="fgr-courses-title fgr-post-label-<?php echo $group_id; ?>">

                            <?php
                            $course_title = LearnDash_Custom_Label::get_label( 'course' );
                            echo $course_title. __( 's', 'frontend-group-restriction-for-LearnDash' ); ?>
                        </div>
                        <?php 
                        }
                        ?>
                        <!-- course toggler html -->
                        <div class="fgr-course-contents">
                            <?php
                            FGR_Helper::fgr_display_line_item( $group_id, $grp_course_ids, 'data-course_id', 'course-lesson-open-icn', $grp_course_ids );
                            ?>
                        </div>
                        <!-- end course toggle html -->

                        <?php
                        $lesson_list = learndash_get_lesson_list( $grp_course_ids );
                        ?>
                        <!-- lesson container -->
                    
                        <div class="fgr-lesson-container fgr-lesson-container-<?php echo intval( $grp_course_ids ); ?>">
                            <?php
                            if( $lesson_list ) {
                                $l_no = 0;
                                foreach( $lesson_list as $lessons ) {
                                    $l_no++;
                                    ?>
                                    <!-- lesson toggler html -->

                                    <?php 
                                    if( 1 == $l_no  ) {
                                        ?>
                                        <div class="fgr-lesson-title">
                                            <span class="dashicons dashicons-arrow-down"></span>
                                            <?php
                                            $lesson_title = LearnDash_Custom_Label::get_label( 'lesson' );
                                            echo $lesson_title. __( 's', 'frontend-group-restriction-for-LearnDash' ); 
                                            ?>
                                        </div>
                                    <?php 
                                    }
                                    ?>
                                    <div class="fgr-lesson-contents fgr-lesson-container-<?php echo $lessons->ID; ?>">

                                        <?php
                                        FGR_Helper::fgr_display_line_item( $group_id, $lessons->ID, 'data-lesson_id', 'lesson-topic-open-icn', $grp_course_ids );
                                        ?>
                                    </div>

                                    <?php
                                    $topic_lists = learndash_get_topic_list( $lessons->ID, $grp_course_ids );
                                    if( ! empty( $topic_lists ) && is_array( $topic_lists ) ) {

                                        $topic_lists = array_column( $topic_lists, 'ID' );
                                        $t_no = 0;
                                        foreach( $topic_lists as $topic_list ) {
                                            $t_no++;
                                            if( 1 == $t_no ) {
                                                ?>
                                                <div class="fgr-topic-title fgr-topic-container-<?php echo $lessons->ID; ?>">
                                                    <span class="dashicons dashicons-marker"></span>
                                                    <?php
                                                    $topic_title = LearnDash_Custom_Label::get_label( 'topic' );
                                                    echo $topic_title. __( 's', 'frontend-group-restriction-for-LearnDash' ); 
                                                    ?>
                                                </div>
                                                <?php
                                            }
                                            ?>
                                            <div class="fgr-lesson-topics fgr-topic-container-<?php echo $lessons->ID; ?>">
                                                <?php
                                                FGR_Helper::fgr_display_line_item( $group_id, $topic_list, 'data-topic_id', 'topic-quiz-open-icn', $grp_course_ids );
                                                ?>
                                            </div>
                                            <?php

                                            $topic_quiz_lists = learndash_get_lesson_quiz_list( $topic_list ,$grp_course_ids );

                                            if( ! empty( $topic_quiz_lists ) && is_array( $topic_quiz_lists ) ) {

                                                $topic_quizzess = array_column( $topic_quiz_lists, 'id' );
                                                $q_no = 0;
                                                foreach( $topic_quizzess as $topic_quiz ) {
                                                    $q_no++;
                                                    if( 1 == $q_no ) {
                                                        ?>
                                                        <div class="fgr-topic-quiz-title fgr-quiz-container-<?php echo $topic_list; ?>">
                                                        <span class="dashicons dashicons-marker"></span>
                                                            <?php echo __( 'Topic Quizzes', 'frontend-group-restriction-for-LearnDash' ); ?>
                                                        </div>
                                                        <?php
                                                    }
                                                    ?>
                                                    <div class="fgr-topic-quiz fgr-quiz-container-<?php echo $topic_list; ?>">
                                                        <?php
                                                        FGR_Helper::fgr_display_line_item( $group_id, $topic_quiz, 'data-topic_quiz_id', '', $grp_course_ids );
                                                        ?>  
                                                    </div>
                                                    <?php
                                                }
                                            } else {
                                        
                                                ?>
                                                <div class="fgr-topic-quiz-title fgr-quiz-container-<?php echo $topic_list; ?> fgr-no-quiz">
                                                    <span class="dashicons dashicons-marker"></span>
                                                    <?php echo __( 'Topic Quizzes', 'frontend-group-restriction-for-LearnDash' ); ?>
                                                </div>
                                                <div class="fgr-topic-quiz fgr-quiz-container-<?php echo $topic_list; ?> fgr-no-quiz">
                                                    <?php
                                                    echo __( 'No quiz found', 'frontend-group-restriction-for-LearnDash' );
                                                    ?>  
                                                </div>
                                                <?php
                                            }
                                        }
                                    } else {
                                        ?>

                                        <div class="fgr-topic-title fgr-topic-container-<?php echo $lessons->ID; ?>">
                                            <span class="dashicons dashicons-marker"></span>
                                            <?php
                                            $topic_title = LearnDash_Custom_Label::get_label( 'topic' );
                                            echo $topic_title. __( 's', 'frontend-group-restriction-for-LearnDash' ); 
                                            ?>
                                        </div>

                                        <div class="fgr-lesson-topics fgr-topic-container-<?php echo $lessons->ID; ?>">
                                            <?php 
                                            echo __( 'No Topic found', 'frontend-group-restriction-for-LearnDash' );
                                            ?>
                                        </div>
                                        <?php
                                    }

                                    $fgr_lesson_quizzess = learndash_get_lesson_quiz_list(  $lessons->ID, $grp_course_ids );
                                    if( ! empty( $fgr_lesson_quizzess ) && is_array( $fgr_lesson_quizzess ) ) {

                                        $fgr_lesson_quizzess = array_column( $fgr_lesson_quizzess, 'id' );
                                        $q_no = 0;
                                        foreach( $fgr_lesson_quizzess as $fgr_lesson_quiz ) {
                                            $q_no++;
                                            if( 1 == $q_no  ) {
                                                ?>
                                                <div class="fgr-lesson-quiz-title fgr-topic-container-<?php echo $lessons->ID; ?>">
                                                    <span class="dashicons dashicons-marker"></span>
                                                    <?php echo __( 'Quizzes', 'frontend-group-restriction-for-LearnDash' ); ?>
                                                </div>
                                                <?php 
                                            }
                                            ?>
                                            <div class="fgr-lesson-quizzess fgr-topic-container-<?php echo $lessons->ID; ?>">
                                            <?php      
                                            FGR_Helper::fgr_display_line_item( $group_id, $fgr_lesson_quiz, 'data-lesson_quiz_id', '', $grp_course_ids );
                                            ?>
                                            </div>
                                            <?php
                                        }
                                        
                                    } else {
                                        ?>
                                        <div class="fgr-lesson-quiz-title fgr-topic-container-<?php echo $lessons->ID; ?>">
                                            <span class="dashicons dashicons-marker"></span>
                                            <?php echo __( 'Quizzes', 'frontend-group-restriction-for-LearnDash' ); ?>
                                        </div>
                                        <div class="fgr-lesson-quizzess fgr-topic-container-<?php echo $lessons->ID; ?>">
                                            <?php      
                                            echo __( 'No quiz found', 'frontend-group-restriction-for-LearnDash' );
                                            ?>
                                        </div>  
                                        <?php
                                    }
                                    ?>
                                    <!-- end lesson toggler html -->
                                    <?php
                                }
                            } else {
                                ?>
                                <div class="fgr-lesson-title">
                                    <span class="dashicons dashicons-arrow-down-alt2"></span>
                                    <?php echo __( 'Lessons', 'frontend-group-restriction-for-LearnDash' ); ?>
                                </div>
                                <div class="fgr-lesson-contents fgr-lesson-container-">

                                    <?php
                                    echo __( 'No lesson found', 'frontend-group-restriction-for-LearnDash' );
                                    ?>
                                </div>
                                <?php
                            }
                            ?>
                        </div>
                        <!-- end lesson container -->
                    
                        <?php 
                        $course_quiz_ids = learndash_get_course_quiz_list( $grp_course_ids );

                        if( ! empty( $course_quiz_ids ) && is_array( $course_quiz_ids )  ) {

                            $course_quiz_ids = array_column( $course_quiz_ids, 'id' );
                            $c_no = 0;
                            foreach( $course_quiz_ids as $course_quiz_id  ) {
                                $c_no++;
                                if( 1 == $c_no++ ) {
                                ?>
                                <div class="fgr-course-quiz fgr-lesson-container-<?php echo $grp_course_ids ?>">
                                <?php echo __( 'Course Quizzes', 'frontend-group-restriction-for-LearnDash' ); ?>
                                </div>
                                <?php } ?>
                                <div class="fgr-course-quiz-content fgr-lesson-container-<?php echo $grp_course_ids; ?>">
                                <?php
                                FGR_Helper::fgr_display_line_item( $group_id, $course_quiz_id, 'data-course_quiz_id', '', $grp_course_ids );
                                ?>
                                </div>
                                <?php
                            }
                        } else {
                            ?>
                            <div class="fgr-course-quiz fgr-lesson-container-<?php echo $grp_course_ids ?>">
                                <?php echo __( 'Course Quizzes', 'frontend-group-restriction-for-LearnDash' ); ?>
                            </div>
                            <div class="fgr-course-quiz-content fgr-lesson-container-<?php echo $grp_course_ids; ?>">
                                <?php
                                echo __( 'No quiz found', 'frontend-group-restriction-for-LearnDash' );
                                ?>
                            </div>
                            <?php
                        }
                        ?>
                    </div>
                    <!-- end course container -->
                    <?php
                }
            }else{
                ?>
                <!-- course container -->
                <div class="fgr-course-container fgr-course-container-<?php echo intval( $group_id ); ?>">     
                    <p class="no-course-found">
                        <?php echo __( 'No course is attached with this group', 'frontend-group-restriction-for-LearnDash' ); ?>
                    </p>
                </div>
                <?php
            }
            ?>
        </div>
        <?php
    }

    /**
     * create a function to get message html
     */
    public static function fgr_get_restriction_message_html() {
        
        global $wpdb;

        $get_option_settings = get_option( 'fgr-restriction' );
        $current_page = isset( $_GET['page'] ) ? $_GET['page'] : '';    
        $group_courses_is_checked = false;
        $hide_restricted_course = false;
            
        $group_courses_is_checked = isset( $get_option_settings['fgr-group-courses-option'] ) && 'on' == $get_option_settings['fgr-group-courses-option'] ? true : false;

        $hide_restricted_course = isset( $get_option_settings['fgr-hide-restricted-course'] ) ? $get_option_settings['fgr-hide-restricted-course'] : '';

        $show = 'none';

        if( 'on' == $hide_restricted_course ) {
            $show = 'block';
        }

        if( 'on' == $hide_restricted_course ) {
            $hide_restricted_course = true;
        }

        $redirect_url = isset( $get_option_settings['fgr-redirect-url'] ) ? $get_option_settings['fgr-redirect-url'] : '';
        $exclude_url = isset( $get_option_settings['fgr-exclude-url'] ) ? $get_option_settings['fgr-exclude-url'] : '';

        $restriction_msg = '';
        if( ! empty ( $get_option_settings ) && is_array( $get_option_settings ) ) {
            
            $restriction_msg = array_key_exists( 'default_restriction_msg', $get_option_settings ) ? $get_option_settings['default_restriction_msg'] : '';
        }

        $archive_page_content = isset( $get_option_settings['fgr-archive-page-message'] ) ? $get_option_settings['fgr-archive-page-message'] : __( 'All the courses are hidden on this page.', 'frontend-group-restriction-for-LearnDash' );

        $course_is_res_admin = isset( $get_option_settings['is-admin-groupleader'] ) ? $get_option_settings['is-admin-groupleader'] : '';

        if( 'on' == $course_is_res_admin ) {
            $course_is_res_admin = true;
        }else {
            $course_is_res_admin = false;
        }

        ?>
        <div class="fgr-wrap">
            <form method="post" action="<?php echo admin_url( 'admin-post.php' ); ?>">

                <!-- Manage course section metabox -->
                <div class="fgr-content-wrap">

                    <div class="fgr-metabox-heading"><?php _e( 'Front End Group Leader Restriction ', 'frontend-group-restriction-for-LearnDash' ); ?></div>

                    <div class="fgr-tab-content">
                        
                        <div class="fgr-shortcode-title">
                          <?php echo __( 'Restriction Shortcode:', 'frontend-group-restriction-for-LearnDash' ); ?>  
                        </div>

                        <div class="fgr-shortcode">
                            <code>[frontend_group_restriction]</code>
                        </div>

                        <!-- Added option to select a page for shortcode -->

                        <div class="fgr-group-option-wrap fgr-shortcode-page-wrap">
                            <div class="fgr-group-option-title">
                                <?php echo __( 'Apply Shortcode:', 'frontend-group-restriction-for-LearnDash' ); ?>
                            </div>

                            <div class="fgr-group-option">

                                <?php 

                                $page_id = get_option( 'fgr-restriction' );
                                $id = '';
                                if( is_array( $page_id ) && ! empty( $page_id ) ) {
                                    $id = isset( $page_id['page_id'] ) ? $page_id['page_id'] : '';
                                }

                                $args = array(
                                  'numberposts' => -1,
                                  'post_type'   => 'page',
                                  'post_status' => 'publish',
                                  'fields'      => 'ids'
                              );

                                $pages = get_posts( $args );
                                ?>
                                <select name="fgr-shortcode-page" class="shortcode-page">
                                    <option value=""><?php echo __( 'Select a page', 'frontend-group-restriction-for-LearnDash' ); ?></option>
                                    <?php
                                    $selected_page = '';
                                    if( is_array( $pages ) && ! empty($pages ) ) {
                                        foreach( $pages as $page ) {
                                            
                                            if( selected( $id, $page, true ) ) {
                                                $selected_page = $id;    
                                            }
                                            ?>
                                            <option value="<?php echo $page; ?>" <?php selected( $id, $page, true ); ?>><?php echo get_the_title( $page ); ?></option>
                                            <?php
                                        }
                                    }
                                    ?>
                                </select>

                                <?php
                                if( ! empty( $selected_page ) ) { 

                                    $link = get_permalink( $selected_page );
                                    ?>
                                    <span class="dashicons dashicons-admin-links wpe-link-icon"></span>
                                    <span><a href="<?php echo $link; ?>" target="_blank"><?php echo __( 'View Page', 'frontend-group-restriction-for-LearnDash' ); ?></a></span>
                                    <?php
                                }
                                ?>
                                <div class="fgr-help-content">
                                    <p>
                                        <span class="dashicons dashicons-info-outline"></span>
                                        <i>
                                            <?php echo __( ' Use this dropdown to select page for shortcode.', 'frontend-group-restriction-for-LearnDash' ); ?>
                                        </i>
                                    </p>
                                </div>
                            </div>
                        </div>

                        <?php 
                        echo self::fgr_get_restriction_message_editor_html( $restriction_msg );
                        ?>

                        <div class="fgr-group-option-wrap">
                            <div class="fgr-group-option-title">
                                <?php echo __( 'Restrict All Group Courses:', 'frontend-group-restriction-for-LearnDash' );
                                ?>
                            </div>

                            <div class="fgr-group-option">
                                <label class="fgr-checkbox">
                                    <input type="checkbox" name="fgr-group-courses-option" <?php echo checked( $group_courses_is_checked, true, true ); ?>>
                                    <span class="fgr-checkmark round"></span>
                                </label>
                                <div class="fgr-help-content">
                                    <p>
                                        <span class="dashicons dashicons-info-outline"></span>
                                        <i>
                                            <?php echo __( ' Enable this option to restrict all the group courses.', 'frontend-group-restriction-for-LearnDash' ); ?>
                                        </i>
                                    </p>
                                </div>
                            </div>
                        </div>
                        <!-- Add html to remove course from frontend if course is restricted-->

                        <div class="fgr-group-option-wrap">
                            <div class="fgr-group-option-title">
                                <?php echo __( 'Hide Posts:', 'frontend-group-restriction-for-LearnDash' ); ?>
                            </div>

                            <div class="fgr-group-option">
                                <label class="fgr-checkbox">
                                    <input type="checkbox" name="fgr-course-remove-option" <?php echo checked( $hide_restricted_course, true, true ); ?>>
                                    <span class="fgr-checkmark round fgr-hide-post-checkbox"></span>
                                </label>
                            </div>
                        </div>

                        <!-- enter exclude url html -->

                        <div class="fgr-group-option-wrap fgr-exclude-wrap" style="display:<?php echo $show; ?>;">
                            <div class="fgr-group-option-title">
                                <?php echo __( 'Redirect URL:', 'frontend-group-restriction-for-LearnDash' ); ?>
                            </div>

                            <div class="fgr-group-option">
                                
                                <input type="text" name="fgr-redirect-url" class="redirect-url" placeholder="Enter the page URL here" value="<?php echo $redirect_url; ?>">
                                
                                <div class="fgr-help-content">
                                    <p>
                                        <span class="dashicons dashicons-info-outline"></span>
                                        <i>
                                            <?php echo __( ' If a user attempts to access the restricted course directly via URL, they will be redirected to this URL.', 'frontend-group-restriction-for-LearnDash' ); ?>
                                        </i>
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="fgr-group-option-wrap fgr-exclude-wrap" style="display:<?php echo $show; ?>;">
                            <div class="fgr-group-option-title">
                                <?php echo __( 'Exclude URLs:', 'frontend-group-restriction-for-LearnDash' ); ?>
                            </div>

                            <div class="fgr-group-option">

                                <textarea class="exclude-urls" name="fgr-excludes-urls"placeholder="Enter the exclude URLs"><?php echo $exclude_url; ?></textarea>
                                
                                <div class="fgr-help-content">
                                    <p>
                                        <span class="dashicons dashicons-info-outline"></span>
                                        <i>
                                            <?php echo __( ' This option ensures restricted courses remain visible on a page by providing its URL.', 'frontend-group-restriction-for-LearnDash' ); ?>
                                        </i>
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- -->

                        <div class="fgr-group-option-wrap">
                            <div class="fgr-group-option-title">
                                <?php echo __( 'Archive Page Message:', 'frontend-group-restriction-for-LearnDash' ); ?>
                            </div>

                            <div class="fgr-group-option">

                                <textarea class="exclude-urls" name="course-not-dis-cont"placeholder="Write message here"><?php echo $archive_page_content; ?></textarea>
                                
                                <div class="fgr-help-content">
                                    <p>
                                        <span class="dashicons dashicons-info-outline"></span>
                                        <i>
                                            <?php echo __( ' This option allows you to display a message on archive page if all courses are restricted.', 'frontend-group-restriction-for-LearnDash' ); ?>
                                        </i>
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- -->

                        <div class="fgr-group-option-wrap">
                            <div class="fgr-group-option-title">
                                <?php echo __( 'Exclude admin/group leader:', 'frontend-group-restriction-for-LearnDash' );
                                ?>
                            </div>

                            <div class="fgr-group-option">
                                <label class="fgr-checkbox">
                                    <input type="checkbox" name="fgr-admin-group-option" <?php echo checked( $course_is_res_admin, true, true ); ?>>
                                    <span class="fgr-checkmark round"></span>
                                </label>
                                <div class="fgr-help-content">
                                    <p>
                                        <span class="dashicons dashicons-info-outline"></span>
                                        <i>
                                            <?php echo __( " Enable this option, if you don't want to apply hide post feature for admin/group leader.", 'frontend-group-restriction-for-LearnDash' ); ?>
                                        </i>
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- -->

                        <!-- Added group course option  -->
                    </div>
                </div>
            <!-- End manage course section metabox -->

                <!-- Explanation of uses metabox -->
                <div class="fgr-explanation-metabox">
                    <div class="fgr-exp-heading"><?php _e( 'Explanation of Use', 'frontend-group-restriction-for-LearnDash' ); ?></div>                
                    <div class="fgr-exp-content">
                        <div><?php _e( 'Need help? Support? Questions? <br>Read the explanation of use.', 'frontend-group-restriction-for-LearnDash' ); ?></div>
                        <a class="fgr-exp-link" target="_blank" href="https://docs.ldninjas.com/docs/frontend-group-restriction-for-learndash/?_ga=2.184384416.763791991.1651136382-1247926630.1647587694&_gl=1%2A32atzh%2A_ga%2AMTI0NzkyNjYzMC4xNjQ3NTg3Njk0%2A_ga_FBQJWHY5HR%2AMTY1MTEzNjM4MS4xNC4xLjE2NTExMzYzOTYuMA.."><?php _e( 'Explanation of use', 'frontend-group-restriction-for-LearnDash' ); ?></a>
                        <?php
                        wp_nonce_field( 'fgr_restriction_nonce', 'fgr_restriction_nonce_field' );
                        $current_page = isset( $_GET['page'] ) ? $_GET['page'] : '';
                        ?>
                        <input type="hidden" class="fgr-active" name="current_page" value="<?php echo $current_page; ?>">    
                        <input type="submit" name="restriction_submit" value="<?php echo __( 'Save Setting', 'frontend-group-restriction-for-LearnDash' ); ?>" class="restriction-btn button-primary">
                        <input type="hidden" name="action" value="restriction_setting_page">
                    </div>
                </div>
                <!-- End explanation of uses metabox-->
            </form>
        </div>
        <?php
    }

    /**
     * create a function to get user name
     * 
     * @param $user_id
     */
    public static function fgr_get_user_name_by_id( $user_id ) {

        global $wpdb;

        $user_table = $wpdb->prefix.'users';
        $user_name = $wpdb->get_results( "SELECT user_login FROM $user_table
            WHERE ID = $user_id
            " );
        return ucwords( $user_name[0]->user_login );
    }

    /**
     * create a function to return checked or not
     */
    public static function fgr_get_restriction( $target, $group_id, $course_id, $post_type ) {

        $is_current_checked = false;
        $post_settings = get_post_meta( $target, 'restrict_' . $group_id.'_'. $course_id, true );
        if( !empty( $post_settings ) ) {

            if( 'false' != $post_settings && ! empty( $post_settings ) ) {

                $is_current_checked = true;
            }
        }

        if( ! $is_current_checked ) {

            $option_settings = get_option( 'fgr-restriction' );
            $is_current_checked = isset( $option_settings['fgr-group-courses-option'] ) && $option_settings['fgr-group-courses-option'] == 'on' ? true : false;
        }

        return $is_current_checked;
    }

    /**
     * Create a function to get id of course/lesson/topic/quiz
     * 
     * @param $user_id
     */
    public static function fgr_get_post_ids( $user_id ) {

        global $wpdb;

        $table_name = $wpdb->prefix.'usermeta';
        $table_name2 = $wpdb->prefix.'postmeta';

        $group_ids = $wpdb->get_results( $wpdb->prepare( "SELECT meta_value FROM $table_name WHERE meta_key LIKE '%learndash_group_users_%' AND user_id = $user_id " ) );

        if( empty( $group_ids ) || ! is_array( $group_ids ) ) {
            return false;
        }

        $group_ids = array_column( $group_ids, 'meta_value'  );

        $post_ids = [];

        foreach( $group_ids as $group_id ) {
            $meta_key_val = 'learndash_group_enrolled_'.$group_id;

            $course_ids = $wpdb->get_results( "SELECT post_id FROM $table_name2 WHERE meta_key = '".$meta_key_val."' " );

            $course_ids = array_column( $course_ids, 'post_id' );

            if( ! empty( $course_ids ) && is_array( $course_ids ) ) {
                foreach( $course_ids as $course_id ) {

                    if( ! in_array( $course_id, $post_ids ) ) {
                        $post_ids[] = $course_id;
                    }

                    $lesson_ids = $wpdb->get_results( "SELECT post_id FROM $table_name2 WHERE meta_key = 'course_id' AND meta_value = '".$course_id."' " );

                    $lesson_ids = array_column( $lesson_ids, 'post_id' );
                    if( ! empty( $lesson_ids ) && is_array( $lesson_ids ) ) {

                        foreach( $lesson_ids as $lesson_id ) {

                            if( ! in_array( $lesson_id, $post_ids ) ) {
                                $post_ids[] = $lesson_id;
                            }
                        }
                    }
                }
            }
        }
        return $post_ids;
    }

    /**
     * Create a function to get message editor & tags html
     */
    public static function fgr_get_restriction_message_editor_html( $restriction_msg ) {

        ob_start();
        ?>
        <div class="fgr-message-title">
          <?php echo __( 'Restriction Message:', 'frontend-group-restriction-for-LearnDash' ); ?>  
        </div>

        <div class="fgr-message">
            <?php 
            $editor_id = 'group-leader-restriction';
            wp_editor( $restriction_msg, $editor_id, [ 'textarea_rows' => 9 ] ); 
            ?>
        </div>

        <div class="fgr-tag-title">
            <?php echo __( 'Available Tags:', 'frontend-group-restriction-for-LearnDash' ); ?>  
        </div>

        <div class="fgr-tags">
            {user_name}, {post_name}, {instructor}, {group_name}<?php
                $post_type = get_post_type(); 
                
                if( 'sfwd-courses' != $post_type && 'sfwd-quiz' != $post_type ) {
                    ?>
                    , {course_name}
                    <?php

                    if( 'sfwd-lessons' != $post_type 
                        && 'sfwd-quiz' != $post_type
                        && ( isset( $_GET['page'] ) && 'frontend-group-restriction-for-learndash' != $_GET['page'] ) ) {
                        ?>
                        , {lesson_name}
                        <?php
                    }
                }
            ?>
        </div>
        <?php
        $content = ob_get_contents();
        ob_get_clean();
        return $content;
    }

    /**
     * create a function to get group courses
     */
    public static function fgr_get_group_courses( $group_id ) {

        global $wpdb;

        if( ! $group_id ) {
            return;
        }

        $meta_key = 'learndash_group_enrolled_'.$group_id;

        $post_ids = $wpdb->get_col( $wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s",
            $meta_key
        ) );

        return $post_ids;
    }

    /**
     * create a function to get group users
     */
    public static function fgr_get_group_users( $group_id ) {

        global $wpdb;

        if( ! $group_id ) {
            return;
        }

        $meta_key = 'learndash_group_users_'.$group_id;

        $user_ids = $wpdb->get_col( $wpdb->prepare(
            "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = %s",
            $meta_key
        ) );

        return $user_ids;
    }

    /**
     * create a function to get username by using user id
     * 
     * @param $user_id
     */
    public static function fgr_get_username_by_user_id( $user_id ) {
        global $wpdb;

        if ( empty( $user_id ) ) {
            return '';
        }

        return $wpdb->get_var(
            $wpdb->prepare(
                "SELECT display_name FROM {$wpdb->users} WHERE ID = %d",
                $user_id
            )
        );
    }

    /**
     * create a function to get user first and last name
     */
    public static function fgr_get_user_first_last_name( $user_id ) {

        global $wpdb;

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "
                SELECT meta_value
                FROM {$wpdb->usermeta}
                WHERE user_id = %d
                AND meta_key IN ('first_name', 'last_name')
                ",
                $user_id
            )
        );
        return $results;
    }

    /**
     * Check is course hidden for user
     */
    public static function is_course_hidden_for_user( $course_id, $user_id ) {

        $user_is_capable = self::fgr_get_user_capability( $user_id );
        if ( $user_is_capable && self::fgr_get_admin_groupleader_resriction_option() ) {
            return false;
        }

        $group_ids = learndash_get_course_groups( $course_id );
        if ( empty( $group_ids ) ) {
            return false;
        }

        foreach ( $group_ids as $group_id ) {

            if( !learndash_is_user_in_group( $user_id, $group_id ) ) {
                continue;
            } 

            $permissions = FGR_Shorcode::instance()->get_group_permission_data( $group_id, $course_id );
            if ( empty( $permissions ) ) {
                continue;
            }

            $restriction = null;
            $exclude_users = [];
            $include_users = [];

            foreach ( $permissions as $record ) {

                if ( (int) $record->post_id === (int) $course_id ) {
                    $restriction = $record->restriction;
                }

                if ( $record->type === 'exclude' ) {
                    $exclude_users = explode( ',', $record->user_ids );
                }

                if ( $record->type === 'include' ) {
                    $include_users = explode( ',', $record->user_ids );
                }
            }

            if ( 'true' === $restriction ) {

                if ( ! empty( $exclude_users ) && ! in_array( $user_id, $exclude_users ) ) {
                    return true;
                }

                if ( ! empty( $include_users ) && ! in_array( $user_id, $include_users ) ) {
                    return true;
                }
            }
        }

        return false;
    }

}   
