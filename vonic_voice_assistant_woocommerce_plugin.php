<?php
/**
 * Plugin Name: Vonic Voice Assistant for Woocommerce
 * Plugin URI: https://vonic.ai
 * Description: This plugin allows woocommerce stores to be navigated via voice commands
 * Version: 0.9.4.0
 * Author: Amitoj Cheema
 * Author URI: https://vonic.ai
 * License: GPLv2 or later
 **/

/*
* This program is free software; you can redistribute it and/or
* modify it under the terms of the GNU General Public License
* as published by the Free Software Foundation; either version 2
* of the License, or (at your option) any later version.
*
* This program is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
* GNU General Public License for more details.
*
* You should have received a copy of the GNU General Public License
* along with this program; if not, write to the Free Software
* Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
*
* Copyright 2020 DeepAudio
* */

 
// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
     die;
}


// variables for the field and option names 
$nonce_action_name = 'vonic_wc_submit_hidden';
$nonce_field_name = '_admin_email';
$admin_email_field = 'vonic_wc_domain_email';

$vonic_woocommerce_product_prefix_option_name = "vonic_woocommerce_product_prefix_option";
$vonic_woocommerce_is_activated_option_name = "vonic_woocommerce_is_activated_option";
$vonic_woocommerce_check_activation_state_event_name = "vonic_woocommerce_check_activation_state_event";
$vonic_woocommerce_schedule_catalogue_sync_event_name = "vonic_woocommerce_schedule_catalogue_sync_event";
$vonic_wc_admin_email_option_name = 'vonic_wc_admin_email';
$vonic_wc_admin_production_status_option_name = 'vonic_wc_production_status';
$vonic_woocommerce_domain_key = 'vonic_woocommerce_domain_key_name';
$vonic_woocommerce_visiblehash_key_name = 'vonic_woocommerce_visiblehash_key';
$vonic_woocommerce_initial_sync_completed_option_name = 'vonic_woocommerce_initial_sync_completed';

register_activation_hook( __FILE__, 'vonic_woocommerce_activate_plugin' );
register_deactivation_hook( __FILE__, 'vonic_woocommerce_deactivate_plugin' );
 
add_action( 'plugins_loaded', 'vonic_woocommerce_admin_settings' );
add_action( 'admin_menu', 'vonic_woocommerce_plugin_menu' );

add_action("wp_enqueue_scripts", "vonic_woocommerce_voice_enqueue_scripts");
add_action("wp_head", "vonic_woocommerce_voice_head");
add_action("admin_init", "vonic_woocommerce_setup_init_values");
add_action( $vonic_woocommerce_check_activation_state_event_name, 'vonic_woocommerce_check_activation_state' );
add_action( $vonic_woocommerce_schedule_catalogue_sync_event_name, 'vonic_woocommerce_sync_catalogue' );

$plugin = plugin_basename( __FILE__ );
add_filter( "plugin_action_links_$plugin", 'vonic_woocommerce_settings_link' );

/**
 * Show 'Settings' link on plugins list page
 *
 * @since 1.0.0
 */
function vonic_woocommerce_settings_link( $links ) {
    $settings_link = '<a href="options-general.php?page=vonic-plugin">Settings</a>'; 
    array_unshift($links, $settings_link); 
    return $links; 
}

/**
 * Activate and set default options
 *
 * @since 1.0.0
 */
