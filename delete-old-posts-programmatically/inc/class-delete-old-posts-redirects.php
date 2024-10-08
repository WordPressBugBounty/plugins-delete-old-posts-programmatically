<?php
namespace DEL\OLD\Posts\Cls;

/**
 * Make the plugin class.
 */
class Delete_Old_Posts_Redirects extends Delete_Old_Posts {

    /**
     * Hooks init (nothing else) and calls things that need to run right away.
     */
    public function __construct(){
        // add redirection for deleted posts
        add_action('template_redirect', [ $this, 'deloldp_redirectDeletedPosts' ]);

        add_action( 'admin_menu', [ $this, 'deloldp_custom_menu_page' ] );
    }

    /**
     * add a custom menu in admin menu
     */
    function deloldp_custom_menu_page() {

        global $deloldp_redirects_submenu;

        // Add submenu page with same slug as parent to ensure no duplicates
        $deloldp_redirects_submenu = add_submenu_page(
            'delete-old-posts',
            'Redirects - Delete old posts automatically',
            esc_html__('Redirects', 'delete-old-posts'),
            'manage_options',
            'delete-old-posts-redirects',
            [$this, 'deloldpRedirects']
        );

        /** Create the screen option for submenu */
        add_action("load-$deloldp_redirects_submenu", [$this, "deloldp_redirects_screen_options"]);
    }

    /**
     * Logic to redirect deleted posts to similar ones
     */
    function deloldp_redirectDeletedPosts(){

        global $wp;
        
        /**
         * check if user enabled the redirects
         */
        $getOptionObject = get_option('deloldp-post-days-option');
        if ( is_object($getOptionObject) && property_exists($getOptionObject, 'params') )
        if ( isset($getOptionObject->params->deloldpRedirect) && $getOptionObject->params->deloldpRedirect == 0 ) return false;
        
        // user have enabled the redirects - go on
        if (is_404()){ // check if 404 page
            $requestedUrl = home_url( $wp->request );
            /**
             * check the requested url into deleted posts opt
             */
            $deletedPostsNames = get_option('deletedpostredirectsopt');
            if( is_array($deletedPostsNames) ) foreach( $deletedPostsNames as $deletedPostKey => $deletedPostN ){
                if( stristr($requestedUrl, $deletedPostN) !== false ){
                    /**
                     * the requested url is one of deleted posts
                     * redirect it to a similar post or check if was manually edited by user
                     */
                    // check if redirect manually edited and redirect to the requested URL
                    $redirectsOptEdited = get_option('deletedpostredirectsoptedited');
                    if( is_array($redirectsOptEdited) && array_key_exists($deletedPostKey, $redirectsOptEdited) ) {
                        wp_redirect( esc_url($redirectsOptEdited[$deletedPostKey]), 301 );
                        exit;
                    }
                    // redirect was not manually edited - search for sumilar posts
                    $redirect_url = $this->deloldp_posts_results_filter($requestedUrl);
                    wp_redirect( esc_url($redirect_url), 301 );
                    exit;
                }
            }

            /**
             * URL was not found in the list
             * Do Nothing!
             */
            return false;
        }
    }

    /**
     * search similar posts
     * 
     * @param $requestedUrl (string)
     */
    function deloldp_posts_results_filter( $requestedUrl ){
        $requestedUrl = (string) $requestedUrl;
        $args = array(
            'numberposts'      => 50000,
            // 'category'         => 0,
            'orderby'          => 'date',
            'order'            => 'DESC',
            'include'          => array(),
            'exclude'          => array(),
            'meta_key'         => '',
            'meta_value'       => '',
            'post_type'        => 'any',
            'suppress_filters' => true,
        );
        $posts = get_posts( $args );
        
        $filtered_posts = array();
        foreach ( $posts as $post ) {
            similar_text($post->post_name, $requestedUrl, $similarPercentage);
            $filtered_posts[$similarPercentage] = $post->ID;
        }
        
        // sort the post after similarity percentage
        krsort($filtered_posts);
        $bestPostMatch = reset($filtered_posts);
        // error_log('Best similar match: ' . array_key_first($filtered_posts) . ' - ' . print_r(get_permalink($bestPostMatch), true));
        
        return get_permalink($bestPostMatch);
    }

