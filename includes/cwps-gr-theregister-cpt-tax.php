<?php
/**
 * CiviCRM "The Register" CPT Taxonomy Class.
 *
 * Handles sync between the Option Values for custom fields to custom taxonomies for "The Register" CPT.
 *
 * @package CiviCRM_WP_Profile_Sync
 * @since 0.5
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * "The Register" Taxonomy Class.
 *
 * This class keeps the custom field taxonomies for "The Register" synchronised from CiviCRM to WordPress.
 * Changes are ALWAYS overwritten on the WordPress side
 *
 * @since 0.5
 */
class CiviCRM_Greenregister_TheRegister_CPT_Tax {

	/**
	 * Plugin object.
	 *
	 * @since 0.5
	 * @access public
	 * @var object $plugin The plugin object.
	 */
	public $plugin;

	/**
	 * ACF Loader object.
	 *
	 * @since 0.5
	 * @access public
	 * @var CiviCRM_WP_Profile_Sync_ACF_Loader $acf_loader The ACF Loader object.
	 */
	public $acf_loader;

	/**
	 * CiviCRM object.
	 *
	 * @since 0.5
	 * @access public
	 * @var CiviCRM_Profile_Sync_ACF_CiviCRM $civicrm The CiviCRM object.
	 */
	public $civicrm;

	/**
	 * Contact object.
	 *
	 * @since 0.5
	 * @access public
	 * @var CiviCRM_Profile_Sync_ACF_CiviCRM_Contact $contact The Contact object.
	 */
	public $contact;

	/**
	 * Parent (calling) object.
	 *
	 * @since 0.5
	 * @access public
	 * @var CiviCRM_Greenregister_TheRegister_CPT $cpt The parent object.
	 */
	public $cpt;

	/**
	 * Term Meta key.
	 *
	 * @since 0.5
	 * @access public
	 * @var object $term_meta_key The Term Meta key.
	 */
	public $term_meta_key = '_cwps_civicrm_optionvalue_id';

	/**
	 * Constructor.
	 *
	 * @since 0.5
	 *
	 * @param object $parent The parent object reference.
	 */
	public function __construct( $parent ) {

		// Store references to objects.
		$this->plugin = $parent->acf_loader->plugin;
		$this->acf_loader = $parent->acf_loader;
		$this->civicrm = $parent->civicrm;
		$this->contact = $parent->contact;
		$this->cpt = $parent;

		// Init when the "The Register" CPT object is loaded.
		add_action( 'cwps/acf/civicrm/theregister-cpt/loaded', [ $this, 'initialise' ] );

	}



	/**
	 * Initialise this object.
	 *
	 * @since 0.5
	 */
	public function initialise() {

		// Register hooks.
		$this->register_hooks();

	}



	/**
	 * Register hooks on plugin init.
	 *
	 * @since 0.5
	 */
	public function register_hooks() {

		// Add CiviCRM listeners once CiviCRM is available.
		add_action( 'civicrm_config', [ $this, 'civicrm_config' ], 10, 1 );

		// Create custom filters that mirror 'the_content'.
		add_filter( 'cwps/acf/civicrm/theregister-cpt/term-desc', 'wptexturize' );
		add_filter( 'cwps/acf/civicrm/theregister-cpt/term-desc', 'convert_smilies' );
		add_filter( 'cwps/acf/civicrm/theregister-cpt/term-desc', 'convert_chars' );
		add_filter( 'cwps/acf/civicrm/theregister-cpt/term-desc', 'wpautop' );
		add_filter( 'cwps/acf/civicrm/theregister-cpt/term-desc', 'shortcode_unautop' );

	}



	/**
	 * Callback for "civicrm_config".
	 *
	 * @since 0.5
	 *
	 * @param object $config The CiviCRM config object.
	 */
	public function civicrm_config( &$config ) {

		// Add CiviCRM listeners once CiviCRM is available.
		$this->register_civicrm_hooks();

	}



	/**
	 * Add listeners for CiviCRM Contact custom data operations.
	 *
	 * @since 0.5
	 */
	public function register_civicrm_hooks() {

		// Add callback for CiviCRM "hook_civicrm_custom" hook (civi.dao.postX does not fire).
		Civi::service( 'dispatcher' )->addListener(
			'hook_civicrm_custom',
			[ $this, 'callback_contact_updated' ],
			-100 // Default priority.
		);

	}



	/**
	 * Remove listeners from CiviCRM Contact custom data operations.
	 *
	 * @since 0.5
	 */
	public function unregister_civicrm_hooks() {

		// Remove callback for CiviCRM "postInsert" hook.
		Civi::service( 'dispatcher' )->removeListener(
			'hook_civicrm_custom',
			[ $this, 'callback_contact_updated' ]
		);

	}



