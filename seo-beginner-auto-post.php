<?php

/*
Plugin Name: SEOBeginner Auto Post
Plugin URI: https://www.seobeginner.com/seobeginner-auto-post-plugin/
Description: Use this plugin to insert post remote  - no need to login into WordPress dashboard to insert post.
Version: 2.1.3
Author: SEOBeginner
Author URI: https://www.seobeginner.com/
License: GPLv2 or later
Text Domain: seobeginner
*/

define('UPDATE_FILE', dirname(__FILE__) . '/sbap-auto-update.php');
if (file_exists(UPDATE_FILE)) {
    require_once(UPDATE_FILE);
}

define('BI_PLUGIN_URL', plugin_dir_url(__FILE__));
define('BI_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('BI_DELETE_LIMIT', 100000);
define('BI_KEY_TOOL_AUTO_POST', '_seobeginner_auto_post');
define('BI_UPDATE_OLD_LINKS', 'https://tools.seobeginner.com/hooks/ss/update-old-links');
define('BI_GET_TOOL_POST_BY_GUID_OR_ID', 'https://tools.seobeginner.com/api/guestpost/post-id-by-domain?domain=');

class BI_Insert
{
    public static function init()
    {

        $act = isset($_REQUEST['act']) ? sanitize_text_field($_REQUEST['act']) : '';
        $code = isset($_REQUEST['code']) ? sanitize_text_field($_REQUEST['code']) : '';

        if ($act == 'getcats') {

            $categoriess = get_categories(array(
                'orderby' => 'name',
                'hide_empty' => 0,
                // 'parent'  => 0
            ));
            $list = $cats = $tags = array();
            foreach ($categoriess as $key => $cat) {
                $temp = array();

                $ctemp['term_id'] = $cat->term_id;
                $ctemp['cat_name'] = $cat->cat_name;
                $ctemp['parent'] = $cat->parent;
                $list['cats'][] = $ctemp;
            }
            $posttags = get_tags();
            if ($posttags) {
                foreach ($posttags as $key => $tag) {
                    $ttemp = array();
                    $ttemp['term_id'] = $tag->term_id;
                    $ttemp['name'] = $tag->name;
                    $ttemp['slug'] = $tag->slug;
                    //$ttemp['parent'] = $tag->parent;
                    $list['tags'][] = $ttemp;
                }
            }
            global $_wp_additional_image_sizes;

            $image_sizes = array();
            $default_image_sizes = get_intermediate_image_sizes();

            foreach ($default_image_sizes as $size) {
                $image_sizes[$size]['width'] = intval(get_option("{$size}_size_w"));
                $image_sizes[$size]['height'] = intval(get_option("{$size}_size_h"));
                $image_sizes[$size]['crop'] = get_option("{$size}_crop") ? get_option("{$size}_crop") : false;
            }

            if (isset($_wp_additional_image_sizes) && count($_wp_additional_image_sizes)) {
                $image_sizes = array_merge($image_sizes, $_wp_additional_image_sizes);
            }
//            $list['sizes'] = $image_sizes;
            $list['pbn_info'] = get_info();

            header('Access-Control-Allow-Origin: *');
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($list, JSON_UNESCAPED_UNICODE);
            die();
        } else if ($act == 'insert' || $act == 'update' || $act == 'delete') {

            $api_code = get_option('api_remote_code', mt_rand(10, 20));

            if ($code != $api_code || empty($api_code)) {

                $check_code = array(
                    'success' => false,
                    'msg' => __('Code invalid', 'boxtheme'),
                    'code' => '002'
                );

                if (isset($_REQUEST['debug'])) {
                    $check_code['request_data'] = $_REQUEST;
                }

                echo json_encode($check_code);
                die();
            }

            self::insert($_REQUEST);
            die();
        } else if ($act == 'getsizes') {

            global $_wp_additional_image_sizes;
            $image_sizes = array();
            $default_image_sizes = get_intermediate_image_sizes();

            foreach ($default_image_sizes as $size) {
                $image_sizes[$size]['width'] = intval(get_option("{$size}_size_w"));
                $image_sizes[$size]['height'] = intval(get_option("{$size}_size_h"));
                $image_sizes[$size]['crop'] = get_option("{$size}_crop") ? get_option("{$size}_crop") : false;
            }

            if (isset($_wp_additional_image_sizes) && count($_wp_additional_image_sizes)) {
                $image_sizes = array_merge($image_sizes, $_wp_additional_image_sizes);
            }

            header('Access-Control-Allow-Origin: *');
            header('Content-Type: application/json; charset=utf-8');
            $respond = array('success' => true, 'data' => $image_sizes);
            echo json_encode($respond);
            die();
        }

//		add_action( 'wp_ajax_nopriv_bi_send', array( 'BI_Insert', 'bi_send' ) );
//		add_action( 'wp_ajax_bi_send', array( 'BI_Insert', 'bi_send' ) );

    }

    public static function bi_send()
    {
        header('Access-Control-Allow-Origin: *');
        header('Content-Type: application/json; charset=utf-8');

        $data = array();
        $request = $_REQUEST;
        $data = $request['data'];
        $code = isset($data['code']) ? $data['code'] : '';

        $respond = array(
            'success' => true,
            'msg' => __("Insert Post OK", 'boxtheme'),
            'data' => array(),
        );
        $data['post_status'] = 'publish';
        if (empty($data['post_title'])) {
            $check = array(
                'success' => false,
                'msg' => __('Fail - Post title is empty', 'boxtheme'),
                'code' => '001'
            );
            echo json_encode($check);
        }
        $api_code = get_option('api_remote_code', mt_rand(10, 20));

        if ($code != $api_code || empty($api_code)) {

            $check_code = array('success' => false, 'msg' => __('Code invalid', 'boxtheme'), 'code' => '002');
            if (isset($data['debug'])) {
                $check_code['request_data'] = $data;
            }
            echo json_encode($check_code);
            die();
        }

        $post = wp_insert_post($data);
        if (is_wp_error($post)) {
            $respond = array(
                'success' => false,
                'msg' => $post->get_error_message(),
                'data' => array(),
            );
        } else {
            $respond['data'] = $post;
            update_post_meta($post, 'insert_via_tool', 1);
            self::saveSeoInfo($post, $request);
        }

        echo json_encode($respond);
        die();
    }

    public static function insert($request)
    {
        header('Access-Control-Allow-Origin: *');
        header('Content-Type: application/json; charset=utf-8');

        $data = $_REQUEST;
        $code = isset($request['code']) ? sanitize_text_field($request['code']) : '';

        if ($data['act'] == 'delete') {
            wp_delete_post($data['ID'], true);
            $respond = array(
                'success' => true,
                'msg' => __('Delete Post Successfully.', 'boxtheme'),
                'pbn_info' => get_info()
            );
            echo json_encode($respond);
            die();
        }

        if (isset($data['seo_slug'])) {
            $data['post_name'] = sanitize_text_field($data['seo_slug']);
        }
        $respond = array(
            'success' => true,
            'data' => array(),
        );
        if (isset($data['debug'])) {
            $respond['request_data'] = $_REQUEST;
        }

        $data['post_status'] = 'publish'; // auto publish post;
        $data['post_author'] = 1; // auto assign author for admin.

        if (empty($data['post_title'])) {
            $check = array(
                'success' => false,
                'msg' => __('Fail - Post title is empty', 'boxtheme'),
                'code' => '001'
            );
            echo json_encode($check);
        }

        if ($_REQUEST['ID'] == '') {
            $post = wp_insert_post($data);
            update_post_meta($post, BI_KEY_TOOL_AUTO_POST, 1);
            $respond['action'] = 'insert';
            $respond['msg'] = __("Insert Post Successfully.", 'boxtheme');
            $respond['pbn_info'] = get_info();
        } else {
            wp_update_post($data);

            global $wpdb;
            $wpdb->update(
                $wpdb->prefix . 'posts',
                array(
                    'post_name' => $data['slug'],
                    'guid' => get_home_url() . '/' . $data['slug'],
                ),
                array('ID' => $_REQUEST['ID']),
                array('%s', '%s'),
                array('%d')
            );

            $post = $data['ID'];
            $respond['action'] = 'update';
            $respond['msg'] = __("Update Post Successfully.", 'boxtheme');
            $respond['pbn_info'] = get_info();
        }

        if (is_wp_error($post)) {
            $respond = array(
                'success' => false,
                'msg' => $post->get_error_message(),
                'data' => array(),

            );
        } else {

            $respond['data'] = get_post($post);
            update_post_meta($post, '_seobeginner_api_insert', 1);

            if (isset($data['featured_img'])) {
                $url_img = esc_url($data['featured_img']);
                bi_insert_img_from_url($url_img, $post);
            }

            self::saveSeoInfo($post, $data);
        }

        echo json_encode($respond);
        die();

    }

    /**
     * check the plugin and save all seo meta into database.
     */
    public static function saveSeoInfo($post_id, $request)
    {

        $plugin = $request['seo_plugin'];

        switch ($plugin) {
            case 'yoast-seo':
                self::saveSeoYoast($post_id, $request);
                break;
            case 'seo-ultimate':
                self::saveSeoUltimatet($post_id, $request);
                break;
            case 'aio-seo-pack':
                self::saveSeoAioPack($post_id, $request);
                break;
            case 'wp-meta-seo':
                self::saveSeoMeta($post_id, $request);
                break;
            default:
                # code...
                break;
        }

    }

    public static function saveSeoYoast($post_id, $request)
    {
        $prefix_ = "_yoast_wpseo_";
        $meta = array(
            '_yoast_wpseo_metadesc',
            '_yoast_wpseo_focuskw_text_input',
            '_yoast_wpseo_content_score',
            '_yoast_wpseo_primary_category',
            '_yoast_wpseo_focuskw',
            '_yoast_wpseo_linkdex'
        );

        if (isset($request['seo_des'])) {
            update_post_meta($post_id, '_yoast_wpseo_metadesc', sanitize_text_field($request['seo_des']));
        }
        if (isset($request['seo_title'])) {
            update_post_meta($post_id, '_yoast_wpseo_title', sanitize_text_field($request['seo_title']));
        }
        if (isset($request['seo_keyword'])) {
            update_post_meta($post_id, '_yoast_wpseo_focuskw', sanitize_text_field($request['seo_keyword']));
        }

    }

    public static function saveSeoAioPack($post_id, $request)
    {

        $meta = array('_aioseop_description', '_aioseop_title');

        if (isset($request['seo_des'])) {
            update_post_meta($post_id, '_aioseop_description', sanitize_text_field($request['seo_des']));
        }
        // if( isset( $request['seo_keyword']) ){
        // 	update_post_meta($post_id,'_yoast_wpseo_focuskw',  $request['seo_keyword'] );
        // }
        if (isset($request['seo_title'])) {
            update_post_meta($post_id, '_aioseop_title', sanitize_text_field($request['seo_title']));
        }

    }

    public static function saveSeoUltimatet($post_id, $request)
    {

        $meta = array('_su_title', '_su_description', '_su_meta_robots_noindex', '_su_meta_robots_nofollow');

        if (isset($request['seo_des'])) {
            update_post_meta($post_id, '_su_description', sanitize_text_field($request['seo_des']));
        }
        if (isset($request['seo_keyword'])) {
            update_post_meta($post_id, '_yoast_wpseo_focuskw', sanitize_text_field($request['seo_keyword']));
        }
        if (isset($request['seo_title'])) {
            update_post_meta($post_id, '_su_title', sanitize_text_field($request['seo_title']));
        }

    }

    public static function saveSeoMeta($post_id, $request)
    {
        $prefix_ = "_yoast_wpseo_";
        $meta = array(
            '_metaseo_metatitle',
            '_metaseo_metadesc',
            '_metaseo_metaopengraph-desc',
            '_metaseo_metaopengraph-image',
            '_metaseo_metatwitter-title',
            '_metaseo_metatwitter-desc',
            '_metaseo_metatwitter-image'
        );

        if (isset($request['seo_des'])) {
            update_post_meta($post_id, '_metaseo_metadesc', sanitize_text_field($request['seo_des']));
            update_post_meta($post_id, '_metaseo_metaopengraph-desc', sanitize_text_field($request['seo_des']));

        }
        if (isset($request['seo_keyword'])) {
            update_post_meta($post_id, '_yoast_wpseo_focuskw', sanitize_text_field($request['seo_keyword']));
        }
        if (isset($request['seo_title'])) {
            update_post_meta($post_id, '_metaseo_metatitle', sanitize_text_field($request['seo_title']));
        }

    }
    // public static function getCats(){
    // 	$cats = get_categories( array(
    // 	    'orderby' => 'name',
    // 	    'parent'  => 0
    // 	) );
    // 	$respond = array(
    // 		'success' => true,
    // 		'data' => $cats
    // 	);

    // 	$response = wp_remote_get( 'http://boxthemes.net/?act=getcats' );
    // 	$data = array();
    // 	try {
    //         // Note that we decode the body's response since it's the actual JSON feed
    //         $data = json_decode( $response['body'] );

    //     } catch ( Exception $ex ) {
    //         $json = null;
    //     } // end try/catch

    //    wp_send_json(array('success' => true, 'data' => $data) );

    // }
}

add_action('init', array('BI_Insert', 'init'));
add_action('wp_ajax_nopriv_bi_send', array('BI_Insert', 'bi_send'));
add_action('wp_ajax_bi_send', array('BI_Insert', 'bi_send'));


/**
 * Name: bi_api_remote_settings
 * Create form to display plugin settings
 */
function bi_api_remote_settings()
{
    if (!empty($_POST['api_remote_code'])) {
        update_option('api_remote_code', sanitize_text_field($_POST['api_remote_code']));
    }
    $api_remote_code = get_option('api_remote_code', true);
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }
    echo '<div class="wrap bi-import">'; ?>

    <form style=" padding: 10px 20px; min-height: 600px; border:1px solid #ccc; display: block;overflow: hidden;"
          class="frm-bi" method="POST">
        <div class="postbox-container">
            <div class="full">
                <label><?php _e('Set password for Insert Post API', 'boxtheme'); ?></label>
            </div>
            <div class="full">
                <input type="text" placeholder="<?php _e("Set your code here", 'boxtheme'); ?>" id="api_remote_code"
                       class="api_remote_code" name="api_remote_code"
                       value="<?php echo esc_attr($api_remote_code); ?>">
                <span class="button general-pw"><?php _e('Generate Password', 'boxtheme'); ?> </span>
            </div>
            <button type="submit"><?php _e('Save', 'boxtheme'); ?></button>
        </div>
    </form>
    <style type="text/css">
        .frm-bi input {
            display: inline-block;
            clear: both;
            margin-bottom: 15px;
            height: 39px;
        }

        .frm-bi button {
            min-width: 120px;
            padding: 8px 10px;
            text-align: right;
            float: right;
            border-radius: 5px;
            border: 0;
            color: #fff;
            background-color: #048269;
            cursor: pointer;
            text-align: center;
            height: 39px;
        }

        .full {
            width: 100%;
            float: left;
            clear: both;
            padding-bottom: 15px;
            margin: 0 auto;
        }

        .full input {
            width: 60%;
        }

        .frm-bi .full span {
            width: auto;
            float: right;
            height: 39px;
            line-height: 35px;
        }
    </style>
    <script type="text/javascript">
        (function ($) {
            $(document).ready(function () {
                $(".general-pw").click(function (e) {
                    var view = this;
                    $target = $(e.currentTarget);

                    var pData = {};
                    $target.find('input,textarea,select').each(function () {
                        var $this = $(this);
                        pData[$this.attr('name')] = $this.val();
                    });

                    var param = {
                        url: '<?php echo admin_url('admin-ajax.php');?>',
                        type: 'POST',
                        data: {
                            'action': 'bi_general_pw',
                        },
                        //contentType	: 'application/x-www-form-urlencoded;charset=UTF-8',
                        beforeSend: function () {
                            console.log('beforeSend');
                            //view.blockUi.block( $target );
                        },
                        success: function (resp) {
                            if (resp.success) {
                                $("#api_remote_code").val(resp.pw);
                            }
                        },
                        complete: function () {
                            //view.blockUi.unblock();
                        }
                    };
                    $.ajax(param);
                    return false;
                })
            })
        })(jQuery);
    </script>
    <?php

    echo '</div>';

}

