<?php

namespace DEL\OLD\Posts\Cls;

/**
 * Make the plugin class.
 */
class Delete_Old_Posts {
    /**
     * Hooks init (nothing else) and calls things that need to run right away.
     */
    public function __construct() {
        add_action( 'admin_menu', [$this, 'deloldp_custom_menu_page'] );
        add_filter( 'plugin_action_links_delete-old-posts/delete-old-posts.php', [$this, 'deloldp_settings_link'] );
        // add scheduled task
        add_filter( 'cron_schedules', [$this, 'deloldp_add_cron_interval'] );
        add_action( 'deloldp_cron_delete_old_posts', [$this, 'deloldp_cron_exec'] );
        if ( !wp_next_scheduled( 'deloldp_cron_delete_old_posts' ) ) {
            wp_schedule_event( time(), 'fifteen_seconds', 'deloldp_cron_delete_old_posts' );
        }
        // delete scheduled tasks when deactivated
        register_deactivation_hook( plugin_dir_path( dirname( __FILE__, 1 ) ), [$this, 'deloldp_deactivate'] );
        // Not like register_uninstall_hook(), you do NOT have to use a static function.
        dop_fs()->add_action( 'after_uninstall', [$this, 'deloldp_deleted'] );
    }

    /**
     * add a custom menu in admin menu
     */
    function deloldp_custom_menu_page() {
        $deloldp_menu = add_menu_page(
            __( 'Delete old posts', 'delete-old-posts' ),
            esc_html__( 'Delete old posts', 'delete-old-posts' ),
            'manage_options',
            'delete-old-posts',
            array($this, 'deloldp_PluginPage'),
            'dashicons-trash',
            5
        );
    }

    /**
     * add settings link to plugin in the list of plugins
     */
    function deloldp_settings_link( $links ) {
        // Build and escape the URL.
        $url = esc_url( add_query_arg( 'page', 'delete-old-posts', get_admin_url() . 'admin.php' ) );
        // Create the link.
        $settings_link = "<a href='{$url}'>" . __( 'Settings' ) . '</a>';
        // Adds the link to the end of the array.
        array_push( $links, $settings_link );
        return $links;
    }

    /**
     * create an alert
     */
    function deloldp_makeAlert( $alertText = '', $alertStyle = 'success', $alertClose = 'is-dismissible' ) {
        $alert = '
        <div class="notice notice-' . $alertStyle . ' ' . $alertClose . '">
            <p>' . $alertText . '</p>
        </div>';
        return $alert;
    }

    /*
     * trim words
     */
    private function deloldp_TrimWords( $s, $limit = 3 ) {
        return preg_replace( '/((\\w+\\W*){' . ($limit - 1) . '}(\\w+))(.*)/', '${1}', $s );
    }

    /**
     * create a dropdown
     */
    public function deloldp_Dropdown() {
        ?>
        <div x-data="{ open: false }" class="p-6 bg-gray-200 m-3.5">
            <button @click="open = true">Open Dropdown</button>

            <ul
                x-show="open"
                @click.away="open = false"
                x-cloak
            >
                Dropdown Body
            </ul>
        </div>
        <?php 
    }