    /**
     * Create the rediects page
     */
    function deloldpRedirects(){
       
        /** Check and make the redirects table actions */
        $this->tableActions();

        /**
         * Check if user changed the screen options
         * Update the user_meta if changed -> used into Redirects_List_Table->prepare_items
         */
        if( isset($_POST['wp_screen_options']) && is_array($_POST['wp_screen_options']) ){
            if( isset($_POST['wp_screen_options']['option']) && isset($_POST['wp_screen_options']['value']) ){
                $wp_screen_options = $_POST['wp_screen_options']['option'];
                switch ($wp_screen_options){
                    case 'redirects_per_page':
                        update_user_meta( get_current_user_id(), 'redirects_per_page', $_POST['wp_screen_options']['value'] );
                        break;
                }
            }
        }

        /**
         * Show redirects table
         */
        $redirects_list_table =  new Redirects_List_Table();
        ?>
        <div class='mx-4 my-8'>
            <div class="wrap"><h2><?php esc_html_e('Deleted posts list', 'delete-old-posts'); ?></h2></div>
            <?php
            $page   = filter_input( INPUT_GET, 'page', FILTER_UNSAFE_RAW );
            $paged  = filter_input( INPUT_GET, 'paged', FILTER_SANITIZE_NUMBER_INT );
            // $s      = filter_input( INPUT_GET, 's', FILTER_UNSAFE_RAW );
            
            echo '
            <form method="post" id="delete-old-posts-redirects">';
                printf( '<input type="hidden" name="page" value="%s" />', $page );
                printf( '<input type="hidden" name="paged" value="%d" />', $paged );
                $redirects_list_table->prepare_items();
                $redirects_list_table->search_box( __( 'Search redirects', 'delete-old-posts' ), 'search_id' );
                $redirects_list_table->display();
                echo '
            </form>';
            ?>
        </div>
        <?php
    }

    /**
     * add screen options for the redirects page
     */
    function deloldp_redirects_screen_options() {
    
        global $deloldp_redirects_submenu;

        $screen = get_current_screen();
 
        // get out of here if we are not on our settings page
        if(!is_object($screen) || $screen->id != $deloldp_redirects_submenu)
            return;

        /** check if items per page was set in the screen options */
        $redirects_per_page = get_user_meta(get_current_user_id(), 'redirects_per_page', false);
        if( ! $redirects_per_page || $redirects_per_page == '' ) $redirects_per_page = 10;
        
        $args = array(
            'label' => __('Redirects per page', 'delete-old-posts'),
            'default' => $redirects_per_page,
            'option' => 'redirects_per_page'
        );
        add_screen_option( 'per_page', $args );
    }

    /**
     * Make the redirects table actions
     */
    function tableActions(){
        if( isset($_REQUEST['action']) )
        switch( $_REQUEST['action'] ){
            case 'delete_all':
                /** delete redirects in bulk */
                $deletedPostsNames = get_option('deletedpostredirectsopt');
                if( isset($_REQUEST['redirect_id']) ) foreach( $_REQUEST['redirect_id'] as $redirect_id_to_delete ){
                    unset( $deletedPostsNames[absint($redirect_id_to_delete)] );
                    update_option('deletedpostredirectsopt', $deletedPostsNames);
                }
                printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( 'notice notice-success' ), esc_html( __('The redirects have been deleted.', 'delete-old-posts') ) );
                break;
            case 'edit':
                if (isset($_GET['element']) && (isset($_GET['_wpnonce']) & wp_verify_nonce($_GET['_wpnonce'], 'edit_redirect'))){
                    /** 
                    * Nonce verified - edit the redirect 
                    * Create a new option and store the new link
                    * Check if the id of the redirect is in the new option, then redirect to edited link
                    */
                    $element            = intval($_GET['element']);
                    $deletedPostsNames  = get_option('deletedpostredirectsopt');
                    $old_redirect       = $deletedPostsNames[$element];

                    /**
                     * Check if form was saved and don't show it
                    */
                    if( ! isset($_POST['new_redirect']) ){
                        // check if redirect was already edited
                        $redirectsOptEdited = get_option('deletedpostredirectsoptedited');
                        ?>
                        <form method="post" class="flex flex-col flex-wrap p-6">
                            <label class="" for="old_redirect">
                                <span class=""><?php esc_html_e('Deleted post slug:', 'delete-old-posts'); ?></span>
                                <input class="p-1" type="text" disabled name="old_redirect" value="<?php echo $old_redirect; ?>" />
                            </label>
                            <label class="" for="new_redirect">
                                <span class="block"><?php esc_html_e('New redirect to post (full URL starting with https:// or http://):', 'delete-old-posts'); ?></span>
                                <input class="w-3/4" type="text" name="new_redirect" value="<?php if( isset($redirectsOptEdited[absint($_GET['element'])]) ) echo $redirectsOptEdited[absint($_GET['element'])]; ?>" />
                            </label>
                            <input 
                                class="mt-3 cursor-pointer p-2 text-center w-1/5 bg-gray-200 drop-shadow-sm hover:bg-gray-400 hover:text-white" 
                                type="submit" 
                                value="Save the new link" 
                            />
                        </form>
                        <?php
                    }
                    /** 
                     * Save the edted redirect in option and check when the redirection is made 
                    */
                    if( isset($_POST['new_redirect']) && $_POST['new_redirect'] != '' ){
                        $new_redirect_url = sanitize_url( $_POST['new_redirect'] );
                        // check if valid URL was inserted
                        if ( ! wp_http_validate_url( $new_redirect_url ) ) {
                            printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( 'notice notice-error' ), esc_html( __('It\'s NOT valid URL.', 'delete-old-posts') ) );
                            break;
                        }
                        $redirectsOptEdited = get_option('deletedpostredirectsoptedited');
                        if( ! is_array($redirectsOptEdited) ) $redirectsOptEdited = array();
                        $redirectsOptEdited[absint($_GET['element'])] = $new_redirect_url;
                        update_option('deletedpostredirectsoptedited', $redirectsOptEdited);
                        printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( 'notice notice-success' ), esc_html( __('The new redirect have been saved.', 'delete-old-posts') ) );
                    }
                }
                break;
            case 'delete':
                if (isset($_GET['element']) && isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'delete_redirect')){
                    /** Nonce verified - delete the redirect */
                    $element            = absint($_GET['element']);
                    $deletedPostsNames  = get_option('deletedpostredirectsopt');
                    if( isset($deletedPostsNames[$element]) ){
                        $redirect_text      = $deletedPostsNames[$element];
                        unset($deletedPostsNames[$element]);
                        update_option('deletedpostredirectsopt', $deletedPostsNames);
                        printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( 'notice notice-success' ), esc_html( __('The redirect "'.$redirect_text.'" have been deleted.', 'delete-old-posts') ) );
                    }
                }
                break;
        }
    }
}