function vonic_woocommerce_activate_plugin(){
    //Somehow global variables don't work in activate/deactivate function
    //Using local variables
    $vonic_woocommerce_is_activated_option_name = "vonic_woocommerce_is_activated_option";
    $vonic_woocommerce_domain_key = 'vonic_woocommerce_domain_key_name';
    $vonic_woocommerce_visiblehash_key_name = 'vonic_woocommerce_visiblehash_key';
    $vonic_woocommerce_initial_sync_completed_option_name = 'vonic_woocommerce_initial_sync_completed';
    $vonic_wc_admin_production_status_option_name = 'vonic_wc_production_status';

    $data = (object)[];
    $site_url = get_site_url();
    $data->site_url = $site_url;
    $server_name = $_SERVER['SERVER_NAME'];
    $data->domain = $server_name;
    if ( is_plugin_active( 'woocommerce/woocommerce.php' ) ){
        $data->woocommerce_found = true;
    }
    else{
        $data->woocommerce_found = false;
    }
    $response = wp_remote_post( 'https://vonic.app/wc_plugin_activated', array( 'body' => json_encode($data) ) );
    //echo "vonic_woocommerce_activate_plugin:".print_r($response)."<br/>";
    if ( is_wp_error( $response ) ) {
        update_option($vonic_woocommerce_domain_key,"error");
        update_option($vonic_woocommerce_is_activated_option_name,"error");
        return false;
    }

    $body = wp_remote_retrieve_body( $response );
    //echo "vonic_woocommerce_activate_plugin:".print_r($body)."<br/>";
    $token_array = preg_split("/:/", $body);
    update_option($vonic_woocommerce_domain_key,$token_array[0]);
    update_option($vonic_woocommerce_visiblehash_key_name,$token_array[1]);
    update_option($vonic_woocommerce_is_activated_option_name,"no");
    update_option($vonic_woocommerce_initial_sync_completed_option_name, "no");
    update_option( $vonic_wc_admin_production_status_option_name, "live" );
}

/**
 * Deactivate and clear options
 *
 * @since 1.0.0
 */
function vonic_woocommerce_deactivate_plugin(){
    //Somehow global variables don't work in activate/deactivate function
    //Using local variables
    $vonic_woocommerce_is_activated_option_name = "vonic_woocommerce_is_activated_option";
    $vonic_woocommerce_domain_key = 'vonic_woocommerce_domain_key_name';
    $vonic_woocommerce_check_activation_state_event_name = "vonic_woocommerce_check_activation_state_event";
    $vonic_woocommerce_schedule_catalogue_sync_event_name = "vonic_woocommerce_schedule_catalogue_sync_event";
    $vonic_wc_admin_email_option_name = 'vonic_wc_admin_email';
    $vonic_wc_admin_production_status_option_name = 'vonic_wc_production_status';
    $vonic_woocommerce_visiblehash_key_name = 'vonic_woocommerce_visiblehash_key';
    $vonic_woocommerce_initial_sync_completed_option_name = 'vonic_woocommerce_initial_sync_completed';

    delete_option($vonic_woocommerce_is_activated_option_name);
    delete_option($vonic_wc_admin_email_option_name);
    delete_option($vonic_wc_admin_production_status_option_name);
    delete_option($vonic_woocommerce_domain_key);
    delete_option($vonic_woocommerce_visiblehash_key_name);
    delete_option($vonic_woocommerce_initial_sync_completed_option_name);
    wp_clear_scheduled_hook( $vonic_woocommerce_check_activation_state_event_name );
    wp_clear_scheduled_hook( $vonic_woocommerce_schedule_catalogue_sync_event_name );
}

/**
 * Check if this domain is currently active
 *
 * @since 1.0.0
 */
function vonic_woocommerce_check_activation_state(){
    global $vonic_woocommerce_is_activated_option_name;
	global $vonic_woocommerce_domain_key;

    $server_name = $_SERVER['SERVER_NAME'];
	$domain_key = get_option($vonic_woocommerce_domain_key);
    //echo "vonic_woocommerce_check_activation_state:".$domain_key."<br/>";
    $headers = array( 'Authorization' => 'Basic ' . base64_encode( $server_name . ':' . $domain_key ),);
    $response = wp_remote_get( 'https://vonic.app/isactivated?domain='.$server_name, array('headers' => $headers));
    if ( is_wp_error( $response ) ) {
        return "error";
    } 
    
    $body = wp_remote_retrieve_body( $response );
    //echo "vonic_woocommerce_check_activation_state body:".$body."<br/>";
    update_option($vonic_woocommerce_is_activated_option_name, $body);
    return $body;
}

/**
 * Voice button should be displayed only if:
 * 1. Plugin is activated; and
 * 2. The current page is powered by woocommerce
 *
 * @since 1.0.0
 */
