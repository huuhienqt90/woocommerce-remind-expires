<?php
/**
 * Plugin Name: WooCommerce Subscription Reminders
 * Plugin URI: https://github.com/huuhienqt90/woocommerce-remind-expires
 * Description: Sends subscription reminder emails to subscribers.
 * Author: Hien(Hamilton) H.HO
 * Author URI: https://github.com/huuhienqt90
 * Version: 1.0.0
 *
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Required functions
 */
if ( ! function_exists( 'woothemes_queue_update' ) || ! function_exists( 'is_woocommerce_active' ) ) {
    require_once( 'woo-includes/woo-functions.php' );
}

if ( ! class_exists( 'WooCommerceRemindExpires' ) ) :

/**
 * Main WooCommerceRemindExpires Class.
 *
 * @class WooCommerceRemindExpires
 * @version 1.0.0
 */
class WooCommerceRemindExpires {
    /**
     * Construct for class
     */
    public function __construct(){
        $this->init();
    }

    /**
     * Init hooks
     */
    public function init(){
        register_activation_hook( __FILE__, array( $this, 'install' ) );
        register_deactivation_hook( __FILE__, array( $this, 'uninstall' ) );

        add_action( 'admin_menu', array( $this, 'class_admin_menu') );
        add_action( 'init', array( $this, 'woocommerce_loaded' ), 50);
    }

    /**
     * Hooks when woocommerce loaded
     */
    public function woocommerce_loaded(){
        add_action( 're_woocommerce_subscription_remind', array( $this, 'get_subsciption_shoud_send_remind' ) );
    }

    /**
     * Add admin menu
     */
    public function class_admin_menu(){
        add_options_page( 
            'Subscription Reminders',
            'Subscription Reminders',
            'manage_options',
            're-remind-email',
            array( $this, 'remind_email_view' )
        );
    }

    /**
     * Show admin menu view
     */
    public function remind_email_view(){
        load_template(self::plugin_path().'/templates/admin/view/email.php');
    }

    /**
     * Trigger when active plugin
     */
    public function install(){
        // Require woocommerce plugin
        if ( ! is_plugin_active( 'woocommerce/woocommerce.php' ) and current_user_can( 'activate_plugins' ) ) {
            // Stop activation redirect and show error
            wp_die('Sorry, but this plugin requires the WooCommerce Plugin to be installed and active. <br><a href="' . admin_url( 'plugins.php' ) . '">&laquo; Return to Plugins</a>');
        }

        // Require woocommerce-subscriptions plugin
        if ( ! is_plugin_active( 'woocommerce-subscriptions/woocommerce-subscriptions.php' ) and current_user_can( 'activate_plugins' ) ) {
            // Stop activation redirect and show error
            wp_die('Sorry, but this plugin requires the WooCommerce Subscriptions Plugin to be installed and active. <br><a href="' . admin_url( 'plugins.php' ) . '">&laquo; Return to Plugins</a>');
        }

        if (! wp_next_scheduled ( 're_woocommerce_subscription_remind' ) ) {
            wp_schedule_event( strtotime(date("Y-m-d 14:30:00")), 'daily', 're_woocommerce_subscription_remind' );
            //wp_schedule_event( time(), 'every_minute', 're_woocommerce_subscription_remind' );
        }
    }

    /**
     * Trigger when deactive plugin
     */
    public function uninstall(){
        wp_clear_scheduled_hook( 're_woocommerce_subscription_remind' );
    }

    /**
     * Get the plugin url.
     * @return string
     */
    public function plugin_url() {
        return untrailingslashit( plugins_url( '/', __FILE__ ) );
    }

    /**
     * Get the plugin path.
     * @return string
     */
    public function plugin_path() {
        return untrailingslashit( plugin_dir_path( __FILE__ ) );
    }