	/**
	 * Creates a Term in the "taxonomy" category.
	 *
	 * @since 0.5
	 *
	 * @param array $taxonomy The taxonomy definition
	 * @param array $new_term The CiviCRM OptionValue to make a term.
	 * @return array $result Array containing category Term data.
	 */
	public function term_create( $taxonomy, $new_term ) {

		// Sanity check.
		if ( ! is_array( $new_term ) ) {
			return false;
		}

		// Define description if present.
		$description = isset( $new_term['description'] ) ? $new_term['description'] : '';

		// Construct args.
		$args = [
			'slug' => sanitize_title( $new_term['name'] ),
			'description' => $description,
		];

		// Insert it.
		$result = wp_insert_term( $new_term['label'], $taxonomy['name'], $args );

		// If all goes well, we get: array( 'term_id' => 12, 'term_taxonomy_id' => 34 )
		// If something goes wrong, we get a WP_Error object.
		if ( is_wp_error( $result ) ) {
			return false;
		}

		// Add the CiviCRM Option Value ID to the Term's meta.
		$this->term_meta_set( $taxonomy, $result['term_id'], (int) $new_term['id'] );

		// --<
		return $result;

	}



	/**
	 * Updates a Term in the "taxonomy" category.
	 *
	 * @since 0.5
	 *
	 * @param array $taxonomy The taxonomy definition
	 * @param array $new_term The CiviCRM OptionValue to make a term.
	 * @return integer|bool $term_id The ID of the updated OptionValue category Term.
	 */
	public function term_update( $taxonomy, $new_term ) {

		// Sanity check.
		if ( ! is_array( $new_term ) ) {
			return false;
		}

		// First, query "term meta".
		$term = $this->term_get_by_meta( $taxonomy, $new_term['id'] );

		// If the query produces a result.
		if ( $term instanceof WP_Term ) {

			// Grab the found Term ID.
			$term_id = $term->term_id;

		}

		// If we don't get one.
		if ( empty( $term_id ) ) {

			// Create Term,
			$result = $this->term_create( $taxonomy, $new_term );

			// How did we do?
			if ( $result === false ) {
				return $result;
			}

			// --<
			return $result['term_id'];

		}

		// Define description if present.
		$description = isset( $new_term['description'] ) ? $new_term['description'] : '';

		// Construct Term.
		$args = [
			'name' => $new_term['label'],
			'slug' => sanitize_title( $new_term['name'] ),
			'description' => $description,
		];

		// Update Term.
		$result = wp_update_term( $term_id, $taxonomy['name'], $args );

		// If all goes well, we get: array( 'term_id' => 12, 'term_taxonomy_id' => 34 )
		// If something goes wrong, we get a WP_Error object.
		if ( is_wp_error( $result ) ) {
			return false;
		}

		// --<
		return $result['term_id'];

	}



	// -------------------------------------------------------------------------



	/**
	 * Get the Term taxonomy category for a given CiviCRM OptionValue ID.
	 *
	 * @param array $taxonomy The taxonomy definition
	 * @param integer $option_value_id The numeric ID of the CiviCRM OptionValue.
	 * @return WP_Term|bool $term The Term object, or false on failure.
	 *@since 0.5
	 *
	 */
	public function term_get_by_meta($taxonomy, $option_value_id ) {

		// Query Terms for the Term with the ID of the CiviCRM OptionValue in meta data.
		$args = [
			'hide_empty' => false,
			'meta_query' => [
				[
					'key' => $this->term_meta_key,
					'value' => $option_value_id,
					'compare' => '=',
				],
			],
		];

		// Get what should only be a single Term.
		$terms = get_terms( $taxonomy['name'], $args );
		if ( empty( $terms ) ) {
			return false;
		}

		// Log a message and bail if there's an error.
		if ( is_wp_error( $terms ) ) {
			$e = new \Exception();
			$trace = $e->getTraceAsString();
			error_log( print_r( [
				'method' => __METHOD__,
				'message' => $terms->get_error_message(),
				'terms' => $terms,
				'option_value_id' => $option_value_id,
				'backtrace' => $trace,
			], true ) );
			return false;
		}

		// If we get more than one, WTF?
		if ( count( $terms ) > 1 ) {
			return false;
		}

		// Init return.
		$term = false;

		// Grab Term data.
		if ( count( $terms ) === 1 ) {
			$term = array_pop( $terms );
		}

		// --<
		return $term;

	}



