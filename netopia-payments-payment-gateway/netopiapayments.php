<?php

/*
Plugin Name: NETOPIA Payments Payment Gateway
Plugin URI: https://www.netopia-payments.ro
Description: accept payments through NETOPIA Payments
Author: Netopia
Version: 1.3
License: GPLv2
*/

// The ID use as unigue identifier in Block
// define('NETOPIA_PAYMENTS_ID', 'netopia_payments');

// Include our Gateway Class and register Payment Gateway with WooCommerce
add_action( 'plugins_loaded', 'netopiapayments_init', 0 );
function netopiapayments_init() {
	// If the parent WC_Payment_Gateway class doesn't exist
	// it means WooCommerce is not installed on the site
	// so do nothing
	if ( ! class_exists( 'WC_Payment_Gateway' ) ) return;
	DEFINE ('NTP_PLUGIN_DIR', plugins_url( basename( plugin_dir_path( __FILE__ ) ), basename( __FILE__ ) ) . '/' );
	
	// If we made it this far, then include our Gateway Class
	include_once( 'wc-netopiapayments-gateway.php' );

	// Now that we have successfully included our class,
	// Lets add it too WooCommerce
	add_filter( 'woocommerce_payment_gateways', 'add_netopiapayments_gateway' );
	function add_netopiapayments_gateway( $methods ) {
		$methods[] = 'netopiapayments';
		return $methods;
	}

	// Add custom action links
	add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'netopia_action_links' );
	function netopia_action_links( $links ) {
		$plugin_links = array(
			'<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=netopiapayments' ) . '">' . __( 'Settings', 'netopiapayments' ) . '</a>',
		);
		return array_merge( $plugin_links, $links );
	}

	add_action( 'admin_enqueue_scripts', 'netopiapaymentsjs_init' );
    function netopiapaymentsjs_init($hook) {
        if ( 'woocommerce_page_wc-settings' != $hook ) {
            return;
        }
        wp_enqueue_script( 'netopiapaymentsjs', plugin_dir_url( __FILE__ ) . 'js/netopiapayments_.js',array('jquery'),'2.0' ,true);
        wp_enqueue_script( 'netopiaOneyjs', plugin_dir_url( __FILE__ ) . 'js/netopiaOney.js',array('jquery'),'2.0' ,true);
        wp_enqueue_script( 'netopiatoastrjs', plugin_dir_url( __FILE__ ) . 'js/toastr.min.js',array(),'2.0' ,true);
        wp_enqueue_style( 'netopiatoastrcss', plugin_dir_url( __FILE__ ) . 'css/toastr.min.css',array(),'2.0' ,false);
    }

	/**
	 * Custom function to declare compatibility with cart_checkout_blocks feature 
	*/
	function declare_netopiapayments_blocks_compatibility() {
		// Check if the required class exists
		if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
			// Declare compatibility for 'cart_checkout_blocks'
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
		}
	}
	// Hook the custom function to the 'before_woocommerce_init' action
	add_action('before_woocommerce_init', 'declare_netopiapayments_blocks_compatibility');

	// Hook in Blocks integration. This action is called in a callback on plugins loaded
	add_action( 'woocommerce_blocks_loaded', 'woocommerce_gateway_netopia_block_support' );
	function woocommerce_gateway_netopia_block_support() {
		if ( class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
			
			// Include the custom Block checkout class
			require_once dirname( __FILE__ ) . '/netopia/Payment/Blocks.php';

			// Hook the registration function to the 'woocommerce_blocks_payment_method_type_registration' action
			add_action(
				'woocommerce_blocks_payment_method_type_registration',
				function( Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry ) {
					add_action(
						'woocommerce_blocks_payment_method_type_registration',
						function( Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry ) {
							// Registre an instance of netopiaBlocks
							$payment_method_registry->register( new netopiapaymentsBlocks );
						 }
					);
				},
				5
			);
		} else {
			// The Current installation of wordpress not sue WooCommerce Block
			return;
		}
	}

	// Add Oney Plugin
	// Define the path to the oney add-on
	$oney_add_on_path = plugin_dir_path(__FILE__) . 'oney/oney-add-on-netopia.php';

	// Check if the file exists before including
	if (file_exists($oney_add_on_path)) {
		include_once($oney_add_on_path);
	} else {
		error_log('Oney add-on file not found: ' . $oney_add_on_path);
	}

}