/**
 * Name: bi_seometa_settings_init
 * Add menu item to wp admin menu
 */
function bi_seometa_settings_init()
{
    add_submenu_page('tools.php', __('API Insert Post Remote', 'all-in-one-seo-pack'), __('API Insert Post Remote', 'boxthemes'), 'manage_options', 'bi_api_remote_settings', 'bi_api_remote_settings');
}

add_action('admin_menu', 'bi_seometa_settings_init');

/**
 * Name: bi_generate_password
 * Generate password, is called via ajax
 */
function bi_generate_password()
{
    $size = rand(10, 20);
    $pass = wp_generate_password($size, true, true);
    wp_send_json(array('success' => true, 'pw' => $pass));
}

add_action('wp_ajax_bi_general_pw', 'bi_generate_password');


//add_filter( 'post_thumbnail_html', 'bi_thumbnail_external_replace', 10, 3 );

/**
 * @param $html
 * @param $post_id
 * @param $post_thumbnail_id
 *
 * Handle image (features)
 *
 * @return string
 */
function bi_thumbnail_external_replace($html, $post_id, $post_thumbnail_id)
{

    // if ( empty( $url ) || ! url_is_image( $url ) ) {
    //     return $html;
    // }
    if ($post_thumbnail_id) {
        return $html;
    }
    $url = get_post_meta($post_id, 'featured_img', true);

    if (!empty($url)) {
        $alt = get_post_field('post_title', $post_id) . ' ' . __('thumbnail', 'boxtheme');
        $attr = array('alt' => $alt);
        $attr = apply_filters('wp_get_attachment_image_attributes', $attr, null);
        $attr = array_map('esc_attr', $attr);
        $html = sprintf('<img src="%s"', esc_url($url));
        foreach ($attr as $name => $value) {
            $html .= " $name=" . '"' . $value . '"';
        }
        $html .= ' />';
    }

    return $html;
}