function vonic_woocommerce_display_voice_button_on_current_page($is_wc_page_body = false){
    global $wp;
    global $vonic_woocommerce_product_prefix_option_name;
    global $vonic_woocommerce_is_activated_option_name;
    global $vonic_wc_admin_production_status_option_name;

    $site_url = get_site_url();
    $trimmed_site_url = trim($site_url,"/");
    $cleaned_site_url = implode("/", array_slice(explode("/", $trimmed_site_url),2));
    //echo "cleaned_site_url:".$cleaned_site_url."<br/>";

    $shop_page_url = get_permalink(wc_get_page_id( 'shop' ));
    $trimmed_shop_url = trim($shop_page_url,"/");
    $cleaned_shop_url = implode("/", array_slice(explode("/", $trimmed_shop_url),2));

    $current_url = home_url( $wp->request );
    //echo "current_url:".$current_url."<br/>"; 
    $url_info = wp_parse_url($current_url);
    $trimmed_current_url = trim($current_url,"/");
    $cleaned_current_url = implode("/", array_slice(explode("/", $trimmed_current_url),2));
    //echo "cleaned_current_url:".$cleaned_current_url."<br/>";

    /*$vonic_woocommerce_product_prefix_option_value = get_option($vonic_woocommerce_product_prefix_option_name);

    if($vonic_woocommerce_product_prefix_option_value == false){
        return false;
    }*/

    $production_status = get_option($vonic_wc_admin_production_status_option_name);
    if($production_status == "admin_preview" && !current_user_can('manage_options')){
        return false;
    }

    $is_activated = get_option($vonic_woocommerce_is_activated_option_name);
    //echo "vonic_woocommerce_is_activated_option_value:".$is_activated."<br/>";
    if( $is_activated != "yes"){
        if( $is_activated == "expired"){
            return false;
        }

        if( $is_wc_page_body == true){
            vonic_woocommerce_check_activation_state();
        }

        return false;
    }

    //echo "siteurl:".$cleaned_site_url." ".$url_info['host']."<br/>";
    if(strcmp($cleaned_site_url, $url_info['host']) == 0 ){
        $current_slug = "";
        if( isset($url_info['path'] )){
            $current_slug = $url_info['path'];
        }
        //echo "current_slug:".$current_slug."<br/>";
        $get_array = array();
        if( isset($url_info['query'])){
            $current_params = $url_info['query'];
            parse_str($current_params, $get_array);
        }
        //echo "current_params:".print_r($get_array)."<br/>";
        if(!array_key_exists("post_type",$get_array)){ 
            if(empty($current_slug)){ 
                return true;
            }
        }

        //This is apparently hardcoded in woocommerce
        if(isset($get_array["post_type"]) && $get_array["post_type"] == "product"){
            return true;
        }
    }

    //echo "shopurl:".strcmp($cleaned_shop_url, $cleaned_current_url)."<br/>";
    if(strcmp($cleaned_shop_url, $cleaned_current_url) == 0){
        return true;
    }

    //Check if this is one of the products
    $wc_options = get_option('woocommerce_permalinks');
    $server_name = $_SERVER['SERVER_NAME'];
    $category_base = $wc_options['category_base'];
    $prefix_url = $server_name."/".$category_base;
    //echo "prefix_url1:".$prefix_url."<br/>";
    if( strpos($current_url,$prefix_url) !== false ){
        return true;
    }
    $product_base = $wc_options['product_base'];
    $prefix_url = $server_name."/".ltrim($product_base,"/");
    //echo "prefix_url2:".$prefix_url."<br/>";
    if( strpos($current_url,$prefix_url) !== false ){
        return true;
    }

    if( strpos($current_url, rtrim(wc_get_cart_url(),"/")) !== false || strpos($current_url, rtrim(wc_get_checkout_url(),"/")) !== false){
        return true;
    }

    /*$wc_endpoints_array = array("woocommerce_checkout_order_received_endpoint", "woocommerce_checkout_pay_endpoint", "woocommerce_logout_endpoint", "woocommerce_myaccount_add_payment_method_endpoint", "woocommerce_myaccount_delete_payment_method_endpoint", "woocommerce_myaccount_downloads_endpoint", "woocommerce_myaccount_edit_account_endpoint", "woocommerce_myaccount_edit_address_endpoint", "woocommerce_myaccount_lost_password_endpoint", "woocommerce_myaccount_orders_endpoint", "woocommerce_myaccount_payment_methods_endpoint", "woocommerce_myaccount_set_default_payment_method_endpoint", "woocommerce_myaccount_view_order_endpoint");
    foreach($wc_endpoints_array as $wc_endpoint_option_name){
        $wc_endpoint_option_value = get_option($wc_endpoint_option_name);
        echo $wc_endpoint_option_name.":".$wc_endpoint_option_value."<br/>";
        if($wc_endpoint_option_name == false){
            continue;
        }
        //This check may cause trouble with weird product/category names
        if(strpos($current_url,$wc_endpoint_option_value) !== false){
            return true;
        }
    }*/


    return false;
}