/**
 * Activation hook  once after install / update will execute
 * By "verify-regenerat" key will verify if certifications not exist
 * Then try to regenerated the certifications
 * */ 
register_activation_hook( __FILE__, 'plugin_activated' );
function plugin_activated(){
	add_option( 'woocommerce_netopiapayments_certifications', 'verify-and-regenerate' );
}

/**
 * The verify and generate certifications will add before the plugin upgrade too.
 * Just in case if installation failed,..
 * Note : We deactive the plugin before upgrade, 
 */
add_action('upgrader_pre_install', 'ntpPreUpgrade', 10, 2);
function ntpPreUpgrade($upgrader_object, $options) {
	// check if , the instalation / upgrade is related to NETOPIA plugin
	if(isset($_POST['action'], $_POST['slug'])) {
		if($upgrader_object && $_POST['action'] == "install-plugin" && $_POST['slug'] == "netopia-payments-payment-gateway") {
			// Deactivate the plugin
			deactivate_plugins(plugin_basename(__FILE__));
			add_option( 'woocommerce_netopiapayments_certifications', 'verify-and-regenerate' );
		}
	}
}

add_action('upgrader_process_complete', 'ntpUpgrade_complete', 10, 2);
function ntpUpgrade_complete($upgrader_object, $options) {
    add_option( 'woocommerce_netopiapayments_certifications', 'verify-and-regenerate' );
}


/**
 * Deactive the plugin, before uninstall the plugin
 */
register_uninstall_hook(__FILE__, 'ntpUninstall');
function ntpUninstall() {
    // Deactivate the plugin
   deactivate_plugins(plugin_basename(__FILE__));
}

/** OneY Netopia Hooks */

/* BEGIN PLUGIN SETTIGN SPAGE */
// Define a global variable to store the link to metoda de plata value

function create_oney_netopia_page() {
    global $wpdb;

    // Create the table if not exists
    $table_name = $wpdb->prefix . 'oney_netopia_vars';
    
    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id INT AUTO_INCREMENT PRIMARY KEY,
        oney_name TEXT,
        oney_value TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;";

    // Execute the query
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
    
    // Check if the page already exists
    $page_query = new WP_Query( array(
        'post_type' => 'page',
        'post_status' => array( 'publish'), // Include all statuses
        'posts_per_page' => 1,
        'title' => 'Oferta Rate Oney'
    ) );

    if( ! $page_query->have_posts() ) {
        // Oney Page doesn't exist, so create it
        $page_args = array(
            'post_title'    => 'Oferta Rate Oney',
            'post_content'  => '[oney-netopia-metoda-plata]',
            'post_status'   => 'publish',
            'post_type'     => 'page'
        );

        // Insert the post into the database and store the ID
        $page_id = wp_insert_post( $page_args );
        

    } else {
        // Page already exists, so retrieve its ID
        $page = $page_query->posts[0];
        $page_id = $page->ID;
    }
    
    // Check if an entry with oney_name = 'oney_netopia_details_page_id' exists
    $existing_entry = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE oney_name = %s", 'oney_netopia_details_page_id' ) );

    if ( $existing_entry ) {
        // Entry already exists, so update its value
        $wpdb->update( 
            $table_name, 
            array( 
                'oney_value' => $page_id
            ), 
            array( 
                'oney_name' => 'oney_netopia_details_page_id'
            ) 
        );
    } else {
        // Entry doesn't exist, so insert a new entry
        $wpdb->insert( 
            $table_name, 
            array( 
                'oney_name' => 'oney_netopia_details_page_id',
                'oney_value' => $page_id
            ) 
        );
    }

    // Restore original post data
    wp_reset_postdata();
}


// Hook into the activation function and create the page
register_activation_hook( __FILE__, 'create_oney_netopia_page' );