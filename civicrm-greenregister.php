<?php
/**
 * Plugin Name: CiviCRM GreenRegister The Register CPT
 * Description: Custom post types and taxonomies for GreenRegister
 * Version: 0.1
 * Author: Matthew Wire
 * Author URI: https://mjw.pt
 * Plugin URI: https://github.com/mjwconsult/civicrm-wp-greenregister
 * Text Domain: civicrm-wp-greenregister
 * Domain Path: /languages
 * Network: false
 *
 * @package CiviCRM_Greenregister
 */


// Store reference to this file.
if ( ! defined( 'CIVICRM_WP_GREENREGISTER_FILE' ) ) {
	define( 'CIVICRM_WP_GREENREGISTER_FILE', __FILE__ );
}

// Store URL to this plugin's directory.
if ( ! defined( 'CIVICRM_WP_GREENREGISTER_URL' ) ) {
	define( 'CIVICRM_WP_GREENREGISTER_URL', plugin_dir_url( CIVICRM_WP_GREENREGISTER_FILE ) );
}

// Store PATH to this plugin's directory.
if ( ! defined( 'CIVICRM_WP_GREENREGISTER_PATH' ) ) {
	define( 'CIVICRM_WP_GREENREGISTER_PATH', plugin_dir_path( CIVICRM_WP_GREENREGISTER_FILE ) );
}



/**
 * CiviCRM Greenregister Class.
 *
 * A class that encapsulates this plugin's functionality.
 *
 * @since 0.1
 */
class CiviCRM_WP_Greenregister {

	/**
	 * Admin object.
	 *
	 * @since 0.1
	 * @access public
	 * @var object $db The Admin object.
	 */
	public $db;

	/**
	 * CiviCRM object.
	 *
	 * @since 0.1
	 * @access public
	 * @var object $civi The CiviCRM utilities object.
	 */
	public $civi;

	/**
	 * Greenregister object.
	 *
	 * @since 0.1
	 * @access public
	 * @var CiviCRM_Greenregister_TheRegister_CPT $theregister The Greenregister utilities object.
	 */
	public $theregister;

	/**
	 * Taxonomy Sync object.
	 *
	 * @since 0.4.2
	 * @access public
	 * @var object $taxonomy The Taxonomy sync object.
	 */
	public $taxonomy;

	/**
	 * CiviCRM Profile Sync compatibility object.
	 *
	 * @since 0.4.4
	 * @access public
	 * @var CiviCRM_WP_Profile_Sync $cwps The CiviCRM Profile Sync compatibility object.
	 */
	public $cwps;



	/**
	 * Initialises this object.
	 *
	 * @since 0.1
	 */
	public function __construct() {

		// Initialise.
		add_action( 'plugins_loaded', [ $this, 'initialise' ] );

	}



	/**
	 * Do stuff on plugin init.
	 *
	 * @since 0.1
	 */
	public function initialise() {

		// Bail if CiviCRM plugin is not present.
		if ( ! function_exists( 'civi_wp' ) ) {
			return;
		}

		// Bail if there's no ACF plugin present.
		if ( ! function_exists( 'acf' ) ) {
			return;
		}

		// Prefer CiviCRM ACF Integration if present.
		if ( function_exists( 'civicrm_acf_integration' ) ) {
			return;
		}

		// Bail if there's no CiviCRM Profile Sync plugin present.
		if ( ! defined( 'CIVICRM_WP_PROFILE_SYNC_VERSION' ) ) {
			return;
		}

		// Bail if CiviCRM Profile Sync is not version 0.4 or greater.
		if ( version_compare( CIVICRM_WP_PROFILE_SYNC_VERSION, '0.4', '<' ) ) {
			return;
		}

		// Wait for next action to finish set up.
		add_action( 'sanitize_comment_cookies', [ $this, 'setup_instance' ] );

	}



	/**
	 * Wait until "plugins_loaded" has finished to set up instance.
	 *
	 * This is necessary because the order in which plugins load cannot be
	 * guaranteed and we need to find out if CiviCRM Profile Sync has fully
	 * loaded its ACF classes.
	 *
	 * @since 0.6.2
	 */
	public function setup_instance() {

		// Grab reference to CiviCRM Profile Sync.
		$plugin = civicrm_wp_profile_sync();

		// Bail if CiviCRM Profile Sync hasn't loaded ACF.
		if ( ! $plugin->acf->is_loaded() ) {
			return;
		}

		// Store reference.
		$this->cwps = $plugin;

		// Include files.
		$this->include_files();

		// Set up objects and references.
		$this->setup_objects();

		/**
		 * Broadcast that this plugin is now loaded.
		 *
		 * This action is used internally by this plugin to initialise its objects
		 * and ensures that all includes and setup has occurred beforehand.
		 *
		 * @since 0.4.1
		 */
		do_action( 'civicrm_wp_greenregister_loaded' );

	}




	/**
	 * Include files.
	 *
	 * @since 0.4
	 */
	public function include_files() {

		// Load our class files.
		include CIVICRM_WP_GREENREGISTER_PATH . 'includes/cwps-gr-theregister-cpt.php';
		include CIVICRM_WP_GREENREGISTER_PATH . 'includes/cwps-gr-theregister-cpt-tax.php';

	}



	/**
	 * Set up this plugin's objects.
	 *
	 * @since 0.4
	 */
	public function setup_objects() {

		// Only do this once.
		static $done;
		if ( isset( $done ) && $done === true ) {
			return;
		}

		// Initialise objects.
		$this->theregister = new CiviCRM_Greenregister_TheRegister_CPT( $this->cwps );

		// We're done.
		$done = true;

	}



	/**
	 * Do stuff on plugin activation.
	 *
	 * @since 0.1
	 */
	public function activate() {
		$this->initialise();
		$this->setup_instance();
		$this->theregister->activate();
	}



	/**
	 * Do stuff on plugin deactivation.
	 *
	 * @since 0.1
	 */
	public function deactivate() {
		$this->initialise();
		$this->theregister->deactivate();

	}



	// -------------------------------------------------------------------------



} // Class ends.



// Declare as global.
global $civicrm_wp_greenregister;

// Init plugin.
$civicrm_wp_greenregister = new CiviCRM_WP_Greenregister();



/**
 * Utility to get a reference to this plugin.
 *
 * @since 0.2.2
 *
 * @return object $civicrm_wp_greenregister The plugin reference.
 */
function civicrm_gr() {

	// Return instance.
	global $civicrm_wp_greenregister;
	return $civicrm_wp_greenregister;

}

// Activation.
register_activation_hook( __FILE__, [ civicrm_gr(), 'activate' ] );

// Deactivation.
register_deactivation_hook( __FILE__, [ civicrm_gr(), 'deactivate' ] );