/**
 * Add vonic related tags
 *
 * @since 1.0.0
 */
function vonic_woocommerce_voice_head()
{
    //global $vonic_woocommerce_visiblehash_key_name;
    $vonic_woocommerce_visiblehash_key_name = 'vonic_woocommerce_visiblehash_key';
    $visiblehash = get_option($vonic_woocommerce_visiblehash_key_name);
    //echo "visiblehash:".$visiblehash."<br/>";
    echo "<meta property='vonicva' content='".$visiblehash."' />\n";
}

/**
 * Import required CSS and JS
 *
 * @since 1.0.0
 */
function vonic_woocommerce_voice_enqueue_scripts()
{

    $display_voice_button = vonic_woocommerce_display_voice_button_on_current_page();
    if( $display_voice_button === false){
        return;
    }

    wp_enqueue_style("voniccss","https://vonicpublic.s3.amazonaws.com/wp/vonicwp.css");
    wp_enqueue_script( 'vonicjs', 'https://vonicpublic.s3.amazonaws.com/wp/vonic_wp_0.9.4.0.min.js', array(), "", true);
}

/**
 * Admin settings on load
 *
 * @since 1.0.0
 */
function vonic_woocommerce_admin_settings() {
 
}

/**
 * The plugin menu
 *
 * @since 1.0.0
 */
function vonic_woocommerce_plugin_menu() {
    add_options_page( 'Vonic Plugin Options', 'Vonic Plugin', 'manage_options', 'vonic-plugin', 'vonic_woocommerce_plugin_options' );
}

/**
 * Read woocommerce catalogue from database
 *
 * @since 1.0.0
 */
function vonic_get_woocommerce_catalogue(){
    $data = (object)[];

    //Get woocommerce shop urls
    $shop_page_url = get_permalink(wc_get_page_id( 'shop' ));
    $data->shop_url = $shop_page_url;
    $cart_url = wc_get_cart_url();
    $data->cart_url = $cart_url;
    $checkout_url = wc_get_checkout_url();
    $data->checkout_url = $checkout_url;

    $product_prefix = "";
    //Get Products from Woocommerce
    $products = wc_get_products(array("limit" => 1000));
    $product_array = array();
    foreach($products as $product){
        $product_obj = (object)[];
        $product_obj->id = $product->get_id();
        $product_obj->name = $product->get_name();
        $product_obj->slug = $product->get_slug();
        $product_obj->permalink = get_permalink($product->get_id() );
        $trimmed_permalink = trim($product_obj->permalink,"/");
        $range = array_slice(explode("/", $trimmed_permalink,-1),2);
        $product_prefix = implode("/", $range);
        $product_obj->type = $product->get_type();
        $product_obj->status = $product->get_status();
        $product_obj->featured = $product->get_featured();
        $product_obj->visible = $product->get_catalog_visibility();
        $product_obj->description = $product->get_description();
        $product_obj->short_description = $product->get_short_description();

        //Create category names from category-ids
        $categories = array();
        foreach($product->get_category_ids() as $catid){
            $cat = get_term_by("id", $catid, "product_cat");
            $cat_values = array("id"=> $cat->term_id, "name" => $cat->name, "slug" => $cat->slug );
            array_push($categories, $cat_values);
        }
        $product_obj->categories = $categories;

        //Get attributes and options of Product
        $attributes = array();
        foreach($product->get_attributes() as $attr){
            $attribute = (object)[];
            $attribute->id = $attr->get_id();
            $attribute->name = str_replace("pa_", "", $attr->get_name());
            $attribute->visible = $attr->get_visible();
            $attribute_options = $attr->get_options();
            $options_found = false;
            $options = array();
            if( ! empty($attribute_options)){
                if ( ! is_numeric($attribute_options[0])){
                    foreach($attribute_options as $attribute_option){
                        array_push($options, $attribute_option);
                    }    
                    $options_found = true;
                }
            }

            if( ! $options_found) {
                $terms = $attr->get_terms();
                if( empty( $terms)){
                    continue;
                }
                else{    
                    foreach($terms as $term){
                        array_push($options, $term->name);
                    }
                }
            }
            $attribute->options = $options;
            array_push($attributes, $attribute);
        }
        $product_obj->attributes = $attributes;
        array_push($product_array, $product_obj);
    }
    $data->products = $product_array;
    $data->product_prefix = $product_prefix;

    $wc_options = get_option('woocommerce_permalinks');
    $data->wc_options = $wc_options;
    return $data;
}