function bi_insert_img_from_url($url, $parent_post_id, $post_title = '')
{

    require_once(ABSPATH . 'wp-admin/includes/file.php');
    $timeout_seconds = 10;

    // Download file to temp dir
    $temp_file = download_url($url, $timeout_seconds);

    if (!is_wp_error($temp_file)) {

        // Array based on $_FILE as seen in PHP file uploads
        $file = array(
            'name' => basename($url), // ex: wp-header-logo.png
            'type' => 'image/png',
            'tmp_name' => $temp_file,
            'error' => 0,
            'size' => filesize($temp_file),
        );

        $overrides = array(
            // Tells WordPress to not look for the POST form
            // fields that would normally be present as
            // we downloaded the file from a remote server, so there
            // will be no form fields
            // Default is true
            'test_form' => false,

            // Setting this to false lets WordPress allow empty files, not recommended
            // Default is true
            'test_size' => true,
        );

        // Move the temporary file into the uploads directory
        $results = wp_handle_sideload($file, $overrides);
        //var_dump($results);
        if (!empty($results['error'])) {
            // Insert any error handling here
        } else {

            $filename = $results['file']; // Full path to the file
            $local_url = $results['url'];  // URL to the file in the uploads dir
            $type = $results['type']; // MIME type of the file
            $wp_upload_dir = wp_upload_dir();

            // Prepare an array of post data for the attachment.
            $attachment = array(
                'guid' => $filename,
                'post_mime_type' => $type,
                'post_title' => preg_replace('/\.[^.]+$/', '', basename($filename)),
                'post_content' => '',
                'post_status' => 'inherit'
            );

            // Insert the attachment.
            $attach_id = wp_insert_attachment($attachment, $filename, $parent_post_id);

            // Make sure that this file is included, as wp_generate_attachment_metadata() depends on it.
            require_once(ABSPATH . 'wp-admin/includes/image.php');

            // Generate the metadata for the attachment, and update the database record.
            $attach_data = wp_generate_attachment_metadata($attach_id, $filename);
            wp_update_attachment_metadata($attach_id, $attach_data);

            $thumbnail_id = set_post_thumbnail($parent_post_id, $attach_id);
            if (!$thumbnail_id) {
                set_own_post_thumbnail($parent_post_id, $attach_id);
            }
            // Perform any actions here based in the above results
        }

    }
}