    /**
     * function to display on Plugin Page
     */
    public function deloldp_PluginPage() {
        /**
         * check if user have the rights to delete posts
         */
        if ( !current_user_can( 'delete_posts' ) & !current_user_can( 'delete_others_posts' ) ) {
            esc_html_e( "You don't have rights to delete the posts.", "delete-old-posts" );
            // exit
            return;
        }
        global $dop_fs;
        $displayInfoTop = false;
        $pluginOptionArray = array();
        $postDeleteDays = 9999;
        $pluginName = basename( plugin_dir_path( dirname( __FILE__, 1 ) ) );
        $pluginName = ucwords( str_replace( "-", " ", $pluginName ) );
        /**
         * process the post data
         */
        if ( isset( $_POST['deloldp-post-days'] ) ) {
            if ( !isset( $_POST['secured-delete-old-posts'] ) || !wp_verify_nonce( $_POST['secured-delete-old-posts'], 'start-delete-old-posts' ) ) {
                esc_html__( 'Sorry, the security key did not verify.', 'delete-old-posts' );
                exit;
            } else {
                // process form data
                if ( $_POST['deloldp-post-days'] > 0 ) {
                    // save the desired post date in option
                    $pluginOptionArray['deloldpDays'] = (int) sanitize_text_field( $_POST['deloldp-post-days'] );
                    if ( $dop_fs->can_use_premium_code() ) {
                        // save skiptrash to option
                        if ( !isset( $_POST['deloldp-post-skiptrash'] ) ) {
                            $pluginOptionArray['deloldpSkiptrash'] = 0;
                        } else {
                            if ( $_POST['deloldp-post-skiptrash'] == 1 ) {
                                $pluginOptionArray['deloldpSkiptrash'] = 1;
                            }
                        }
                        // save number of posts to delete
                        if ( !isset( $_POST['deloldp-posts-number'] ) ) {
                            $pluginOptionArray['deloldpPostsNr'] = 1;
                        } else {
                            if ( $_POST['deloldp-posts-number'] > 1 ) {
                                $pluginOptionArray['deloldpPostsNr'] = (int) sanitize_text_field( $_POST['deloldp-posts-number'] );
                            }
                        }
                    }
                    // save redirection to option
                    if ( !isset( $_POST['deloldp-post-redirect'] ) ) {
                        $pluginOptionArray['deloldpRedirect'] = 0;
                    } else {
                        if ( $_POST['deloldp-post-redirect'] == 1 ) {
                            $pluginOptionArray['deloldpRedirect'] = 1;
                        }
                    }
                    // save start/ stop deleting posts option
                    if ( !isset( $_POST['toggledelete'] ) ) {
                        $pluginOptionArray['toggledelete'] = 0;
                    } else {
                        if ( $_POST['toggledelete'] == 1 ) {
                            $pluginOptionArray['toggledelete'] = 1;
                        }
                    }
                    // save fixed date to option
                    if ( isset( $_POST['deloldp-fix-date'] ) & isset( $_POST['date'] ) ) {
                        $pluginOptionArray['deloldpFixDate'] = sanitize_text_field( $_POST['date'] );
                    }
                    // save data into plugin option
                    $this->saveOption( $pluginOptionArray );
                } else {
                    // display info
                    echo $this->deloldp_makeAlert( esc_html__( "Choose a date or specify the number of days for the posts to be deleted!", "delete-old-posts" ), "warning", "is-dismissible" );
                }
            }
        }
        // get user saved options
        $getOptionObject = get_option( 'deloldp-post-days-option' );
        // display an Info on the top of the page
        if ( is_object( $getOptionObject ) && property_exists( $getOptionObject, 'params' ) && property_exists( $getOptionObject->params, 'deloldpDays' ) ) {
            $postDeleteDays = $getOptionObject->params->deloldpDays;
        }
        if ( is_object( $getOptionObject ) && property_exists( $getOptionObject, 'params' ) && property_exists( $getOptionObject->params, 'toggledelete' ) ) {
            $postToggledelete = $getOptionObject->params->toggledelete;
        }
        $postDeleteDaysTime = $this->userDaysToTimestamp();
        // display info on top only when the post delete days have been set
        if ( isset( $postDeleteDaysTime ) && $postDeleteDaysTime > 0 ) {
            $displayInfoTop = true;
        }
        $infoText = __( "Hint! Test out the list of deleted posts in the \"Filters\" menu, and when you're ready to begin deleting posts, activate the \"Start deleting posts\" option and click \"Save\". Note that no posts will be deleted until you take this action.", "delete-old-posts" );
        if ( $displayInfoTop ) {
            if ( $postDeleteDays > 0 & $postToggledelete == 1 ) {
                $timezoneGmtOffset = get_option( 'gmt_offset' );
                $timestampNextCronRun = wp_next_scheduled( 'deloldp_cron_delete_old_posts' );
                $calculateMaxNumberOfDeletedPosts = ( isset( $getOptionObject->params->deloldpPostsNr ) ? 60 / 15 * 60 * 24 * $getOptionObject->params->deloldpPostsNr : 60 / 15 * 60 * 24 );
                $infoText = sprintf(
                    __( "Posts published on %s %s, %s and before that will be deleted regularly in the background. Up to %d Posts/Day.", "delete-old-posts" ),
                    date( "F", $postDeleteDaysTime ),
                    date( "d", $postDeleteDaysTime ),
                    date( "Y", $postDeleteDaysTime ),
                    $calculateMaxNumberOfDeletedPosts
                );
                $infoText .= '<br />';
                $infoText .= sprintf( __( "Next cron job run scheduled on %s.", "delete-old-posts" ), gmdate( "l jS \\of F Y h:i:s A", $timestampNextCronRun + $timezoneGmtOffset * 3600 ) );
                // show warning if post are permanently deleted
                if ( is_object( $getOptionObject ) && property_exists( $getOptionObject, 'params' ) ) {
                    if ( $dop_fs->can_use_premium_code() ) {
                        if ( is_object( $getOptionObject->params ) && property_exists( $getOptionObject->params, 'deloldpSkiptrash' ) & $getOptionObject->params->deloldpSkiptrash == 1 ) {
                            $infoText .= sprintf( " <span class='text-red-500'>%s.</span>", __( 'The posts will be permanently deleted', 'delete-old-posts' ) );
                        }
                    }
                }
            }
            // show info on top
            echo $this->deloldp_makeAlert( $infoText, "info", "is-dismissible" );
        }
        // check if fixed date selected
        $fixDate = false;
        if ( is_object( $getOptionObject ) && property_exists( $getOptionObject, 'params' ) ) {
            if ( isset( $getOptionObject->params->deloldpFixDate ) && $getOptionObject->params->deloldpFixDate != '' ) {
                $fixDate = true;
            }
        }
        ?>

        <section x-data="deloldp_Start()" x-init="onstart()">
            <div id="pluginInfo" x-cloak class="bg-green-50 border-t-4 border-green-500 rounded-b text-teal-900 px-4 py-3 shadow-md m-5 relative" role="info">
                <div class="flex">
                    <div class="py-1"><svg class="fill-current h-6 w-6 text-teal-500 mr-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path d="M2.93 17.07A10 10 0 1 1 17.07 2.93 10 10 0 0 1 2.93 17.07zm12.73-1.41A8 8 0 1 0 4.34 4.34a8 8 0 0 0 11.32 11.32zM9 11V9h2v6H9v-4zm0-6h2v2H9V5z"/></svg></div>
                    <div>
                        <p class="font-bold"><?php 
        esc_html_e( 'Choose a date or specify the number of days to have your posts automatically deleted.', 'delete-old-posts' );
        ?></p>
                        <p class="text-sm">
                            <?php 
        esc_html_e( 'With the available filters, you have complete control to determine which posts are deleted. Posts are automatically deleted when the WordPress Cron runs or whenever someone visits your website.', 'delete-old-posts' );
        ?>
                        </p>
                    </div>
                </div>
                <button type="button" class="notice-dismiss" @click="dismiss">
                    <span class="screen-reader-text"><?php 
        esc_html_e( 'Dismiss this notice.', 'delete-old-posts' );
        ?></span>
                </button>
            </div>

            <form method="post">
                <div class="flex-grow container mx-auto sm:px-4 pt-6 pb-6">
                    <div class="bg-white border-t border-b sm:border-l sm:border-r sm:rounded shadow mb-6">
                        <div class="border-b px-6">
                            <div class="flex justify-between -mb-px">
                                <div class=" text-blue-dark py-4 text-lg">
                                    <?php 
        esc_html_e( 'Delete old posts automatically', 'delete-old-posts' );
        ?>
                                </div>
                                <div class="flex text-sm">
                                    <div class="py-4 text-grey-dark border-b border-transparent hover:border-grey-dark mr-3">
                                        <span class="dashicons dashicons-trash"></span>    
                                        <span class="dashicons dashicons-image-rotate"></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="lg:flex">
                            <div class="lg:w-1/2 text-center py-8">
                                <div class="md:border-r md:border-b md:pb-6 lg:border-b-0">
                                    <div class="m-2 text-base">
                                        <?php 
        esc_html_e( 'Delete posts older than:', 'delete-old-posts' );
        ?>
                                        <input 
                                            type="number" 
                                            size="5" 
                                            name="deloldp-post-days" 
                                            id="deloldpDays" 
                                            x-on:input.debounce.1750="checkDays($event.target.value)" 
                                            class="w-20"
                                            <?php 
        if ( !$fixDate ) {
            if ( is_object( $getOptionObject ) && property_exists( $getOptionObject, 'params' ) ) {
                if ( $getOptionObject->params->deloldpDays > 0 ) {
                    echo " value='" . $getOptionObject->params->deloldpDays . "'";
                }
            }
        } else {
            // get the number of days between two dates
            $fixDateTimestamp = strtotime( $getOptionObject->params->deloldpFixDate );
            $datediff = time() - $fixDateTimestamp;
            echo " value='" . round( $datediff / (60 * 60 * 24) ) . "'";
        }
        ?>
                                        />
                                        <?php 
        esc_html_e( 'days.', 'delete-old-posts' );
        ?>
                                        <span 
                                            class="dashicons dashicons-editor-help has-tooltip"
                                            x-on:mouseover="tooltip = true" 
                                            x-on:mouseleave="tooltip = false"
                                        >
                                            <span class='tooltip rounded shadow-lg bg-gray-100 p-3 text-sm font-sans -mt-8 text-left max-w-md'>
                                                <?php 
        printf( __( 'Your posts published before the %s, will be automatically deleted in the background any time the WP Cron runs or someone visits your website.', 'delete-old-posts' ), '<strong>specified days</strong>' );
        ?>
                                            </span>
                                        </span>
                                    </div>
                                    <div class="m-2 text-gray-500">
                                        <?php 
        esc_html_e( "Choose the number of ", "delete-old-posts" );
        ?>
                                        <select
                                            id="deloldp-post-months"
                                            name="deloldp-post-months"
                                            x-on:change="calculateDaysInMonths($event.target.value)"
                                        >
                                            <option value="" selected="selected"><?php 
        esc_html_e( "months", "delete-old-posts" );
        ?></option>
                                            <option value="1">1</option>
                                            <option value="2">2</option>
                                            <option value="3">3</option>
                                            <option value="4">4</option>
                                            <option value="5">5</option>
                                            <option value="6">6</option>
                                            <option value="7">7</option>
                                            <option value="8">8</option>
                                            <option value="9">9</option>
                                            <option value="10">10</option>
                                            <option value="11">11</option>
                                            <option value="12">12</option>
                                        </select>
                                        <?php 
        esc_html_e( ' to calculate the number of days automatically,', 'delete-old-posts' );
        ?>
                                        <!-- <span class="dashicons dashicons-arrow-up-alt cursor-pointer" x-on:click="copyDays()"></span>  -->
                                        <span id="daysInXMonths" class="hidden"></span> 
                                        <?php 
        // esc_html_e('Days', 'delete-old-posts');
        ?>
                                        <!-- <span class="text-xs cursor-pointer" x-on:click="copyDays()"><?php 
        esc_html_e( 'Click to copy', 'delete-old-posts' );
        ?></span> -->
                                    </div>
                                    <div class="m-2">
                                        <div x-data="app()" x-init="[initDate(), getNoOfDays()]" x-cloak>
                                            <div class="container mx-auto">
                                                <div class="mx-auto w-64">
                                                    <label for="datepicker" class="mb-1 text-gray-500 block">
                                                        <?php 
        esc_html_e( "or select a date to calculate days in the past automatically:", "delete-old-posts" );
        ?>
                                                    </label>
                                                    <div class="relative">
                                                    <input type="hidden" name="date" x-ref="date" :value="datepickerValue" />
                                                    <input type="text" x-on:click="showDatepicker = !showDatepicker" x-model="datepickerValue" x-on:keydown.escape="showDatepicker = false" class="w-full pl-4 pr-10 py-3 !bg-white leading-none rounded-lg shadow-sm focus:outline-none text-gray-600 font-medium focus:ring focus:ring-blue-600 focus:ring-opacity-50" placeholder="<?php 
        esc_html__( "Select date", "delete-old-posts" );
        ?>" readonly />

                                                    <div class="absolute top-0 right-0 px-3 py-1">
                                                        <svg class="h-6 w-6 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                        </svg>
                                                    </div>

                                                    <!-- <div x-text="no_of_days.length"></div>
                                                                    <div x-text="32 - new Date(year, month, 32).getDate()"></div>
                                                                    <div x-text="new Date(year, month).getDay()"></div> -->

                                                    <div class="bg-white mt-12 rounded-lg shadow p-4 absolute top-0 left-0" style="width: 17rem" x-show.transition="showDatepicker" @click.away="showDatepicker = false">
                                                        <div class="flex justify-between items-center mb-2">
                                                            <div>
                                                                <span x-text="MONTH_NAMES[month]" class="text-lg font-bold text-gray-800"></span>
                                                                <span x-text="year" class="ml-1 text-lg text-gray-600 font-normal"></span>
                                                            </div>
                                                            <div>
                                                                <button type="button" class="focus:outline-none focus:shadow-outline transition ease-in-out duration-100 inline-flex cursor-pointer hover:bg-gray-100 p-1 rounded-full" @click="if (month == 0) {
                                                                                                year--;
                                                                                                month = 12;
                                                                                            } month--; getNoOfDays()">
                                                                <svg class="h-6 w-6 text-gray-400 inline-flex" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                                                                </svg>
                                                                </button>
                                                                <button type="button" class="focus:outline-none focus:shadow-outline transition ease-in-out duration-100 inline-flex cursor-pointer hover:bg-gray-100 p-1 rounded-full" @click="if (month == 11) {
                                                                                                month = 0; 
                                                                                                year++;
                                                                                            } else {
                                                                                                month++; 
                                                                                            } getNoOfDays()">
                                                                <svg class="h-6 w-6 text-gray-400 inline-flex" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                                                </svg>
                                                                </button>
                                                            </div>
                                                        </div>

                                                        <div class="flex flex-wrap mb-3 -mx-1">
                                                            <template x-for="(day, index) in DAYS" :key="index">
                                                                <div style="width: 14.26%" class="px-0.5">
                                                                <div x-text="day" class="text-gray-800 font-medium text-center text-xs"></div>
                                                                </div>
                                                            </template>
                                                        </div>

                                                        <div class="flex flex-wrap -mx-1">
                                                            <template x-for="blankday in blankdays">
                                                                <div style="width: 14.28%" class="text-center border p-1 border-transparent text-sm"></div>
                                                            </template>
                                                            <template x-for="(date, dateIndex) in no_of_days" :key="dateIndex">
                                                                <div style="width: 14.28%" class="px-1 mb-1">
                                                                <div @click="getDateValue(date)" x-text="date" class="cursor-pointer text-center text-sm leading-none rounded-full leading-loose transition ease-in-out duration-100" :class="{
                                                                    'bg-indigo-200': isToday(date) == true, 
                                                                    'text-gray-600 hover:bg-indigo-200': isToday(date) == false && isSelectedDate(date) == false,
                                                                    'bg-indigo-500 text-white hover:bg-opacity-75': isSelectedDate(date) == true 
                                                                    }"></div>
                                                                </div>
                                                            </template>
                                                        </div>
                                                    </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mt-8">
                                        <input 
                                            type="checkbox"
                                            value="<?php 
        if ( $fixDate ) {
            echo $getOptionObject->params->deloldpFixDate;
        }
        ?>"
                                            name="deloldp-fix-date"
                                            id="fixdate"
                                            <?php 
        if ( $fixDate ) {
            echo ' checked';
        }
        ?>
                                        />
                                        <label for="fixdate">
                                            <?php 
        esc_html_e( "Use a fixed date instead of a number of days in the past.", 'delete-old-posts' );
        ?> 
                                            <span 
                                                class="dashicons dashicons-editor-help has-tooltip"
                                                x-on:mouseover="tooltip = true" 
                                                x-on:mouseleave="tooltip = false"
                                            >
                                                <span class='tooltip rounded shadow-lg bg-gray-100 p-3 text-sm font-sans -mt-8 text-left max-w-md'>
                                                    <?php 
        esc_html_e( 'Select this option if you want to use a fixed date instead of a number of days in the past. If you select this option, only the posts older than the selected date will be deleted. If this option is not selected, the post older than the number of days in the past will be deleted, which will always be relative to today.', 'delete-old-posts' );
        ?>
                                                </span>
                                            </span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <div class="lg:w-1/2 text-center py-8">
                                <div class="m-2">
                                    <input 
                                        type="checkbox"
                                        value="1"
                                        name="deloldp-post-redirect"
                                        id="redirect"
                                        <?php 
        if ( is_object( $getOptionObject ) && property_exists( $getOptionObject, 'params' ) ) {
            if ( $getOptionObject->params->deloldpRedirect == 1 ) {
                echo ' checked';
            }
        }
        ?>
                                    />
                                    <label for="redirect" class="">
                                        <?php 
        esc_html_e( "Redirect the URL of the deleted post to a similar post when requested.", 'delete-old-posts' );
        ?> 
                                        <span 
                                            class="dashicons dashicons-editor-help has-tooltip"
                                            x-on:mouseover="tooltip = true" 
                                            x-on:mouseleave="tooltip = false"
                                        >
                                            <span class='tooltip rounded shadow-lg bg-gray-100 p-3 text-sm font-sans -mt-8 max-w-md right-3 text-left'>
                                                <?php 
        esc_html_e( 'Even after the posts are removed, they can still be linked to or found on Google. Choose this option if you want to redirect your visitors to the deleted post to another similar post on your website. The plugin will automatically find the best matching option, but you can customize it in the "Redirects" menu. The HTTP response status code 301 Moved Permanently is used for the redirect.', 'delete-old-posts' );
        ?>
                                            </span>
                                        </span>
                                    </label>
                                </div>
                                
                                <div class="border-r">
                                    <input 
                                        type="checkbox"
                                        value="1"
                                        @click="confirmSkipTrash()"
                                        name="deloldp-post-skiptrash"
                                        id="skiptrash"
                                        <?php 
        if ( $dop_fs->can_use_premium_code() ) {
            if ( is_object( $getOptionObject ) && property_exists( $getOptionObject, 'params' ) ) {
                if ( $getOptionObject->params->deloldpSkiptrash == 1 ) {
                    echo ' checked';
                }
            }
        } else {
            echo ' disabled';
        }
        ?>
                                    />
                                    <label for="skiptrash" class="text-red-600">
                                        <?php 
        esc_html_e( "Delete the Posts permanently, don't add them into Trash", 'delete-old-posts' );
        ?> <span class="dashicons dashicons-database-remove"></span>.
                                    </label>
                                    
                                    <div class="m-2">
                                        <?php 
        printf( __( 'Number of post to delete when the %s runs.', 'delete-old-posts' ), '<a href="https://developer.wordpress.org/plugins/cron/" target="_blank">WP-Cron</a>' );
        $numberOfPostsToDelete = 1;
        if ( $dop_fs->can_use_premium_code() ) {
            if ( is_object( $getOptionObject ) && property_exists( $getOptionObject, 'params' ) ) {
                if ( property_exists( $getOptionObject->params, 'deloldpPostsNr' ) ) {
                    $numberOfPostsToDelete = $getOptionObject->params->deloldpPostsNr;
                }
            }
        }
        ?>
                                        <select
                                            id="deloldp-post-number"
                                            name="deloldp-posts-number"
                                            <?php 
        if ( !$dop_fs->can_use_premium_code() ) {
            echo "disabled";
        }
        ?>
                                        >
                                            <?php 
        $postNrs = array(
            1,
            2,
            3,
            4,
            5,
            10,
            20,
            30,
            50,
            100
        );
        foreach ( $postNrs as $pnr ) {
            ?>
                                                <option value="<?php 
            echo $pnr;
            ?>" <?php 
            echo ( isset( $numberOfPostsToDelete ) && $numberOfPostsToDelete == $pnr ? 'selected="selected"' : '' );
            ?>><?php 
            echo $pnr;
            ?></option>
                                                <?php 
        }
        ?>
                                        </select>
                                        <span 
                                            class="dashicons dashicons-editor-help has-tooltip"
                                            x-on:mouseover="tooltip = true" 
                                            x-on:mouseleave="tooltip = false"
                                        >
                                            <span class='tooltip rounded shadow-lg bg-gray-100 p-3 text-sm font-sans -mt-8 max-w-md right-3 text-left'>
                                                <?php 
        esc_html_e( 'Choose the number of post to be deleted at the same time. Please note, that a higher number of post deleted at once means a higher load on your server. You can choose a smaller number on a hosting with low resources (ex. shared hosting) and a higher number if your website is running for example on a dedicated server. By default, one post is deleted.', 'delete-old-posts' );
        ?>
                                            </span>
                                        </span>
                                    </div>

                                    <?php 
        if ( !$dop_fs->can_use_premium_code() ) {
            echo '
                                        <section>' . esc_html__( 'These options are available in the Professional version.', 'delete-old-posts' );
            echo '
                                            <a href="' . $dop_fs->get_upgrade_url() . '">' . '<u>' . esc_html__( 'Upgrade Now!', 'delete-old-posts' ) . '</u>' . '</a>';
            echo '
                                        </section>';
        }
        ?>

                                    <section class="mx-8 mt-8 text-slate-500">
                                        <?php 
        esc_html_e( "Filter the deleted post by selecting more options in the filters menu. Available filters are custom post types, categories, taxonomies, users, favorite post, and text search (posts containing a text), so only some from the posts will be deleted even if they are older than selected date (selected days).", "delete-old-posts" );
        ?>
                                    </section>

                                </div>
                            </div>
                        </div>
                        <div class="mt-3 mb-3 w-full flex items-center">
                            <div class="text-right w-1/2">
                                <div>
                                    <!-- Rounded switch -->
                                    <?php 
        $toggledelete = false;
        if ( is_object( $getOptionObject ) && property_exists( $getOptionObject, 'params' ) && property_exists( $getOptionObject->params, 'toggledelete' ) ) {
            if ( $getOptionObject->params->toggledelete == 1 ) {
                $toggledelete = true;
            }
        }
        if ( !$toggledelete ) {
            esc_html_e( "Start", "delete-old-posts" );
        } else {
            esc_html_e( "Stop", "delete-old-posts" );
        }
        echo "&nbsp;";
        esc_html_e( "deleting the posts:", "delete-old-posts" );
        ?>
                                    <label class="switch ml-2">
                                        <input
                                            type="checkbox"
                                            id="toggledelete"
                                            name="toggledelete"
                                            value="1" 
                                            <?php 
        if ( $toggledelete ) {
            echo "checked";
        }
        ?>
                                        >
                                        <span class="slider round"></span>
                                    </label>
                                    <!-- Help tooltip -->
                                    <span 
                                        class="dashicons dashicons-editor-help has-tooltip"
                                        x-on:mouseover="tooltip = true" 
                                        x-on:mouseleave="tooltip = false"
                                    >
                                        <span class='tooltip rounded shadow-lg bg-gray-100 p-3 text-sm text-left font-sans max-w-md'>
                                            <?php 
        esc_html_e( 'Start/ Stop deleting posts. Select this option and click save to start/ stop deleting the posts automatically in background. Hint! You can first test the results when you save the filters.', 'delete-old-posts' );
        ?>
                                        </span>
                                    </span>
                                </div>
                            </div>
                            <div class="text-left w-1/2 items-right">
                                <button 
                                    type="submit" 
                                    class="bg-blue-500 rounded-full font-bold text-white px-4 py-3 ml-5 inline-block transition duration-300 ease-in-out hover:bg-blue-600"
                                >
                                    <?php 
        esc_html_e( 'Save', 'delete-old-posts' );
        ?>
                                    <svg xmlns="http://www.w3.org/2000/svg" class="inline ml-2 w-6 stroke-current text-white stroke-2" viewBox="0 0 24 24" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                        <line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/>
                                    </svg>
                                </button>
                                <?php 
        wp_nonce_field( 'start-delete-old-posts', 'secured-delete-old-posts' );
        ?>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
            
            <?php 
        /**
         * get post deletion log
         */
        $post_delete_log = array();
        if ( get_option( 'post_delete_log_array' ) !== false ) {
            $post_delete_log = get_option( 'post_delete_log_array' );
        }
        ?>

            <div class="flex-grow container mx-auto sm:px-4 pt-1 pb-1" x-data="{}">
                <div class="bg-white border-t border-b sm:border-l sm:border-r sm:rounded shadow mb-6">
                    <div class="border-b px-6">
                        <div class="flex justify-between -mb-px">
                            <div class=" text-blue-dark py-4 text-lg">
                                <?php 
        esc_html_e( 'The latest deleted post:', 'delete-old-posts' );
        ?>
                            </div>
                            <div class="flex text-sm">
                                <div class="py-4 text-grey-dark border-b border-transparent hover:border-grey-dark mr-3">
                                    <span class="dashicons dashicons-nametag"></span>  
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="text-gray-400 grid auto-cols-auto grid-flow-col gap-3">
                        <div class="tblrow">
                            <?php 
        esc_html_e( 'Post published date:', 'delete-old-posts' );
        if ( isset( $post_delete_log['deleted_post_timestamp'] ) ) {
            echo "&nbsp;" . date( "d M Y h:i:s A", $post_delete_log['deleted_post_timestamp'] );
        }
        ?>
                            <br />
                            <?php 
        esc_html_e( 'Id:', 'delete-old-posts' );
        echo "&nbsp;";
        if ( isset( $post_delete_log['deleted_post_id'] ) ) {
            echo $post_delete_log['deleted_post_id'];
        }
        ?>
                        </div>
                        <div class="tblrow">Title: <?php 
        if ( isset( $post_delete_log['deleted_post_title'] ) ) {
            echo $post_delete_log['deleted_post_title'];
        }
        ?></div>
                    </div>
                </div>
            </div>
        </section>

        <?php 
    }

    /**
     * add custom cron interval
     */
    function deloldp_add_cron_interval( $schedules ) {
        $schedules['fifteen_seconds'] = array(
            'interval' => 15,
            'display'  => esc_html__( 'Every Fifteen Seconds' ),
        );
        return $schedules;
    }

    /**
     * function to delete the cron job
     */
    function deloldp_deactivate() {
        // delete plugin data
        delete_option( 'deloldp-post-days-option' );
        // error_log('plugin deactivated');
        $timestamp = wp_next_scheduled( 'deloldp_cron_delete_old_posts' );
        wp_unschedule_event( $timestamp, 'deloldp_cron_delete_old_posts' );
    }

    /**
     * function exec. when cron run
     */
    function deloldp_cron_exec() {
        // error_log('cron running...');
        /**
         * check if user selected the option to start deleting the posts
         */
        $startDeletingPosts = false;
        // get user saved options
        $getOptionObject = get_option( 'deloldp-post-days-option' );
        // check if start delete posts option is on
        if ( is_object( $getOptionObject ) && property_exists( $getOptionObject, 'params' ) && property_exists( $getOptionObject->params, 'toggledelete' ) ) {
            if ( $getOptionObject->params->toggledelete == 1 ) {
                $startDeletingPosts = true;
            }
        }
        // get the posts to delete
        if ( $startDeletingPosts ) {
            $nrOfPostsToGet = $this->getNumberOfPosts();
            $altPostsArray = $this->getAltestPostsObj( $nrOfPostsToGet );
        }
        /**
         * delete the altest post
         * @return NULL | array
         */
        if ( isset( $altPostsArray ) && is_array( $altPostsArray ) ) {
            $this->deletePosts( $altPostsArray, $getOptionObject );
        }
    }

    /**
     * get the posts list
     * @param $nrOfPostsToGet - the number of posts to retrieve
     * @return post object
     */
    function getAltestPostObj( $nrOfPostsToGet = 1 ) {
        $post_list = array();
        // set sql query vars
        $postTags = $this->formatSQLParam( 'postTags' );
        $postTypes = $this->formatSQLParam( 'postType' );
        $postCat = $this->formatSQLParam( 'postCategory' );
        $postAuthor = $this->formatSQLParam( 'postAuthor' );
        $relation = $this->formatSQLParam( 'relation' );
        $postids = $this->formatSQLParam( 'postids' );
        $skeywords = $this->formatSQLParam( 'search_keywords' );
        $postStatus = $this->formatSQLParam( 'post_status' );
        // create tax_query array
        $tax_query = array();
        $tax_query['relation'] = $relation;
        if ( is_array( $postCat ) ) {
            foreach ( $postCat as $cat ) {
                $tax_query[] = array(
                    'taxonomy' => 'category',
                    'field'    => 'term_id',
                    'terms'    => $cat,
                );
            }
        }
        if ( is_array( $postTags ) ) {
            foreach ( $postTags as $tag ) {
                $tax_query[] = array(
                    'taxonomy' => 'post_tag',
                    'field'    => 'term_id',
                    'terms'    => $tag,
                );
            }
        }
        // get the altest post
        $posts_arg = array(
            'post_type'      => $postTypes,
            'post_status'    => $postStatus,
            'orderby'        => 'date',
            'order'          => 'ASC',
            'posts_per_page' => $nrOfPostsToGet,
            'include'        => array(),
            'exclude'        => $postids,
            'author'         => $postAuthor,
            'meta_key'       => '',
            'meta_value'     => '',
            's'              => $skeywords,
            'tax_query'      => $tax_query,
        );
        if ( is_array( $postTypes ) ) {
            $post_list = get_posts( $posts_arg );
        }
        return $post_list;
    }

    /**
     * get the custom posts list
     * @param $nrOfPostsToGet - the number of posts to retrieve
     * @return post object
     */
    function getAltestCustomPostObj( $nrOfPostsToGet = 1 ) {
        $custom_post_list = array();
        // set sql query vars
        $customPostTypes = $this->formatSQLParam( 'customPostTypes' );
        // create a request for each specified cpt
        $custom_post_list = array();
        if ( is_array( $customPostTypes ) ) {
            foreach ( $customPostTypes as $cpt ) {
                // get the tax_query
                $postTax = $this->formatSQLParam( 'postTax', $cpt );
                $postAuthor = $this->formatSQLParam( 'postAuthor' );
                $postids = $this->formatSQLParam( 'postids' );
                $skeywords = $this->formatSQLParam( 'search_keywords' );
                $postStatus = $this->formatSQLParam( 'post_status' );
                // get the altest post
                $custom_post_arr = get_posts( array(
                    'post_type'      => $cpt,
                    'post_status'    => $postStatus,
                    'orderby'        => 'date',
                    'order'          => 'ASC',
                    'posts_per_page' => $nrOfPostsToGet,
                    'include'        => array(),
                    'exclude'        => $postids,
                    'author'         => $postAuthor,
                    'meta_key'       => '',
                    'meta_value'     => '',
                    's'              => $skeywords,
                    'tax_query'      => $postTax,
                ) );
                if ( is_array( $custom_post_arr ) ) {
                    foreach ( $custom_post_arr as $postsObject ) {
                        $custom_post_list[] = $postsObject;
                    }
                }
            }
        }
        return $custom_post_list;
    }

    /**
     * make user choosed days in a timestamp
     */
    function userDaysToTimestamp() {
        $getOptionObject = get_option( 'deloldp-post-days-option' );
        // check if fixed date selected
        $fixDate = false;
        if ( is_object( $getOptionObject ) && property_exists( $getOptionObject, 'params' ) ) {
            if ( isset( $getOptionObject->params->deloldpFixDate ) && $getOptionObject->params->deloldpFixDate != '' ) {
                $fixDate = true;
            }
        }
        switch ( $fixDate ) {
            case true:
                // get the number of days between two dates
                $fixDateTimestamp = strtotime( $getOptionObject->params->deloldpFixDate );
                $datediff = time() - $fixDateTimestamp;
                $deleteSavedDays = absint( round( $datediff / (60 * 60 * 24) ) );
                break;
            case false:
                if ( is_object( $getOptionObject ) && property_exists( $getOptionObject, 'params' ) ) {
                    if ( property_exists( $getOptionObject->params, 'deloldpDays' ) ) {
                        $deleteSavedDays = absint( $getOptionObject->params->deloldpDays );
                    }
                }
                break;
        }
        if ( isset( $deleteSavedDays ) && is_int( $deleteSavedDays ) ) {
            $post2DelTimestamp = strtotime( "-" . $deleteSavedDays . ' days' );
            /** 
             * get the timestamp of the date at 23:59:59
             * all post published untill this date and time will be deleted
             */
            $post2DelTimestamp00 = mktime(
                23,
                59,
                59,
                date( 'm', $post2DelTimestamp ),
                date( 'd', $post2DelTimestamp ),
                date( 'Y', $post2DelTimestamp )
            );
            return $post2DelTimestamp00;
        }
        return false;
    }

    /**
     * save options - save plugin data
     */
    function saveOption( $optionValue = array() ) {
        $myOptions = new \stdClass();
        $myOptions->name = 'deloldp-post-days-option';
        $myOptions->params = (object) $this->sanitize_text_or_array_field( $optionValue );
        /**
         * save the plugin options
         */
        return update_option( 'deloldp-post-days-option', $myOptions );
    }

    /**
     * Recursive sanitation for text or array
     * 
     * @param $array_or_string (array|string)
     * @since  0.1
     * @return mixed
     */
    function sanitize_text_or_array_field( $array_or_string ) {
        if ( is_string( $array_or_string ) ) {
            $array_or_string = sanitize_text_field( $array_or_string );
        } elseif ( is_array( $array_or_string ) ) {
            foreach ( $array_or_string as $key => &$value ) {
                if ( is_array( $value ) ) {
                    $value = $this->sanitize_text_or_array_field( $value );
                } else {
                    $value = sanitize_text_field( $value );
                }
            }
        }
        return $array_or_string;
    }

    /**
     * Make some actions when the plugin is removed
     */
    function deloldp_deleted() {
        return true;
    }

    /**
     * Save deleted posts data in an option and use it to rediect traffic
     * 
     * @param $deletedPostData (array)
     */
    function saveToRedirectOpt( $deletedPostData ) {
        // check if option to redirect the deleted posts is checked
        $deloldpRedirect_opt = get_option( 'deloldp-post-days-option' );
        if ( is_object( $deloldpRedirect_opt ) && property_exists( $deloldpRedirect_opt->params, 'deloldpRedirect' ) ) {
            if ( $deloldpRedirect_opt->params->deloldpRedirect == 1 ) {
                // option checked - save the redirect
                if ( is_object( $deletedPostData ) ) {
                    // update deletedpostredirectsopt
                    $deletedpostredirectsopt = get_option( 'deletedpostredirectsopt' );
                    if ( !is_array( $deletedpostredirectsopt ) ) {
                        $deletedpostredirectsopt = array();
                    }
                    $deletedpostredirectsopt[] = ( isset( $deletedPostData->post_name ) ? $deletedPostData->post_name : '' );
                    // update the option
                    array_unique( $deletedpostredirectsopt );
                    update_option( 'deletedpostredirectsopt', $deletedpostredirectsopt );
                }
            }
        }
    }

    /**
     * Create help tooltips
     */
    function generateHelpTooltip( $helpTxt, $tooltipClass = '', $tooltipTxtClass = '' ) {
        ?>
        <span 
            class="dashicons dashicons-editor-help has-tooltip <?php 
        echo $tooltipClass;
        ?>"
            x-on:mouseover="tooltip = true" 
            x-on:mouseleave="tooltip = false"
        >
            <span class='tooltip rounded shadow-lg bg-gray-100 p-3 text-sm font-sans -mt-8 max-w-md <?php 
        echo $tooltipTxtClass;
        ?>'>
                <?php 
        echo $helpTxt;
        ?>
            </span>
        </span>
        <?php 
    }

    /**
     * format the SQL parameters used to fetch the posts to be deleted
     * @param $what -> define what parameter to return
     * @return array | string
     */
    function formatSQLParam( $what = '', $cpt = '' ) {
        // get filters options saved values
        $delop_filters = get_option( 'delop_filters' );
        switch ( $what ) {
            case 'postType':
                // default post type = post
                $postTypes = array('post');
                // check if only custom post types selected
                if ( is_array( $delop_filters ) && isset( $delop_filters['cpt'] ) && is_array( $delop_filters['cpt'] ) && !in_array( 'post', $delop_filters['cpt'] ) ) {
                    $postTypes = 'skip';
                }
                return $postTypes;
            case 'customPostTypes':
                $customPostTypes = 'skip';
                // if user selected the post types just return the cpt array
                if ( is_array( $delop_filters ) && isset( $delop_filters['cpt'] ) ) {
                    $customPostTypes = $delop_filters['cpt'];
                    // delete post in cpt array
                    if ( ($post_val_key = array_search( 'post', $customPostTypes )) !== false ) {
                        unset($customPostTypes[$post_val_key]);
                    }
                    // check if any post left in the cpt array
                    if ( count( $customPostTypes ) == 0 ) {
                        $customPostTypes = 'skip';
                    }
                    return $customPostTypes;
                }
            case 'post_status':
                $post_status = array();
                if ( is_array( $delop_filters ) && isset( $delop_filters['cpt_type'] ) && is_array( $delop_filters['cpt_type'] ) ) {
                    $post_status = $delop_filters['cpt_type'];
                }
                return $post_status;
            case 'postCategory':
                // default val. for category = 0 -> no category
                $postCat = 0;
                if ( is_array( $delop_filters ) && isset( $delop_filters['post_category'] ) && is_array( $delop_filters['post_category'] ) ) {
                    // $postCat = implode( ',', filter_var_array($delop_filters['post_category']) );
                    $postCat = filter_var_array( $delop_filters['post_category'] );
                }
                return $postCat;
            case 'postAuthor':
                $postAuthor = 0;
                if ( is_array( $delop_filters ) && isset( $delop_filters['userid'] ) && is_array( $delop_filters['userid'] ) ) {
                    $postAuthor = implode( ',', filter_var_array( $delop_filters['userid'] ) );
                }
                return $postAuthor;
            case 'postTax':
                $postTax = array();
                if ( isset( $cpt ) && is_string( $cpt ) && $cpt != '' ) {
                    // check if cpt specified - needed to check if the taxonomies are registered for (belongs to) the cpt
                    if ( is_array( $delop_filters ) && isset( $delop_filters['tax_input'] ) && is_array( $delop_filters['tax_input'] ) ) {
                        // format the taxonomy array like this
                        // array(
                        //     'relation' => 'AND',
                        //     array(
                        //         'taxonomy'   => 'services', // you can change it according to your taxonomy
                        //         'field'      => 'term_id', // this can be 'term_id', 'slug' & 'name'
                        //         'terms'      => $service->term_id,
                        //     )
                        // )
                        $relation = 'AND';
                        if ( is_array( $delop_filters ) && isset( $delop_filters['relation'] ) ) {
                            $relation = sanitize_text_field( $delop_filters['relation'] );
                        }
                        $postTax['relation'] = $relation;
                        // use relation when are more terms (AND | OR)
                        $get_obj_taxonomies = get_object_taxonomies( $cpt );
                        foreach ( $delop_filters['tax_input'] as $key => $tax_input ) {
                            if ( is_array( $tax_input ) && in_array( $key, $get_obj_taxonomies ) ) {
                                if ( is_array( $tax_input ) ) {
                                    foreach ( $tax_input as $term ) {
                                        $postTax[] = array(
                                            'taxonomy' => $key,
                                            'field'    => 'term_id',
                                            'terms'    => $term,
                                        );
                                    }
                                }
                            }
                        }
                    }
                }
                return $postTax;
            case 'relation':
                // get the selected relation for tax_query
                $relation = 'AND';
                if ( is_array( $delop_filters ) && isset( $delop_filters['relation'] ) ) {
                    $relation = sanitize_text_field( $delop_filters['relation'] );
                }
                return $relation;
            case 'postids':
                $postids = array();
                if ( is_array( $delop_filters ) && isset( $delop_filters['postids'] ) ) {
                    $postids = array_map( 'trim', explode( ',', sanitize_text_field( $delop_filters['postids'] ) ) );
                }
                return $postids;
            case 'search_keywords':
                $search_keywords = '';
                if ( is_array( $delop_filters ) ) {
                    if ( isset( $delop_filters['search_keyword'] ) && $delop_filters['search_keyword'] != '' ) {
                        $search_keywords = sanitize_text_field( $delop_filters['search_keyword'] );
                        // check if negative keywords exists
                        if ( isset( $delop_filters['search_keyword_negativ'] ) && $delop_filters['search_keyword_negativ'] != '' ) {
                            $search_keywords = $search_keywords . ' -' . sanitize_text_field( $delop_filters['search_keyword_negativ'] );
                        }
                    }
                }
                return $search_keywords;
            case 'postTags':
                $postTags = 0;
                if ( is_array( $delop_filters ) && isset( $delop_filters['tax_input']['post_tag'] ) && is_array( $delop_filters['tax_input']['post_tag'] ) ) {
                    $postTags = filter_var_array( $delop_filters['tax_input']['post_tag'] );
                }
                return $postTags;
        }
        return '';
    }

    /**
     * get the old posts to delete array
     */
    function getAltestPostsObj( $nrOfPostsToGet ) {
        $altPostsFinalArray = array();
        // get the default posts list
        $altPostsArray = $this->getAltestPostObj( $nrOfPostsToGet );
        // get the custom posts list
        $altCustomPostsArray = $this->getAltestCustomPostObj( $nrOfPostsToGet );
        // combine the lists toghether
        if ( is_array( $altPostsArray ) ) {
            foreach ( $altPostsArray as $oldPost ) {
                $altPostsFinalArray[] = $oldPost;
            }
        }
        if ( is_array( $altCustomPostsArray ) ) {
            foreach ( $altCustomPostsArray as $oldCustomPost ) {
                $altPostsFinalArray[] = $oldCustomPost;
            }
        }
        // get user saved options
        $getOptionObject = get_option( 'deloldp-post-days-option' );
        // get the list of deleted posts without deleting them
        $postsDeleted = $this->deletePosts( $altPostsFinalArray, $getOptionObject, false );
        if ( !is_array( $postsDeleted ) ) {
            $postsDeleted = array();
        }
        // if NULL returned
        return $postsDeleted;
    }

    /**
     * retrieve the number of post to delete
     */
    function getNumberOfPosts() {
        global $dop_fs;
        $nrOfPostsToGet = 1;
        if ( $dop_fs->can_use_premium_code() ) {
            $getOptionObject = get_option( 'deloldp-post-days-option' );
            if ( is_object( $getOptionObject ) && property_exists( $getOptionObject, 'params' ) ) {
                if ( property_exists( $getOptionObject->params, 'deloldpPostsNr' ) ) {
                    $nrOfPostsToGet = $getOptionObject->params->deloldpPostsNr;
                }
            }
        }
        return $nrOfPostsToGet;
    }

    /**
     * log var_dump errors
     * you can only echo the results you have to capture the output buffer with ob_start(), assign it to a variable, 
     * and then clean the buffer with ob_end_clean() allowing you to write the resulting variable to the error_log
     */
    function var_error_log( $object = null ) {
        ob_start();
        // start buffer capture
        var_dump( $object );
        // dump the values
        $contents = ob_get_contents();
        // put the buffer into a variable
        ob_end_clean();
        // end capture
        error_log( $contents );
        // log contents of the result of var_dump( $object )
    }

    /**
     * delete the posts
     * @param  array $altPostsArray     = array of posts selected from DB
     * @param object $getOptionObject   = saved plugin options
     * @param true|false $action        -> true = delete the posts | false = test (return the list of posts to delete)
     */
    function deletePosts( $altPostsArray, $getOptionObject, $action = true ) {
        global $dop_fs;
        // get the user delete days
        $postDeleteDaysTime = $this->userDaysToTimestamp();
        /** check if number of days was set */
        if ( !$postDeleteDaysTime ) {
            return;
        }
        // posts nr. of days to be deleted not defined - exit
        // error_log("Time: " . date("d M Y", $postDeleteDaysTime));
        if ( isset( $altPostsArray ) && is_array( $altPostsArray ) ) {
            $i = 0;
            $deletedPostsArray = array();
            // get saved attached_img option
            $delop_filters = get_option( 'delop_filters' );
            $attached_img_opt = ( is_array( $delop_filters ) && isset( $delop_filters['attached_img'] ) ? $delop_filters['attached_img'] : '' );
            foreach ( $altPostsArray as $altPostDataObj ) {
                if ( is_object( $altPostDataObj ) ) {
                    $altPostTimestamp = strtotime( $altPostDataObj->post_date );
                    if ( $postDeleteDaysTime > $altPostTimestamp ) {
                        // save the last entry for log
                        if ( $i == count( $altPostsArray ) - 1 ) {
                            if ( $action ) {
                                update_option( 'post_delete_log_array', array(
                                    'deleted_post_id'        => $altPostDataObj->ID,
                                    'deleted_post_timestamp' => $altPostTimestamp,
                                    'deleted_post_title'     => $altPostDataObj->post_title,
                                    'deleted_post_name'      => $altPostDataObj->post_name,
                                    'deleted_post_url'       => $altPostDataObj->guid,
                                ) );
                            }
                        }
                        $i++;
                        // ======== delete post =========
                        $deletePostSkipTrash = false;
                        if ( $dop_fs->can_use_premium_code() ) {
                            // check if skipTrash is set
                            $skipTrashOption = 0;
                            if ( is_object( $getOptionObject ) && property_exists( $getOptionObject, 'params' ) ) {
                                if ( $getOptionObject->params->deloldpSkiptrash ) {
                                    $skipTrashOption = $getOptionObject->params->deloldpSkiptrash;
                                }
                            }
                            if ( $skipTrashOption == 1 ) {
                                $deletePostSkipTrash = true;
                            }
                        }
                        // delete the post
                        if ( isset( $altPostDataObj->ID ) ) {
                            if ( $action ) {
                                // delete the post
                                if ( $deletePostSkipTrash ) {
                                    $deletePostResult = wp_delete_post( (int) $altPostDataObj->ID, $deletePostSkipTrash );
                                } else {
                                    $deletePostResult = wp_trash_post( (int) $altPostDataObj->ID );
                                }
                                //cpt are not trashed with wp_delete_post
                            } else {
                                /**
                                 * test -> create the array of deleted posts to return without actually deleting them
                                 */
                                $deletedPostsArray[] = $altPostDataObj;
                            }
                        }
                        /**
                         * Save deleted post data in an option to redirect it later to a similar post if old post URL called
                         */
                        if ( $action ) {
                            $this->saveToRedirectOpt( $altPostDataObj );
                        }
                    }
                }
            }
            if ( !$action ) {
                // return the array with posts that normally would have been deleted
                return $deletedPostsArray;
            }
        }
    }

    /**
     * Return the Filter's form Post Vars.
     */
    function getFiltersOpt( $whatToReturn = '', $nestedarray = '' ) {
        // delete_option('delop_filters');
        // get filters options saved values
        $delop_filters = get_option( 'delop_filters' );
        if ( isset( $delop_filters[trim( $whatToReturn )] ) & $nestedarray == '' ) {
            return $delop_filters[trim( $whatToReturn )];
        }
        if ( isset( $delop_filters[trim( $whatToReturn )][trim( $nestedarray )] ) & $nestedarray != '' ) {
            return $delop_filters[trim( $whatToReturn )][trim( $nestedarray )];
        }
        // if the options are empty return the default post type
        if ( empty( $delop_filters ) && $whatToReturn == 'cpt' ) {
            return array('post');
        }
        // no data saved for the request
        return false;
    }

    /**
     * Check if we are on specific page
     */
    function delop_checkIfPage( $requestedPage ) {
        global $wp;
        /**
         * get the page url
         */
        $current_url = esc_url( home_url( add_query_arg( $_GET, $wp->request ) ) );
        /**
         * check if $_POST in the filter page
         */
        if ( preg_match( '/page=' . $requestedPage . '/i', $current_url ) ) {
            // we are on the page
            return true;
        }
        // not the requested page
        return false;
    }

}