/**
 * Sync woocommerce catalogue from database with Vonic servers
 *
 * @since 1.0.0
 */
function vonic_woocommerce_sync_catalogue(){
    global $vonic_woocommerce_product_prefix_option_name;
    global $vonic_wc_admin_email_option_name;
    $vonic_woocommerce_domain_key = 'vonic_woocommerce_domain_key_name';
	//global $vonic_woocommerce_domain_key;

    if(!is_plugin_active('woocommerce/woocommerce.php')){
        return false;
    }

    $data = vonic_get_woocommerce_catalogue();

    $admin_email = get_option('admin_email');
    if($admin_email === false){
        $admin_email = "";
    }
    $data->email = $admin_email;
    $site_url = get_site_url();
    $data->site_url = $site_url;
    $server_name = $_SERVER['SERVER_NAME'];
    $data->domain = $server_name;
    $data->woocommerce_found = true;
    update_option($vonic_woocommerce_product_prefix_option_name, $data->product_prefix);
	$domain_key = get_option($vonic_woocommerce_domain_key);
    $headers = array( 'Authorization' => 'Basic ' . base64_encode( $server_name . ':' . $domain_key ),);
    $response = wp_remote_post( 'https://vonic.app/wc_database_sync', array( 'body' => json_encode($data), "headers" => $headers ) );
    if ( is_wp_error( $response ) ) {
        return false;
    }

    $body = wp_remote_retrieve_body( $response );
    return true;
}

/**
 * Setup initial schedules and sync upon manual activation of plugin
 *
 * @since 1.0.0
 */
function vonic_woocommerce_setup_init_values(){
    global $vonic_woocommerce_schedule_catalogue_sync_event_name;
    global $vonic_woocommerce_check_activation_state_event_name;
    global $vonic_woocommerce_initial_sync_completed_option_name;

    if ( !wp_next_scheduled( $vonic_woocommerce_check_activation_state_event_name )) {
        wp_schedule_event(time(), 'hourly', $vonic_woocommerce_check_activation_state_event_name);
    }

    if ( !wp_next_scheduled( $vonic_woocommerce_schedule_catalogue_sync_event_name )) {
        wp_schedule_event(time(), 'hourly', $vonic_woocommerce_schedule_catalogue_sync_event_name);
    }

    $initial_sync_completed = get_option($vonic_woocommerce_initial_sync_completed_option_name);
    if($initial_sync_completed == "yes"){
        return true;
    }

    $is_catalogue_synced = vonic_woocommerce_sync_catalogue();
    update_option($vonic_woocommerce_initial_sync_completed_option_name,"yes");
    return $is_catalogue_synced;
}

/**
 * Setup initial schedules and sync upon manual activation of plugin
 *
 * @since 1.0.0
 */