function set_own_post_thumbnail( $post, $thumbnail_id ) {
    $post         = get_post( $post );
    $thumbnail_id = absint( $thumbnail_id );
    if ( $post && $thumbnail_id && get_post( $thumbnail_id ) ) {
        return update_post_meta( $post->ID, '_thumbnail_id', $thumbnail_id );
    }
    return false;
}

function bi_get_post_info_by_guid()
{
    header('Access-Control-Allow-Origin: *');
    header('Content-Type: application/json; charset=utf-8');
    global $wpdb;
    $guid = isset($_REQUEST['guid']) ? sanitize_text_field($_REQUEST['guid']) : '';

    $post_id = $wpdb->get_var($wpdb->prepare("SELECT ID FROM $wpdb->posts WHERE guid=%s", $guid));
    $post = get_post($post_id);
    echo json_encode(array(
        'ID' => $post->ID,
        'post_title' => $post->post_title,
        'post_status' => $post->post_status,
        'post_name' => $post->post_name,
    ));
    die();

}

add_action('wp_ajax_bi_get_post_info_by_guid', 'bi_get_post_info_by_guid');
add_action('wp_ajax_nopriv_bi_get_post_info_by_guid', 'bi_get_post_info_by_guid');

function remove_row_actions($actions, $post)
{
    $meta_auto_post = get_post_meta($post->ID, BI_KEY_TOOL_AUTO_POST);
    $meta_auto_post_value = $meta_auto_post[0];
    if ($post->post_type === 'post' && $meta_auto_post_value == '1') {
        unset($actions['edit']);
        unset($actions['trash']);
        unset($actions['inline hide-if-no-js']);
        echo '
		    <script>
                jQuery(document).ready(function () {
                    jQuery("#cb-select-' . $post->ID . '").prop("disabled", true);
                });
            </script>
		';
    }

    return $actions;
}

