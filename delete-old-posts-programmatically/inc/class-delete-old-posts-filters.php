<?php

namespace DEL\OLD\Posts\Cls;

/**
 * Make the plugin class.
 */
class Delete_Old_Posts_Filters extends Delete_Old_Posts {
    private $colors;

    private $bcolors;

    /**
     * Hooks init (nothing else) and calls things that need to run right away.
     */
    public function __construct() {
        add_action( 'admin_menu', [$this, 'deloldp_custom_menu_page'] );
        $this->colors = array(
            'decoration-sky-600 text-sky-600',
            'decoration-red-600 text-red-600',
            'decoration-green-600 text-green-600',
            'decoration-orange-600 text-orange-600',
            'decoration-indigo-600 text-indigo-600',
            'decoration-rose-600 text-rose-600',
            'decoration-purple-600 text-purple-600',
            'decoration-stone-600 text-stone-600',
            'decoration-yellow-600 text-yellow-600',
            'decoration-lime-600 text-lime-600'
        );
        $this->bcolors = array(
            'border-sky-200',
            'border-red-200',
            'border-green-200',
            'border-orange-200',
            'border-indigo-200',
            'border-rose-200',
            'border-purple-200',
            'border-stone-200',
            'border-yellow-200',
            'border-lime-200'
        );
    }

    /**
     * add a custom menu in admin menu
     */
    function deloldp_custom_menu_page() {
        // Add submenu page with same slug as parent to ensure no duplicates
        $deloldp_filters_submenu = add_submenu_page(
            'delete-old-posts',
            esc_html__( 'Filters - Delete old posts automatically' ),
            esc_html__( 'Filters', 'delete-old-posts' ),
            'manage_options',
            'delete-old-posts-filters',
            [$this, 'deloldpFilters']
        );
    }

    /**
     * Create filters page
     */
    function deloldpFilters() {
        /**
         * Handle the Filter's form data saving 
         */
        $this->filtersFormSave();
        ?>
        <section class="mx-4 my-8 delop" x-data="deloldp_Start()" x-init="onstart()">
            <div class="wrap mb-4">
                <h2><?php 
        esc_html_e( 'Available filters to use when deleting the posts', 'delete-old-posts' );
        ?></h2>
                <?php 
        // display info
        echo $this->deloldp_makeAlert( esc_html__( "Select which filters you would like to apply when deleting your posts from the options below.", "delete-old-posts" ), "info", "is-dismissible" );
        ?>
            </div>
            <div class="max-w-full bg-white border border-inherit p-8 mr-5">
                <form method="post" action="">
                    <div class="flex flex-row flex-wrap gap-5">
                        <?php 
        if ( current_user_can( 'delete_posts' ) & current_user_can( 'delete_others_posts' ) ) {
            esc_html_e( "\n                                Once you've decided on the number of days in the past that posts should automatically be deleted, you can refine the criteria for deletion even further. If you want to delete posts of only one type, posts in specific categories, or posts with one or more taxonomies (for custom post types), simply select the appropriate options below. Any posts published before the date you have chosen will then be filtered to make sure only those meeting your additional criteria are deleted. This way, you can be sure that only the posts you want removed are deleted.\n                                ", "delete-old-posts" );
            $this->delop_get_form_input( 'cpt' );
            $this->delop_get_form_input( 'cpt_type' );
            if ( current_user_can( 'manage_categories' ) ) {
                $this->delop_get_form_input( 'categories' );
                $this->delop_get_form_input( 'relation' );
            }
            if ( current_user_can( 'list_users' ) ) {
                $this->delop_get_form_input( 'users' );
            }
            $this->delop_get_form_input( 'postids' );
            $this->delop_get_form_input( 'search_keywords' );
            $this->delop_get_form_input( 'attached_img' );
        } else {
            esc_html_e( "You don't have rights to delete the posts.", "delete-old-posts" );
        }
        ?>
                        <div class="w-full flex items-center">
                            <div class="w-full text-right">
                                <button 
                                    type="submit" 
                                    class="bg-blue-500 rounded-full font-bold text-white px-4 py-3 transition duration-300 ease-in-out hover:bg-blue-600"
                                >
                                    <?php 
        esc_html_e( 'Save and test the deleted posts', 'delete-old-posts' );
        ?>
                                    <svg xmlns="http://www.w3.org/2000/svg" class="inline ml-2 w-6 stroke-current text-white stroke-2" viewBox="0 0 24 24" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                        <line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/>
                                    </svg>
                                </button>
                                <?php 
        wp_nonce_field( 'delop_filters_save', 'delop_nonce_filters' );
        ?>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </section>
        <?php 
    }

