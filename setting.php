<?php

defined( 'ABSPATH' ) || die();

$new_general_setting = new new_general_setting();

class new_general_setting {

    public function __construct(){
        add_filter( 'admin_init' , array( $this, 'register_fields' ) );
    }

    public function register_fields() {

        register_setting( 'general', 'sig_wpscan_api' );

        add_settings_field(
            'wpscan_api',
            '<label for="sig_wpscan_api">WPSCAN API token</label>',
            array( $this, 'fields_html') ,
            'general'
        );
    }

    public function fields_html() {
        $value = get_option( 'sig_wpscan_api', '' );
        echo '<input type="text" id="sig_wpscan_api" name="sig_wpscan_api" value="' . $value . '" class="regular-text ltr"/>';
        echo '<p class="description"><a href="https://wpscan.com/api" target="_blank">https://wpscan.com/api</a></p>';
    }
}