/**
 * ================================================================================== WP_List_Table Class for Redirections
 */
// WP_List_Table is not loaded automatically so we need to load it in our application
if( !class_exists('WP_List_Table') ){
    require_once( ABSPATH . 'wp-admin/includes/screen.php' );
    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}
/**
 * Create the class that will list the redirects table and will extend the WP_List_Table
 */
class Redirects_List_Table extends \WP_List_Table {
    /**
     * Prepare the items for the table to process
     *
     * @return Void
     */
    public function prepare_items()
    {
        $columns = $this->get_columns();
        $hidden = $this->get_hidden_columns();
        $sortable = $this->get_sortable_columns();
        
        /**
         * set the number of items displayed / page
         */
        $perPage = 10;
        /** check if items per page was set in the screen options */
        $redirects_per_page = intval(get_user_meta(get_current_user_id(), 'redirects_per_page', true));
        if( is_int($redirects_per_page) && $redirects_per_page > 0 ) $perPage = $redirects_per_page;
        
        /** check if it a search - then display all entries */
        $search = ( isset( $_POST['s'] ) ) ? $_POST['s'] : '';
        if( $search != '' ) {
            /** get table data on search */
            $data = $this->table_data($search);
            $perPage = count($data);
        } else {
            $data = $this->table_data();
        }

        // sort data
        usort( $data, array( &$this, 'sort_data' ) );
        
        $currentPage = $this->get_pagenum();
        $totalItems = count($data);
        
        $this->set_pagination_args( array(
            'total_items' => $totalItems,
            'per_page'    => $perPage
        ) );

        $data = array_slice($data,(($currentPage-1)*$perPage),$perPage);

        $this->_column_headers = array($columns, $hidden, $sortable);
        $this->items = $data;
    }

    /**
     * Override the parent columns method. Defines the columns to use in your listing table
     *
     * @return Array
     */
    public function get_columns()
    {
        $columns = array(
            'cb'        => '<input type="checkbox" />',
            'id'        => __('ID', 'delete-old-posts'),
            'post_slug' => __('Deleted post slug', 'delete-old-posts'),
            'check'     => __('Redirection', 'delete-old-posts'),
        );

        return $columns;
    }

    /**
     * Define which columns are hidden
     *
     * @return Array
     */
    public function get_hidden_columns()
    {
        return array();
    }

    /**
     * Define the sortable columns
     *
     * @return Array
     */
    public function get_sortable_columns()
    {
        $sortable_columns = array(
            'id'        => array('id', false),
            'post_slug' => array('post_slug', false)
        );
        
        return $sortable_columns;
    }

