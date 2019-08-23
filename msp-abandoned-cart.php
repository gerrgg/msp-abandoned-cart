<?php
/**
 * Plugin Name: MSP Abandoned Cart
 * Plugin URI: http://drunk.kiwi
 * Description: A lightweight plugin that grabs emails from checkout fields, waits 10 minutes, checks, sets up cron & emails user.
 * Author: Gerrg
 * Author URI: http://www.gerrg.com
 * Version: 0.1
 *
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 *
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly


class Msp_Abandoned_Cart
{
    /**
     * This cart will grab emails from checkout fields, saves the email to a transient,
     * sets up cron to email the user, sends the email, deletes the cron & transient.
     */
    private $settings = array(
        'subject' => "We've got your abandoned cart!",
        'headers' => array('Content-Type: text/html; charset=UTF-8'),
    );

    public $email;
    public $cart;
    public $hooks = array(
        'before' => '',
        'after'  => ''
    );


     function __construct()
     /**
      * Sets up hook for ajax requests. Runs the setup for order_wait cron.
      */
     {
         add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
         
         add_action( 'wp_ajax_msp_save_checkout_email', array( $this, 'get_data' ) );
         add_action( 'wp_ajax_nopriv_msp_save_checkout_email', array( $this, 'get_data' ) );
     }

     public function enqueue_scripts(){
        wp_enqueue_script( 'functions', plugin_dir_url( __FILE__ ) . '/includes/functions.js', array( 'jquery' ) );
        wp_localize_script( 'functions', 'wp_ajax', array( 'url' => admin_url( 'admin-ajax.php' ) ) );
     }

     public function get_data( $email = '' )
     /**
      * Takes in an email, then creates a hash of cookie data
      */
     {
        $this->email = ( empty($email) ) ? $_POST['email'] : $email;
        if( ! empty( $this->email ) && ! WC()->cart->is_empty() ){
            $session_keys = explode('||', $_COOKIE['wp_woocommerce_session_' . COOKIEHASH]);
            $this->cart = array(
                'hash' => $_COOKIE['woocommerce_cart_hash'],
                'item_count' => $_COOKIE['woocommerce_items_in_cart'],
                'customer_id' => $session_keys[0]
            );
            $this->set_wait_for_order_cron();
        }
     }

     public function set_wait_for_order_cron()
     /**
      * Creates a unique hook using cart hash, scheduals a check on cart in ten minutes
      */
     {
         if( empty( $this->cart['hash'] ) ) return;

         $hook = 'await_order_for_cart_' . $this->cart['hash'];
         $this->hooks['before'] = $hook;

         $one_hour = time() + 30;
         if( ! wp_next_scheduled( $hook ) ){
            wp_schedule_single_event( $one_hour, $hook, $this->cart );
            add_action( $hook, array( $this, 'check_order_for_cart' ) );
         }
     }

     public static function get_session( $customer_id )
     {
        $handler = new WC_Session_Handler();
        return $handler->get_session( $customer_id );
     }

     public static function check_order_for_cart( $cart )
     {
        /**
         * Splits up the session cookie, uses the first part to grab the cart session
         * If session is false, that means the cart expired or was order.
         */
         $session = $this->get_session( $cart['customer_id'] );
         
         if( false !== $session ){
            $hook = 'email_customer_abandoned_cart_' . $cart['hash'];
            $this->hooks['after'] = $hook;

            wp_schedule_single_event( time() + DAY_IN_SECONDS, $hook, $cart );
            add_action( $hook, array( $this, 'email_customer_abandoned_cart' ) );
         }
         remove_action( $this->hooks['before'], array( $this, 'check_order_for_cart' ) );
     }

    public static function email_customer_abandoned_cart( $cart ){
        $session = $this->get_session( $cart['customer_id'] );
        $hook = $this->hooks['after'];
        
        if( false === $session ){
            remove_action( $hook, array( $this, 'email_customer_abandoned_cart' ) );
        } else {
            ob_start();
            var_dump( $this->cart );
            $message = ob_get_contents();
            wp_mail( $this->email, $this->settings['subject'], $message, $this->settings['headers'] );
        }
    }

    
}

new Msp_Abandoned_Cart();
