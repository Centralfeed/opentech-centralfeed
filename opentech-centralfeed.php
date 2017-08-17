<?php

/*
Plugin Name: Central Feed exporter
Plugin URI:
Description: Export products from woocommerce store for Central Feed
Version: 1.1
Author: Opentech
Text Domain: centralfeed
 */


if ( ! defined( 'ABSPATH' ) ) :
    exit; // Exit if accessed directly
endif;

class CSVExportForCentralFeed {
    function __construct() {

        require_once( dirname(__FILE__).'/classes/CSVExporter.php');

        if ( is_admin() ) {
            add_action( 'admin_menu', array( $this, 'admin_menu' ), 99 );
        }

        if ( isset( $_GET[ 'centralfeed' ] )) {
            $options = get_option('centralfeed_options');
            $password = $options['centralfeed_password'];
            if ( isset( $_GET[ 'p' ] ) && $_GET[ 'p' ] == $password) {
                add_action('wp_loaded', array($this,'centralfeed_export'));
            }
            else {
                die('password is required');
            }
        }
    }


    function admin_menu() {

        if (function_exists('add_menu_page'))
        {
            add_menu_page('Central Feed Export', 'Central Feed Export', 'administrator', dirname( __FILE__ ) . '/classes/CSVExporterMenu.php');
        }
    }

    function centralfeed_export(){

        $exporter = new OpentechCentralfeedExporter();
        $type = $exporter::EXPORT_TYPE_ALL;
        if ( isset( $_GET[ 'Command' ] )) {
            $command =  strtolower($_GET[ 'Command' ] );
            if ($command == $exporter::EXPORT_TYPE_STOCK)
                $type = $exporter::EXPORT_TYPE_STOCK;
        }
        $exporter->centralfeed_export($type);
    }
}

new CSVExportForCentralFeed();


/**
 * custom option and settings
 */
function centralfeed_options_settings_init()
{
    // register a new setting for "wporg" page
    register_setting('centralfeed_options', 'centralfeed_options');

    // register a new section in the "centralfeed_options" page
    add_settings_section(
        '',
        __('לינק לייצוא centralfeed', 'centralfeed_options'),
        'centralfeed_options_cb',
        'centralfeed_options'
    );

    // register a new field
    add_settings_field(
        'centralfeed_option_password',
        __('סיסמה עבור centralfeed', 'centralfeed_options'),
        'centralfeed_options_password_field',
        'centralfeed_options',
        '',
        [
            'label_for'         => 'centralfeed_password',
            'class'             => 'centralfeed_link',
            'centralfeed_custom_data' => 'custom',
        ]
    );
}

/**
 * register our centralfeed_options_settings_init to the admin_init action hook
 */
add_action('admin_init', 'centralfeed_options_settings_init');


// section callbacks can accept an $args parameter, which is an array.
// $args have the following keys defined: title, id, callback.
// the values are defined at the add_settings_section() function.
function centralfeed_options_cb($args)
{
    $options = get_option('centralfeed_options');
    $password = $options['centralfeed_password'];
    ?>
    <p id="<?= esc_attr($args['id']); ?>">
        <?= esc_html__('הלינק עבור קובץ centralfeed:', 'centralfeed_options'); ?>
        <BR>
        <?= get_home_url().'?centralfeed&p='.$password?>
<BR><BR>
        <?= esc_html__('הלינק עבור קובץ centralfeed - כמויות בלבד:', 'centralfeed_options'); ?>
        <BR>
        <?= get_home_url().'?centralfeed&p='.$password.'&Command=stock'?>

    </p>
    <?php
}


function centralfeed_options_password_field($args)
{
    centralfeed_options_input_text ($args, "centralfeed_password");
}

function centralfeed_options_input_text ($args, $option) {
    // get the value of the setting we've registered with register_setting()
    $options = get_option('centralfeed_options');

    // output the field
    ?>
    <input type="text" id="<?= esc_attr($args['label_for']); ?>"
           data-custom="<?= esc_attr($args['centralfeed_custom_data']); ?>"
           name="centralfeed_options[<?= esc_attr($args['label_for']); ?>]"
           value="<?= esc_attr($options[$option]); ?>"
        >
    <?php
}