	/**
	 * Get CiviCRM Option Value for a Term.
	 *
	 * @since 0.5
	 *
	 * @param integer $term_id The numeric ID of the Term.
	 * @return integer|bool $option_value_id The ID of the CiviCRM OptionValue, or false on failure.
	 */
	public function term_meta_get( $term_id ) {

		// Get the CiviCRM Option Value ID from the Term's meta.
		$option_value_id = get_term_meta( $term_id, $this->term_meta_key, true );

		// Bail if there is no result.
		if ( empty( $option_value_id ) ) {
			return false;
		}

		// --<
		return $option_value_id;

	}



	/**
	 * Add meta data to a Term in the Option Value category.
	 *
	 * @since 0.5
	 *
	 * @param array $taxonomy The taxonomy definition
	 * @param integer $term_id The numeric ID of the Term.
	 * @param integer $option_value_id The numeric ID of the CiviCRM Option Value.
	 * @return integer|bool $meta_id The ID of the meta, or false on failure.
	 */
	public function term_meta_set( $taxonomy, $term_id, $option_value_id ) {

		// Add the CiviCRM Option Value ID to the Term's meta.
		$meta_id = add_term_meta( $term_id, $this->term_meta_key, intval( $option_value_id ), true );

		// Log something if there's an error.
		if ( $meta_id === false ) {

			/*
			 * This probably means that the Term already has its Term meta set.
			 * Uncomment the following to debug if you need to.
			 */

			/*
			$e = new \Exception();
			$trace = $e->getTraceAsString();
			error_log( print_r( [
				'method' => __METHOD__,
				'message' => __( 'Could not add Term meta', 'civicrm-wp-profile-sync' ),
				'term_id' => $term_id,
				'option_value_id' => $option_value_id,
				'backtrace' => $trace,
			], true ) );
			*/

		}

		// Log a message if the Term ID is ambiguous between Taxonomies.
		if ( is_wp_error( $meta_id ) ) {
			$e = new \Exception();
			$trace = $e->getTraceAsString();
			error_log( print_r( [
				'method' => __METHOD__,
				'message' => $meta_id->get_error_message(),
				'term_id' => $term_id,
				'option_value_id' => $option_value_id,
				'backtrace' => $trace,
			], true ) );
			return false;
		}

		// --<
		return $meta_id;

	}



	// -------------------------------------------------------------------------



	/**
	 * Callback for the CiviCRM 'hook_civicrm_custom' hook.
	 *
	 * @since 0.5
	 *
	 * @param object $event The event object.
	 * @param string $hook The hook name.
	 */
	public function callback_contact_updated( $event, $hook ) {

		if ($hook === 'hook_civicrm_custom') {
			$field1 = reset($event->params);
			if ($field1['entity_table'] === 'civicrm_contact') {
				$this->cpt->contact_sync_to_post(['objectId' => $field1['entity_id']]);
			}
		}

	}



	/**
	 * Syncs all CiviCRM Option Values to Terms in the Custom Taxonomy.
	 *
	 * @since 0.5
	 */
	public function optiongroups_sync_to_terms() {

		foreach ($this->cpt->taxonomies as $taxonomy) {
			// Get all CiviCRM OptionValues.
			$optionValues = $this->get_all($taxonomy);
			if (empty($optionValues)) {
				return;
			}

			// Create (or update) the corresponding Terms.
			foreach ( $optionValues as $optionValue ) {
				$this->term_update( $taxonomy, $optionValue );
			}
		}

	}

	/**
	 * Get all terms for taxonomy
	 *
	 * @since 0.5
	 *
	 * @param array $taxonomy The taxonomy definition
	 * @return array $terms The array of all terms
	 */
	public function get_all($taxonomy) {

		// Only do this once.
		static $pseudocache;
		if ( isset( $pseudocache[$taxonomy['name']] ) ) {
			return $pseudocache[$taxonomy['name']];
		}

		// Init return.
		$terms = [];

		// Try and init CiviCRM.
		if ( ! $this->civicrm->is_initialised() ) {
			return $terms;
		}

		$optionValues = \Civi\Api4\OptionValue::get(FALSE)
			->addWhere('option_group_id:name', '=', $taxonomy['option_group_name'])
			->addOrderBy('label', 'ASC')
			->execute()
			->indexBy('id');

		// Bail if there are no results.
		if ( empty( $optionValues->count() ) ) {
			return $terms;
		}

		// The result set is what we're after.
		$terms = $optionValues->getArrayCopy();

		// Maybe add to pseudo-cache.
		if ( ! isset( $pseudocache[$taxonomy['name']] ) ) {
			$pseudocache[$taxonomy['name']] = $terms;
		}

		// --<
		return $terms;

	}



} // Class ends.