    /**
     * Handle the Filter's form change
     */
    function filtersFormSave() {
        // check if filters form was just saved
        if ( isset( $_POST['delop_nonce_filters'] ) && wp_verify_nonce( $_POST['delop_nonce_filters'], 'delop_filters_save' ) ) {
            // sanitize $_POST vars
            $filtersOptions = ( isset( $_POST ) ? (array) $_POST : array() );
            $filtersOptions = filter_var_array( $filtersOptions );
            // check if any post type selected (at least the default post type have to be selected)
            if ( !isset( $filtersOptions['cpt'] ) || isset( $filtersOptions['cpt'] ) && empty( $filtersOptions['cpt'] ) ) {
                // set the default post
                $filtersOptions['cpt'] = array('post');
                echo $this->deloldp_makeAlert( esc_html__( "At least one post type has to be selected. The default post type have been automatically selected.", "delete-old-posts" ), "warning", "is-dismissible" );
            }
            // save Filters option into an Option
            update_option( 'delop_filters', $filtersOptions );
            // show a list with deleted posts. Test the filter.
            $this->tryFilter();
        }
    }

    /**
     * Create form inputs
     */
    function delop_get_form_input( $inputName ) {
        global $dop_fs;
        switch ( $inputName ) {
            case 'cpt':
                ?>
                <div class="border border-inherit p-7 relative delop-filter">
                    <span class="dashicons dashicons-admin-post text-4xl mb-4"></span>
                    <?php 
                $helpTxt = sprintf( esc_html__( 'You can choose to delete only the posts from a specific custom post type, or all posts types. If not otherwise specified, only the posts from the %s will be deleted.', 'delete-old-posts' ), '<strong>' . esc_html__( "default post type", "delete-old-posts" ) . '</strong>' );
                $this->generateHelpTooltip( $helpTxt, 'w-full text-right absolute right-1 top-1', 'text-left right-1' );
                /**
                 * Get the list with custom post types
                 */
                // get saved options
                $toggle_hidden_cpt = $this->getFiltersOpt( 'toggle_hidden_cpt' );
                $args = array(
                    '_builtin' => false,
                );
                if ( !$toggle_hidden_cpt ) {
                    $args['public'] = true;
                }
                $getCustomPostTypes = get_post_types( $args );
                // get saved cpt if form was submited
                $cpts = $this->getFiltersOpt( 'cpt' );
                ?>
                    <div class='m-2 max-h-56 overflow-y-scroll'>
                        <?php 
                echo "<div class='text-base'>";
                esc_html_e( 'Choose the post type:', 'delete-old-posts' );
                echo "</div>";
                echo "\n                        <label class='block my-2'>\n                            <input type='checkbox' name='cpt[]' value='post'";
                if ( empty( $cpts ) || isset( $cpts ) && is_array( $cpts ) && array_search( 'post', $cpts ) !== false ) {
                    echo "checked";
                }
                echo "/>\n                            <span class='underline " . $this->colors[0] . " ml-1 decoration-2'>\n                                Post\n                            </span>\n                        </label>";
                $cptColor = 1;
                if ( is_array( $getCustomPostTypes ) ) {
                    foreach ( $getCustomPostTypes as $customPostType ) {
                        $checked = false;
                        if ( isset( $cpts ) && is_array( $cpts ) && array_search( $customPostType, $cpts ) !== false ) {
                            $checked = true;
                        }
                        echo "\n                            <label class='block my-2'>\n                                <input type='checkbox' name='cpt[]' value='" . $customPostType . "'";
                        if ( $checked ) {
                            echo "checked";
                        }
                        echo " />\n                                <span class='ml-1 ";
                        echo ( $checked && isset( $this->colors[$cptColor] ) ? $this->colors[$cptColor] . ' underline decoration-2' : '' );
                        echo "'>\n                                    " . ucfirst( str_replace( "_", " ", $customPostType ) ) . "\n                                </span>\n                            </label>";
                        $cptColor++;
                    }
                }
                echo "\n                    </div>";
                ?>
                    <!-- Rounded switch -->
                    <?php 
                // get saved options
                $toggle_hidden_cpt = $this->getFiltersOpt( 'toggle_hidden_cpt' );
                ?>
                    <label class="relative inline-flex items-center mb-5 cursor-pointer">
                        <input
                            type="checkbox"
                            id="toggle_hidden_cpt"
                            name="toggle_hidden_cpt"
                            value="1" 
                            <?php 
                if ( $toggle_hidden_cpt ) {
                    echo "checked";
                }
                ?> 
                            class="sr-only peer"
                        >
                        <div class="w-9 h-5 bg-gray-200 peer-focus:outline-none peer-focus:ring-0 peer-focus:ring-blue-300 dark:peer-focus:ring-blue-800 rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all dark:border-gray-600 peer-checked:bg-blue-600"></div>
                    </label>
                    <span class="ms-3 text-sm text-gray-900 dark:text-gray-300 ml-2 leading-6 max-w-[150px] inline-block"><?php 
                _e( 'Show hidden Custom Post Types.', 'delete-old-posts' );
                ?></span>
                </div>
                <?php 
                break;
            case 'cpt_type':
                $all_posts_statuses = get_post_stati();
                unset($all_posts_statuses['trash'], $all_posts_statuses['preview'], $all_posts_statuses['auto-draft']);
                ?>
                <div class="border border-inherit p-7 relative delop-filter">
                    <span class="dashicons dashicons-post-status text-4xl mb-4"></span>
                    <?php 
                $helpTxt = esc_html__( 'You can choose to delete only the posts by a specific status. The default status used is "publish".', 'delete-old-posts' );
                $this->generateHelpTooltip( $helpTxt, 'w-full text-right absolute right-1 top-1', 'text-left right-1' );
                // Generate the checkboxes
                echo "<div class='text-base'>";
                esc_html_e( 'Choose the post status:', 'delete-old-posts' );
                echo "</div>";
                if ( is_array( $all_posts_statuses ) ) {
                    foreach ( $all_posts_statuses as $key => $posts_status ) {
                        $this->delopt_generate_check_box( $key, ucfirst( $posts_status ) );
                    }
                }
                ?>
                </div>
                <?php 
                break;
            case 'categories':
                // get the list with checked post types
                $getCustomPostTypes = $this->getFiltersOpt( 'cpt' );
                /**
                 * Get all CPT
                 * Used to find the co;or index for the category box
                 */
                $args = array(
                    'public'   => true,
                    '_builtin' => false,
                );
                $getAllCustomPostTypes = get_post_types( $args );
                /**
                 * display a list of checkboxes for each taxonomy
                 * create the color for the taxonomy border to be the same as color used for the related custom post
                 */
                // get taxonomies for every checked post type
                if ( is_array( $getCustomPostTypes ) ) {
                    foreach ( $getCustomPostTypes as $key => $cpt ) {
                        $cptTaxonomies[$key] = get_object_taxonomies( (string) $cpt );
                    }
                }
                // Convert multidimensional array into single array
                $cptFlatTaxonomies = array_reduce( $cptTaxonomies, 'array_merge', array() );
                $removeFromFlatTaxonomies = array('post_format');
                // , 'post_tag'
                foreach ( $removeFromFlatTaxonomies as $removeFromFlatTaxonomiesVal ) {
                    if ( array_search( $removeFromFlatTaxonomiesVal, $cptFlatTaxonomies ) !== false ) {
                        $post_format_key = array_search( $removeFromFlatTaxonomiesVal, $cptFlatTaxonomies );
                        unset($cptFlatTaxonomies[$post_format_key]);
                    }
                }
                // list all categories and taxonomies
                if ( is_array( $cptFlatTaxonomies ) ) {
                    foreach ( $cptFlatTaxonomies as $category ) {
                        /**
                         * check if category is registered for the current cpt
                         * Get the CP array index
                         */
                        $cptIndex = $this->delop_find_parent( $cptTaxonomies, $category );
                        /**
                         * find border color index for the box
                         * need to be the same as the color of CPT in the list
                         */
                        $getCPTKeyInAllCPTArray = $this->countArrayUntilTarget( $getAllCustomPostTypes, $getCustomPostTypes[$cptIndex] );
                        // color index will be incremented with 1 (post is not in the CPT array so will need to be counted too)
                        $borderColorIndex = ( $category != 'category' ? $getCPTKeyInAllCPTArray + 1 : 0 );
                        ?>
                    <div class="border p-7 relative max-w-xs <?php 
                        echo ( isset( $this->bcolors[$borderColorIndex] ) ? $this->bcolors[$borderColorIndex] : '' );
                        ?>  delop-filter">
                        <span class="dashicons dashicons-category text-4xl mb-4"></span>
                        <?php 
                        $titleText = 'Choose taxonomies to filter the deleted %s.';
                        $helpText = 'You can choose to delete only the custom posts with specific taxonomies. If no taxonomy is selected, the custom posts with %s will be deleted.';
                        $strongText = "any taxonomy";
                        if ( $category == 'category' ) {
                            $titleText = 'Choose categories to filter the deleted posts.';
                            $helpText = 'You can choose to delete only the posts from specific categories. If selected, only post from the selected category (categories) will be deleted. If no category is selected, the posts from %s will be deleted.';
                            $strongText = "any category";
                        }
                        $helpTxt = sprintf( $helpText, '<strong>' . esc_html__( $strongText, "delete-old-posts" ) . '</strong>' );
                        $this->generateHelpTooltip( $helpTxt, 'w-full text-right absolute right-1 top-1', 'text-left right-1' );
                        ?>
                        <div class="mb-2 text-base">
                            <?php 
                        $custompostname = ucfirst( str_replace( "_", " ", $getCustomPostTypes[$cptIndex] ) );
                        $getTaxName = get_taxonomy_labels( get_taxonomy( $category ) );
                        echo sprintf( esc_html__( $titleText, "delete-old-posts" ), "<strong>" . esc_html__( $custompostname, "delete-old-posts" ) . "</strong>" );
                        echo "<br /><strong>" . $getTaxName->name . "</strong> ";
                        esc_html_e( "list:", 'delete-old-posts' );
                        ?>
                        </div>
                        <div class="max-h-56 overflow-auto">
                            <?php 
                        $choosedCats = ( $category == 'category' ? $this->getFiltersOpt( 'post_category' ) : $this->getFiltersOpt( 'tax_input', $category ) );
                        $choosedCats = ( is_array( $choosedCats ) ? $choosedCats : false );
                        /**
                         * Display checkboxes with categories
                         */
                        $cat = get_taxonomy( $category );
                        if ( current_user_can( $cat->cap->assign_terms ) ) {
                            // get selected categories if form was previous saved
                            $args = array(
                                'taxonomy'      => $category,
                                'hierarchical'  => true,
                                'title_li'      => '',
                                'hide_empty'    => false,
                                'selected_cats' => $choosedCats,
                                'popular_cats'  => false,
                                'checked_ontop' => true,
                            );
                            wp_terms_checklist( $post_id = 0, $args );
                        }
                        ?>
                        </div>
                    </div>
                    <?php 
                    }
                }
                break;
            case 'users':
                // get saved user filter if form was submited
                $usersFilter = $this->getFiltersOpt( 'userid' );
                // get all users
                $users = get_users( array(
                    'fields' => array('ID'),
                ) );
                // list users
                ?>
                <div class='border border-inherit p-7 relative max-w-xs delop-filter'>
                    <span class="dashicons dashicons-admin-users text-4xl mb-4"></span>
                    <?php 
                $helpTxt = sprintf( esc_html__( 'You can choose to delete only the posts from specific users. By default (no user selected), posts from %s will be deleted.', 'delete-old-posts' ), '<strong>' . esc_html__( "any user", "delete-old-posts" ) . '</strong>' );
                $this->generateHelpTooltip( $helpTxt, 'w-full text-right absolute right-1 top-1', 'text-left right-1' );
                ?>
                    <div class='m-2'>
                        <?php 
                echo "<div class='text-base'>";
                esc_html_e( 'Delete only the posts from specific users:', 'delete-old-posts' );
                echo "</div>";
                echo "\n                        <div class='max-h-56 overflow-auto'>";
                foreach ( $users as $user ) {
                    $userObj = get_user_by( 'ID', $user->ID );
                    echo "<label class='block my-2'><input type='checkbox' name='userid[]' value='" . $user->ID . "'";
                    if ( isset( $usersFilter ) && is_array( $usersFilter ) && array_search( $user->ID, $usersFilter ) !== false ) {
                        echo "checked";
                    }
                    echo "/> " . $userObj->display_name . "</label>";
                }
                echo "\n                        </div>";
                ?>
                    </div>
                </div>
                <?php 
                break;
            case 'relation':
                // get saved relation filter if form was submited
                $relationFilter = $this->getFiltersOpt( 'relation' );
                if ( empty( $relationFilter ) ) {
                    $relationFilter = 'OR';
                }
                ?>
                <div class='border border-inherit p-7 relative max-w-xs delop-filter'>
                    <span class="dashicons dashicons-forms text-4xl mb-4"></span>
                    <?php 
                $helpTxt = sprintf( esc_html__( 'Choose the relation applied to selected categories or terms. By default (no relation selected), %s will be used. "All selected categories or terms" mean that the deleted post will need to have relation with all selected categories or terms. "Any selected categories or terms" mean that if the post has relationship with only one of the selected terms, then the post will be deleted.', 'delete-old-posts' ), '<strong>' . esc_html__( "all selected terms", "delete-old-posts" ) . '</strong>' );
                $this->generateHelpTooltip( $helpTxt, 'w-full text-right absolute right-1 top-1', 'text-left right-1' );
                ?>
                    <div class='m-2'>
                        <?php 
                echo "<div class='text-base'>";
                esc_html_e( 'Choose the relation applied to selected categories or taxonomies terms:', 'delete-old-posts' );
                echo "</div>";
                echo "<label class='block my-2'><input type='radio' name='relation' value='AND'";
                if ( isset( $relationFilter ) && $relationFilter == 'AND' ) {
                    echo "checked";
                }
                echo "/>" . sprintf( esc_html__( "All selected categories or terms (post will need to be in %s selected categories or terms)", "delete-old-posts" ), '<strong>' . esc_html__( 'ALL', 'delete-old-posts' ) . '</strong>' ) . "</label>";
                echo "<label class='block my-2'><input type='radio' name='relation' value='OR'";
                if ( isset( $relationFilter ) && $relationFilter == 'OR' ) {
                    echo "checked";
                }
                echo "/>" . sprintf( esc_html__( "Any selected categories or terms (post will need to be in %s selected categories or terms)", "delete-old-posts" ), '<strong>' . esc_html__( 'ANY', 'delete-old-posts' ) . '</strong>' ) . "</label>";
                ?>
                    </div>
                </div>
                <?php 
                break;
            case 'postids':
                ?>
                <div class="border border-inherit p-7 relative max-w-xs delop-filter">
                    <span class="dashicons dashicons-portfolio text-4xl mb-4"></span>
                    <?php 
                // get saved post ids if form was submited
                $postids = $this->getFiltersOpt( 'postids' );
                $helpTxt = sprintf( esc_html__( 'If you have some posts that you %s, write the posts Ids in the text box separated with coma (ex. 1009, 2345, 4563). You can find the post id in the browser location when you edit the post (ex. ?post=2033).', 'delete-old-posts' ), '<strong>' . esc_html__( "don't want to be deleted", "delete-old-posts" ) . '</strong>' );
                $this->generateHelpTooltip( $helpTxt, 'w-full text-right absolute right-1 top-1', 'text-left right-1' );
                ?>
                    <div class="m-2">
                        <?php 
                echo "<div class='text-base'>";
                esc_html_e( 'Have some important posts? Enter the post IDs that you want to keep:', 'delete-old-posts' );
                echo "</div>";
                ?>
                        <label class="block my-2 mt-8">
                            <span><?php 
                esc_html_e( "Post IDs to exclude:", "delete-old-posts" );
                ?></span>
                            <input class="w-full" type="text" name="postids" value="<?php 
                echo sanitize_text_field( $postids );
                ?>" />
                        </label>
                    </div>
                </div>
                <?php 
                break;
            case 'search_keywords':
                ?>
                <div class="border border-inherit p-7 relative max-w-xs delop-filter">
                    <span class="dashicons dashicons-search text-4xl mb-4"></span>
                    <?php 
                // get saved post ids if form was submited
                $search_keyword = $this->getFiltersOpt( 'search_keyword' );
                $search_keyword_negativ = $this->getFiltersOpt( 'search_keyword_negativ' );
                $helpTxt = sprintf( esc_html__( 'If you want delete only some posts containing some %s, write the keywords to search in the posts here (ex. pillow). If you want to exclude some posts from the search, write your keywords in the "Negative keywords" field below. Eg, "Look for posts that include" = "pillow" and "Exclude from search results" = "sofa", will return posts containing "pillow" but not "sofa".', 'delete-old-posts' ), '<strong>' . esc_html__( "keywords", "delete-old-posts" ) . '</strong>' );
                $this->generateHelpTooltip( $helpTxt, 'w-full text-right absolute right-1 top-1', 'text-left right-1' );
                ?>
                    <div class="m-2">
                        <?php 
                echo "<div class='text-base'>";
                esc_html_e( 'Delete only posts that contain the keyword(s):', 'delete-old-posts' );
                echo "</div>";
                ?>
                        <label class="block my-2">
                            <span><?php 
                esc_html_e( "Look for posts that include the keyword(s):", "delete-old-posts" );
                ?></span>
                            <input class="w-full" type="text" name="search_keyword" value="<?php 
                echo sanitize_text_field( $search_keyword );
                ?>" />
                        </label>
                        <label class="block my-2">
                            <span><?php 
                esc_html_e( "Exclude from search results any posts that contain the keyword(s):", "delete-old-posts" );
                ?></span>
                            <input class="w-full" type="text" name="search_keyword_negativ" value="<?php 
                echo sanitize_text_field( $search_keyword_negativ );
                ?>" />
                        </label>
                    </div>
                </div>
                <?php 
                break;
            case 'attached_img':
                ?>
                <div class="border border-inherit p-7 relative max-w-xs delop-filter">
                    <span class="dashicons dashicons-images-alt2 text-4xl mb-4"></span>
                    <?php 
                // get saved attached_img option if form was submited
                $attached_img = $this->getFiltersOpt( 'attached_img' );
                $force_delete_attached_img = $this->getFiltersOpt( 'force_delete_attached_img' );
                $helpTxt = sprintf( esc_html__( 'If you also want to delete the %s attached to the post, select this option. When the post is deleted, the post thumbnail and all files attached to it will be deleted as well. Use this option carefully because there is no guarantee that the attachment isn\'t still published in some other post (ex. picture galleries).', 'delete-old-posts' ), '<strong>' . esc_html__( "featured image and all files", "delete-old-posts" ) . '</strong>' );
                $this->generateHelpTooltip( $helpTxt, 'w-full text-right absolute right-1 top-1', 'text-left right-1' );
                ?>
                    <div class="m-2">
                        <?php 
                echo "<div class='text-base'>";
                esc_html_e( 'Do you want to delete the images attached to the post (featured image and all files attached to it) when the post is deleted? Note: Attachments will not be deleted if used in another post (use "force delete" for that).', 'delete-old-posts' );
                echo "</div>";
                ?>
                        <label class="block my-2">
                            <span class="font-bold">
                                <?php 
                esc_html_e( "Delete all files attached to post:", "delete-old-posts" );
                ?>
                            </span>
                            <input class="w-full" type="checkbox" name="attached_img" value="1" <?php 
                if ( !$dop_fs->can_use_premium_code() ) {
                    echo "disabled";
                }
                ?> <?php 
                if ( $attached_img == 1 ) {
                    echo "checked";
                }
                ?> />
                        </label>
                        <label class="block my-2">
                            <span class="font-bold">
                                <?php 
                esc_html_e( "Force delete attached files:", "delete-old-posts" );
                ?>
                            </span>
                            <input 
                                class="w-full" 
                                type="checkbox" 
                                @click="confirmForceDelete()" 
                                id="forcedeleteattachedimg" 
                                name="force_delete_attached_img" 
                                value="1" 
                                <?php 
                if ( !$dop_fs->can_use_premium_code() ) {
                    echo "disabled";
                }
                ?> 
                                <?php 
                if ( $force_delete_attached_img == 1 ) {
                    echo "checked";
                }
                ?> 
                            />
                        </label>
                        <?php 
                if ( !$dop_fs->can_use_premium_code() ) {
                    echo '
                            <section class="text-red-600">' . esc_html__( 'This option is available in the Professional version.', 'delete-old-posts' );
                    echo '
                                <a href="' . $dop_fs->get_upgrade_url() . '">' . '<u>' . esc_html__( 'Upgrade and activate it.', 'delete-old-posts' ) . '</u>' . '</a>';
                    echo '
                            </section>';
                }
                ?>
                    </div>
                </div>
                <?php 
                break;
        }
    }