    /**
     * Get the table data
     *
     * @return Array
     */
    private function table_data( $search = '' )
    {
        $data = array();
        
        // get deleted posts array and edited redirections array
        $deletedPostsNames = get_option('deletedpostredirectsopt');
        $redirectsOptEdited = get_option('deletedpostredirectsoptedited');

        // get the permalink structure
        $permalink_structure = get_option( 'permalink_structure' );
        if( stristr( $permalink_structure, '%postname%' ) === false ) {
            printf( '<div class="%1$s"><p>%2$s <a href="%3$s">%4$s</a>.</p></div>', esc_attr( 'notice notice-error' ), esc_html( __('Sorry. You can\'t use the redirection feature because your permalink structure is not using the postname. To use the redirect, change your permanent structure in ', 'delete-old-posts') ), admin_url('options-permalink.php'), esc_attr( __('settings', 'delete-old-posts') ) );
            return $data; // return empty data
        }
        
        if( is_array($deletedPostsNames) ) foreach( $deletedPostsNames as $key => $deletedPostSlug) {
            if ( ! empty($search) && ! stristr($deletedPostSlug, $search) ) continue;
            $checkTxt = 'Check redirection';
            $checkUrl = get_home_url() .'/'. $deletedPostSlug;
            if( isset($redirectsOptEdited[$key]) && esc_url($redirectsOptEdited[$key]) != '' ) {
                $checkTxt = esc_url($redirectsOptEdited[$key]);
            }
            
            $data[] = array(
                    'id'        => $key,
                    'post_slug' => $deletedPostSlug,
                    'check'     => '<a href="' . $checkUrl . '" target="_blank" class="text-sky-500">'.__($checkTxt, 'delete-old-posts').'</a>',
                    );
        }
        
        return $data;
    }

    /**
     * Define what data to show on each column of the table
     *
     * @param  Array $item        Data
     * @param  String $column_name - Current column name
     *
     * @return Mixed
     */
    public function column_default( $item, $column_name )
    {
        switch( $column_name ) {
            case 'id':
            case 'post_slug':
            case 'check':
                return $item[ $column_name ];

            default:
                return print_r( $item, true ) ;
        }
    }

    /**
     * Allows you to sort the data by the variables set in the $_GET
     *
     * @return Mixed
     */
    private function sort_data( $a, $b ) {
        // Set defaults
        $orderby = 'post_slug';
        $order = 'asc';
        $result = strcmp( $a[$orderby], $b[$orderby] );

        // If orderby is set, use this as the sort column
        if(!empty($_GET['orderby']))
        {
            $orderby = $_GET['orderby'];
        }

        // If order is set use this as the order
        if(!empty($_GET['order']))
        {
            $order = $_GET['order'];
        }

        if( isset($_GET['orderby']) ) switch( $_GET['orderby'] ){
            case 'id':
                $result = strnatcmp( $a[$orderby], $b[$orderby] );
                break;
        }
        
        if($order === 'asc') {
            return $result;
        }

        return -$result;
    }

    /**
     * create checkboxes for table rows
     * checkbox will come in handy when we need to create bulk actions to our table.
     */
    function column_cb($item)
    {
        return sprintf('<input type="checkbox" name="redirect_id[]" value="%s" />', $item['id']);
    }

    /**
     * Adding action links to column
     */
    function column_post_slug($item){
        if( !isset($_REQUEST['page']) || !isset($item['id']) || !isset($item['post_slug']) ) return false; //exit earlier

        $actions = array(
                'edit'      => '<a href="' . esc_url( wp_nonce_url( admin_url('admin.php') . sprintf('?page=%s&action=%s&element=%s&paged=%s', esc_html($_REQUEST['page']), 'edit', $item['id'], esc_html($_REQUEST['paged'])), 'edit_redirect' ) ) . '">' . __('Edit', 'delete-old-posts') . '</a>',
                'delete'    => '<a href="' . esc_url( wp_nonce_url( admin_url('admin.php') . sprintf('?page=%s&action=%s&element=%s&paged=%s', esc_html($_REQUEST['page']), 'delete', $item['id'], esc_html($_REQUEST['paged'])), 'delete_redirect' ) ) . '">' . __('Delete', 'delete-old-posts') . '</a>',
        );

        return sprintf('%1$s %2$s', $item['post_slug'], $this->row_actions($actions));
    }

    /**
     * show bulk action dropdown
     */
    function get_bulk_actions() {
        $actions = array(
                'delete_all'    => __('Delete', 'delete-old-posts'),
        );

        return $actions;
    }
}
?>