add_filter('post_row_actions', 'remove_row_actions', 10, 2);

function hide_publishing_actions()
{
    $post_type = 'post';
    global $post;
    $meta_auto_post = get_post_meta($post->ID, BI_KEY_TOOL_AUTO_POST);
    $meta_auto_post_value = $meta_auto_post[0];
    if ($post->post_type == $post_type && $meta_auto_post_value == '1') {
        echo '
                <style type="text/css">
                    #submitdiv {
                        display: none;
                    }
                </style>
            ';
    }
}

add_action('admin_head-post.php', 'hide_publishing_actions');
add_action('admin_head-post-new.php', 'hide_publishing_actions');

function sync_data_when_active()
{
    $data = url_get_contents(BI_GET_TOOL_POST_BY_GUID_OR_ID . get_home_url());
    $auto_post_data = json_decode($data);
    $auto_post_list = $auto_post_data->id_list;

    //Data return
    $error_type_id = array();
    $error_type_guid = array();
    $success_auto_post = array();

    if ($auto_post_list) {
        foreach ($auto_post_list as $item) {
            switch ($item->type) {
                case 'ID':
                    $auto_post = get_post($item->ID);
                    if (is_null($auto_post)) {
                        $error_type_id[] = array(
                            "ID" => $auto_post->ID,
                            "guid" => "",
                        );
                    } else {
                        add_post_meta($auto_post->ID, BI_KEY_TOOL_AUTO_POST, 1);
                        $success_auto_post[] = array(
                            "ID" => $auto_post->ID,
                            "guid" => get_the_guid($auto_post->ID),
                            "post_type" => "post",
                            "post_name" => $auto_post->post_name
                        );
                    }
                    break;
                case 'guid':
                    global $wpdb;
                    $post_id = $wpdb->get_var($wpdb->prepare("SELECT ID FROM $wpdb->posts WHERE guid=%s", $item->guid));
                    if ($post_id) {
                        $auto_post = get_post($post_id);
                        add_post_meta($auto_post->ID, BI_KEY_TOOL_AUTO_POST, 1);
                        $success_auto_post[] = array(
                            "ID" => $auto_post->ID,
                            "guid" => $item->guid
                        );
                    } else {
                        $error_type_guid[] = array(
                            "ID" => "",
                            "guid" => $item->guid,
                        );
                    }
                    break;
            }
        }
    }

    //Send post request to Tool-SeoBeginner system
    $data = array(
        'domain' => get_home_url(),
        'pbn_info' => get_info(),
        'success' => $success_auto_post,
        'error' => array_merge($error_type_guid, $error_type_id),
    );
    $data_string = json_encode($data);


    $args = array('headers' => array('Content-Type' => 'application/json'), 'body' => $data_string);
    $response = wp_remote_post(esc_url_raw(BI_UPDATE_OLD_LINKS), $args);
//	$response_code = wp_remote_retrieve_response_code( $response );
//	$response_body = wp_remote_retrieve_body( $response );

//	trigger_error( ob_get_contents(), E_USER_ERROR );
}

register_activation_hook(__FILE__, 'sync_data_when_active');


function get_info()
{

    if (!function_exists('get_plugin_data')) {
        require_once(ABSPATH . 'wp-admin/includes/plugin.php');
    }
    $plugin_data = get_plugin_data(__FILE__);
    $plugin_version = $plugin_data['Version'];

    $data = array(
        'plugin_version' => $plugin_version,
        'php_version' => phpversion(),
        'wp_version' => get_bloginfo('version')
    );

    return $data;
}

function get_pbn_info()
{
    $data = get_info();
    echo json_encode($data);
    die();
}

add_action('wp_ajax_get_pbn_info', 'get_pbn_info');
add_action('wp_ajax_nopriv_get_pbn_info', 'get_pbn_info');

function url_get_contents ($Url) {
    if (!function_exists('curl_init')){
        die('CURL is not installed!');
    }
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $Url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $output = curl_exec($ch);
    curl_close($ch);
    return $output;
}