    /**
     * try the filter and see the results
     */
    function tryFilter() {
        global $dop_fs;
        // get the posts to delete
        $nrOfPostsToGet = $this->getNumberOfPosts();
        $altPostsArray = $this->getAltestPostsObj( $nrOfPostsToGet );
        ?>
        <div class="p-5 bg-amber-50">
            <div class="text-base mb-2">
                <?php 
        ( !empty( $altPostsArray ) ? esc_html_e( 'The following ', 'delete-old-posts' ) : esc_html_e( 'No ', 'delete-old-posts' ) );
        esc_html_e( "posts will be automatically deleted when the next scheduled cron job runs.", "delete-old-posts" );
        // check if strat deleteing the post option is on off, then show a message to set it on on
        $toggledelete = false;
        $getOptionObject = get_option( 'deloldp-post-days-option' );
        // get user saved options
        if ( is_object( $getOptionObject ) && property_exists( $getOptionObject, 'params' ) ) {
            if ( property_exists( $getOptionObject->params, 'toggledelete' ) ) {
                if ( $getOptionObject->params->toggledelete == 1 ) {
                    $toggledelete = true;
                }
            }
        }
        if ( !$toggledelete ) {
            esc_html_e( " Don't forget to set the \"Start deleting posts\" option ON, to start deleting the post automatically in the background.", "delete-old-posts" );
        }
        ?>
            </div>
            <?php 
        // get saved attached_img option
        $attached_img_opt = $this->getFiltersOpt( 'attached_img' );
        foreach ( $altPostsArray as $altPostDataObj ) {
            if ( is_object( $altPostDataObj ) ) {
                echo 'Post ' . 'ID <a href="' . get_permalink( $altPostDataObj->ID ) . '" target="_blank">' . $altPostDataObj->ID . '</a> - ' . $altPostDataObj->post_title . ' (' . $altPostDataObj->post_status . ': ' . date( "d M Y H:i", strtotime( $altPostDataObj->post_date ) ) . ')<br />';
            }
        }
        ?>
        </div>
        <?php 
    }