    /**
     * Do function for cron tab
     */
    public function get_subsciption_shoud_send_remind(){
        global $woocommerce;
        $log  = null;
        
        $data = ['re_subject', 're_heading', 're_remind_days', 're_content', 're_first_remind'];
        $dataOptions = [];
        foreach ($data as $k) {
            $dataOptions[$k] = get_option($k);
        }

        $re_subject = isset($dataOptions['re_subject']) && !empty($dataOptions['re_subject']) ? $dataOptions['re_subject'] : 'Email Remind';        
        $re_first_remind = isset($dataOptions['re_first_remind']) && !empty($dataOptions['re_first_remind']) && is_numeric($dataOptions['re_first_remind']) && $dataOptions['re_first_remind'] > 0 ? $dataOptions['re_first_remind'] : 0;
        $dayRemind = isset($dataOptions['re_remind_days']) && !empty($dataOptions['re_remind_days']) && is_numeric($dataOptions['re_remind_days']) && $dataOptions['re_remind_days'] > 0 ? $dataOptions['re_remind_days'] : 0;

        // First send remind
        if($re_first_remind):
            $Subscriptions = new WP_Query([
                'post_type' => 'shop_subscription', 
                'post_status' => 'wc-active', 
                'posts_per_page' => -1,
                'meta_query' => array(
                    array(
                        'key'     => '_schedule_next_payment',
                        'value'   => date("Y-m-d", time() + ((int)$re_first_remind * 24 * 3600) ),
                        'compare' => '=',
                        'type'    => 'DATE'
                    ),
                )
            ]);

            $templatePath = self::plugin_path().'/templates/';

            if( $Subscriptions->have_posts() ){

                while( $Subscriptions->have_posts()){

                    $Subscriptions->the_post();
                    $wcSub              = new WC_Subscription(get_the_ID());

                    $nextPaymentDate = get_post_meta(get_the_ID(), '_schedule_next_payment', true);
                    $search     = ['{first_name}', '{subscription_number}', '{days_till_renewal}','{renewal_date}', '{renewal_time}'];
                    $replace    = [$wcSub->get_billing_first_name(), get_the_ID(), $re_first_remind, date("j F Y", strtotime($nextPaymentDate)), date("g:i A", strtotime($nextPaymentDate))];

                    // Email content
                    $email_heading = isset($dataOptions['re_heading']) && !empty($dataOptions['re_heading']) ? $dataOptions['re_heading'] : 'Email Remind';
                    $message = isset($dataOptions['re_content']) && !empty($dataOptions['re_content']) ? $dataOptions['re_content'] : '';
                    $content = wc_get_template_html( 'emails/woocommerce-remind-expires.php',[
                        'email_heading' => str_replace($search, $replace, $email_heading),
                        'subscription' => $wcSub,
                        'message' => $message,
                        'sent_to_admin' => false,
                        'plain_text' => false
                    ], null, $templatePath);
                    
                    $content    = str_replace($search, $replace, $content);

                    //Write action to txt log
                    $log  .= date("F j, Y, g:i a") . " - " . $wcSub->get_billing_email() . " - first reminder".PHP_EOL;

                    ob_start();
                    wc_get_template( 'emails/email-styles.php' );
                    $css = apply_filters( 'woocommerce_email_styles', ob_get_clean() );
                    if( !class_exists('Emogrifier') ){
                        global $woocommerce;
                        include $woocommerce->plugin_path()."/includes/libraries/class-emogrifier.php";
                    }

                    // apply CSS styles inline for picky email clients
                    try {
                        $emogrifier = new Emogrifier( $content, $css );
                        $content    = $emogrifier->emogrify();
                    } catch ( Exception $e ) {
                        
                    }
                    

                    // Send
                    $headers = "Content-Type: text/html\r\n";
                    wp_mail($wcSub->get_billing_email(), str_replace($search, $replace, $re_subject), $content, $headers);
                }
                wp_reset_postdata();
            }
        endif;

        // Second send remind
        if($dayRemind):
            $Subscriptions = new WP_Query([
                'post_type' => 'shop_subscription', 
                'post_status' => 'wc-active', 
                'posts_per_page' => -1,
                'meta_query' => array(
                    array(
                        'key'     => '_schedule_next_payment',
                        'value'   => date("Y-m-d", time() + ((int)$dayRemind * 24 * 3600) ),
                        'compare' => '=',
                        'type'    => 'DATE'
                    ),
                )
            ]);

            $templatePath = self::plugin_path().'/templates/';
            if( $Subscriptions->have_posts() ){

                while( $Subscriptions->have_posts()){
                    $Subscriptions->the_post();
                    $wcSub              = new WC_Subscription(get_the_ID());

                    $nextPaymentDate = get_post_meta(get_the_ID(), '_schedule_next_payment', true);
                    $search     = ['{first_name}', '{subscription_number}', '{days_till_renewal}','{renewal_date}', '{renewal_time}'];
                    $replace    = [$wcSub->get_billing_first_name(), get_the_ID(), $dayRemind, date("j F Y", strtotime($nextPaymentDate)), date("g:i A", strtotime($nextPaymentDate))];

                    // Email content
                    $email_heading = isset($dataOptions['re_heading']) && !empty($dataOptions['re_heading']) ? $dataOptions['re_heading'] : 'Email Remind';
                    $message = isset($dataOptions['re_content']) && !empty($dataOptions['re_content']) ? $dataOptions['re_content'] : '';
                    $content = wc_get_template_html( 'emails/woocommerce-remind-expires.php',[
                        'email_heading' => str_replace($search, $replace, $email_heading),
                        'subscription' => $wcSub,
                        'message' => $message,
                        'sent_to_admin' => false,
                        'plain_text' => false
                    ], null, $templatePath);
                    
                    $content    = str_replace($search, $replace, $content);

                    //Write action to txt log
                    $log  .= date("F j, Y, g:i a") . " - " . $wcSub->get_billing_email() . " - second reminder".PHP_EOL;

                    ob_start();
                    wc_get_template( 'emails/email-styles.php' );
                    $css = apply_filters( 'woocommerce_email_styles', ob_get_clean() );
                    if( !class_exists('Emogrifier') ){
                        global $woocommerce;
                        include $woocommerce->plugin_path()."/includes/libraries/class-emogrifier.php";
                    }

                    // apply CSS styles inline for picky email clients
                    try {
                        $emogrifier = new Emogrifier( $content, $css );
                        $content    = $emogrifier->emogrify();
                    } catch ( Exception $e ) {
                        
                    }

                    // Send Mail
                    $headers = "Content-Type: text/html\r\n";
                    wp_mail($wcSub->get_billing_email(), str_replace($search, $replace, $re_subject), $content, $headers);
                }
                wp_reset_postdata();
            }
        endif;

        file_put_contents(self::plugin_path().'/logs/log_'.date("j.n.Y").'.txt', $log, FILE_APPEND);
    }
}
endif;

new WooCommerceRemindExpires();