function vonic_woocommerce_publish_email_form(){
    global $nonce_action_name;
    global $nonce_field_name;
    global $vonic_wc_admin_production_status_option_name;

    // settings form
    echo "<h3>" . __( 'Publishing status') . "</h3>";

    echo '<form name="form1" method="post" action="">';
    echo '<p>';
    wp_nonce_field( $nonce_action_name, $nonce_field_name );
    _e("Publishing status of Vonic Voice Assistant Plugin:");
    $production_status = get_option($vonic_wc_admin_production_status_option_name);
    $admin_checked = "";
    $live_checked = "";
    if($production_status == "admin_preview"){
        $admin_checked = "checked";
    }
    else{
        $live_checked = "checked";
    }

    echo '
        <hr>
        <input type="radio" name="production_status" value="admin_preview" '.$admin_checked.'> Admin Preview<br>
        <input type="radio" name="production_status" value="live" '.$live_checked.'> Live<br>
        <hr>
    ';
    //echo '<input type="text" name="admin_email" value="" size="50">';

    echo '<p class="submit">';
    echo '<input type="submit" name="Save" class="button-primary" value="Submit" />';
    echo '</p>';

    echo '</form>';
}

/**
 * Setup Vonic admin interface
 *
 * @since 1.0.0
 */
function vonic_woocommerce_plugin_options() {
    global $vonic_woocommerce_is_activated_option_name;
    global $vonic_wc_admin_email_option_name;
    global $vonic_wc_admin_production_status_option_name;
    global $nonce_action_name;
    global $nonce_field_name;

    //must check that the user has the required capability 
    if (!current_user_can('manage_options'))
    {
      wp_die( __('You do not have sufficient permissions to access this page.') );
    }

    if ( !is_plugin_active( 'woocommerce/woocommerce.php' ) ){
        echo '<div class="wrap">';
        echo "<h2>" . __( 'Vonic WooCommerce Plugin Settings') . "</h2>";
        echo "<h3>" . __( 'We could not find woocommerce shop on your site. This plugin will not function properly.') . "</h3>";
        echo '</div>';
        return;
    }

    echo '<div class="wrap">';
    echo "<h2>" . __( 'Vonic WooCommerce Plugin Settings') . "</h2>";

    if( isset($_POST[ $nonce_field_name ]) && wp_verify_nonce( $_POST[$nonce_field_name], $nonce_action_name ) ) {
        $production_status = $_POST["production_status"];
        update_option( $vonic_wc_admin_production_status_option_name, $production_status );
            // Put a "settings saved" message on the screen
        echo '<div class="updated"><p><strong>';
        $production_status_visible_value = $production_status == "admin_preview" ? "Admin Preview" : "Live";
        _e('Vonic production status is now set to : '.$production_status_visible_value); 
        echo '</strong></p></div>';
        echo '<hr>';
    }

    $production_status = get_option($vonic_wc_admin_production_status_option_name);
    //echo "db production_status:".$production_status."<br/>";

    $is_activated = get_option($vonic_woocommerce_is_activated_option_name);
    if($is_activated == "yes"){
        echo '<div class="updated"><p><strong>';
        _e("Your Vonic woocommerce plugin is active. Happy Voice Browsing!");
        echo '</strong></p></div>';
        vonic_woocommerce_publish_email_form();
    }
    else{
        //check_activation_state function may have updated the is_activated value
        $is_activated = vonic_woocommerce_check_activation_state();
        //echo "is_activated:".$is_activated."<br/>";
        if( $is_activated == "error"){
            echo '<div class="updated"><p><strong>';
            echo 'Could not fetch activation state from our servers. Please check again after some time!';
            echo '</strong></p></div>';
        }
        elseif($is_activated == "yes"){
            //Looks like this plugin was re-installed while the domain remained activated.
            //So resync catalogue and scheduled events
            // Put a "settings saved" message on the screen
            echo '<div class="updated"><p><strong>';
            _e("Your Vonic woocommerce plugin is already activated. Happy Voice Browsing!");
            echo '</strong></p></div>';
            vonic_woocommerce_publish_email_form();
        }
        elseif($is_activated == "inprocess"){
            echo '<div class="updated"><p><strong>';
            echo "<p>Your Vonic woocommerce plugin is in process of being activated. See you onboard soon!!</p>";
            echo '</strong></p></div>';
        }
        else{
            echo '<div class="updated"><p><strong>';
            echo 'Could not fetch activation state from our servers. Please check again after some time!';
            echo '</strong></p></div>';
        }
    }
    echo "</div>";
}