    /**
     * count how many elemnts before a target is reached in an array
     * @param $haystack - array to search
     * @param $target - value to search
     */
    function countArrayUntilTarget( $haystack, $target ) {
        $total = 0;
        // check if target exists in array
        if ( !array_search( $target, $haystack ) ) {
            return 0;
        }
        // if target exists count the array elements until target
        foreach ( $haystack as $key => $value ) {
            if ( $value == $target ) {
                break;
            }
            $total++;
        }
        return $total;
    }

    /**
     * Generate a checkbox in form
     *
     * @param [string] $value
     * @param [string] $label
     * @return void
     */
    private function delopt_generate_check_box( $value, $label ) {
        $checked = '';
        // get saved cpt if form was submited
        $cpt_type = $this->getFiltersOpt( 'cpt_type' );
        switch ( $value ) {
            case 'publish':
                if ( empty( $cpt_type ) || isset( $cpt_type ) && is_array( $cpt_type ) && array_search( 'publish', $cpt_type ) !== false ) {
                    $checked = "checked";
                }
                break;
            default:
                if ( isset( $cpt_type ) && is_array( $cpt_type ) && array_search( $value, $cpt_type ) !== false ) {
                    $checked = "checked";
                }
                break;
        }
        echo "\n        <label class='block my-2'>\n            <input type='checkbox' name='cpt_type[]' value='" . $value . "'";
        echo $checked;
        echo "/>\n            <span class='ml-1 decoration-2'>\n                " . esc_html( $label, 'delete-old-posts' ) . "\n            </span>\n        </label>";
    }

    /*
     * Find parent key of array
     *
     * @param [type] $array
     * @param [type] $needle
     * @param [type] $parent
     * @return void
     */
    private function delop_find_parent( $array, $needle, $parent = null ) {
        foreach ( $array as $key => $value ) {
            $ind = array_search( $needle, $value );
            if ( $ind !== false ) {
                return $key;
                break;
            }
        }
        return false;
    }

}
