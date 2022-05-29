<?php
/**
 * CiviCRM "The Register" Custom Post Type Class.
 *
 * Provides "The Register" Custom Post Type.
 *
 * @package CiviCRM_WP_Profile_Sync
 * @since 0.5
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;




/**
 * "The Register" Custom Post Type Class.
 *
 * A class that encapsulates a Custom Post Type for "The Register".
 *
 * @since 0.1
 */
class CiviCRM_Greenregister_TheRegister_CPT {

	/**
	 * Plugin object.
	 *
	 * @since 0.1
	 * @access public
	 * @var object $plugin The plugin object.
	 */
	public $plugin;

	/**
	 * ACF Loader object.
	 *
	 * @since 0.1
	 * @access public
	 * @var CiviCRM_WP_Profile_Sync_ACF_Loader $acf_loader The ACF Loader object.
	 */
	public $acf_loader;

	/**
	 * CiviCRM object.
	 *
	 * @since 0.1
	 * @access public
	 * @var CiviCRM_Profile_Sync_ACF_CiviCRM $civicrm The CiviCRM object.
	 */
	public $civicrm;

	/**
	 * Parent (calling) object.
	 *
	 * @since 0.1
	 * @access public
	 * @var CiviCRM_Profile_Sync_ACF_CiviCRM_Contact $contact The parent object.
	 */
	public $contact;

	/**
	 * Taxonomy Sync object.
	 *
	 * @since 0.1
	 * @access public
	 * @var CiviCRM_Greenregister_TheRegister_CPT_Tax $tax The Taxonomy Sync object.
	 */
	public $tax;

	/**
	 * Custom Post Type name.
	 *
	 * @since 0.1
	 * @access public
	 * @var object $cpt The name of the Custom Post Type.
	 */
	public $post_type_name = 'companies';

	/**
	 * Taxonomy name.
	 *
	 * @since 0.1
	 * @access public
	 * @var array $taxonomies The name of the Custom Taxonomy.
	 */
	public $taxonomies = [];

	/**
	 * ACF identifier.
	 *
	 * @since 0.1
	 * @access public
	 * @var string $acf_slug The ACF identifier.
	 */
	public $acf_slug = 'cwps_gr_theregister';

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
	 * @since 0.1
	 *
	 * @param object $parent The parent object reference.
	 */
	public function __construct( $parent ) {

		$this->taxonomies = [
			'regions' => [
				'name' => 'regions',
				'option_group_name' => 'region_20140212150624',
				'label' => __( 'Regions', 'civicrm-wp-greenregister' ),
				'custom_field_key' => 'Organisation_Details.Region',
				'is_multiple' => false,
			],
			'professions' => [
				'name' => 'professions',
				'option_group_name' => 'profession_20140212145335',
				'label' => __( 'Professions', 'civicrm-wp-greenregister' ),
				'custom_field_key' => 'Organisation_Details.Profession',
				'is_multiple' => true,
			]
		];

		// Store references to objects.
		$this->plugin = $parent->acf->plugin;
		$this->acf_loader = $parent->acf->acf->acf_loader;
		$this->civicrm = $parent->civicrm;
		$this->contact = $parent->civicrm->contact;

		// Init when the Contact object is loaded.
		add_action( 'civicrm_wp_greenregister_loaded', [ $this, 'initialise' ] );

	}



	/**
	 * Initialise this object.
	 *
	 * @since 0.1
	 */
	public function initialise() {

		// Include files.
		$this->include_files();

		// Set up objects and references.
		$this->setup_objects();

		// Register hooks.
		$this->register_hooks();

		/**
		 * Broadcast that this class is now loaded.
		 *
		 * @since 0.1
		 */
		do_action( 'cwps/acf/civicrm/theregister-cpt/loaded' );

	}



	/**
	 * Actions to perform on plugin activation.
	 *
	 * @since 0.1
	 */
	public function activate() {

		// Pass through.
		$this->post_type_create();
		$this->taxonomies_create();

		// Get current setting data.
		$data = $this->acf_loader->mapping->setting_get( $this->post_type_name );

		// Only do this once.
		if ( ! empty( $data['synced'] ) && $data['synced'] === 1 ) {
			//return;
		}

		// Sync them.
		$this->tax->optiongroups_sync_to_terms();

		// Add/Update setting.
		$data['synced'] = 1;

		// Overwrite setting.
		$this->acf_loader->mapping->setting_update( $this->post_type_name, $data );

		// Go ahead and flush.
		flush_rewrite_rules();

	}



	/**
	 * Actions to perform on plugin deactivation (NOT deletion).
	 *
	 * @since 0.1
	 */
	public function deactivate() {

		// Flush rules to reset.
		flush_rewrite_rules();

	}



	/**
	 * Include files.
	 *
	 * @since 0.5
	 */
	public function include_files() {

		// Include class files.
		include_once CIVICRM_WP_GREENREGISTER_PATH . 'includes/cwps-gr-theregister-cpt-tax.php';

	}



	/**
	 * Set up objects.
	 *
	 * @since 0.5
	 */
	public function setup_objects() {

		// Init objects.
		$this->tax = new CiviCRM_Greenregister_TheRegister_CPT_Tax( $this );

	}



	/**
	 * Register WordPress hooks.
	 *
	 * @since 0.5
	 */
	public function register_hooks() {

		// Register Mapper hooks when enabled.
		$this->register_mapper_hooks();

		// Register CPT hooks when enabled.
		$this->register_cpt_hooks();

		// Intercept Post created from Contact events.
		add_action( 'cwps/acf/post/contact_sync_to_post', [ $this, 'contact_sync_to_post' ], 10 );

	}



	/**
	 * Register callbacks for Mapper events.
	 *
	 * @since 0.5
	 */
	public function register_mapper_hooks() {

		// Listen for events from our Mapper that require Post updates.
		add_action( 'cwps/acf/mapper/contact/delete/pre', [ $this, 'contact_pre_delete' ], 10 );
		add_action( 'cwps/acf/mapper/contact/deleted', [ $this, 'contact_deleted' ] );

	}



	/**
	 * Unregister callbacks for Mapper events.
	 *
	 * @since 0.5
	 */
	public function unregister_mapper_hooks() {

		// Remove all Mapper listeners.
		remove_action( 'cwps/acf/mapper/contact/created', [ $this, 'contact_created' ] );
		remove_action( 'cwps/acf/mapper/contact/edited', [ $this, 'contact_edited' ] );

	}



	/**
	 * Register callbacks for CPT events.
	 *
	 * @since 0.5
	 */
	public function register_cpt_hooks() {

		// Always create Post Type.
		add_action( 'init', [ $this, 'post_type_create' ] );
		add_action( 'admin_init', [ $this, 'post_type_remove_title' ] );

		// Create Taxonomy.
		add_action( 'init', [ $this, 'taxonomies_create' ] );

		// Fix hierarchical Taxonomy metabox display.
		add_filter( 'wp_terms_checklist_args', [ $this, 'taxonomy_fix_metabox' ], 10, 2 );

		// Add a filter to the wp-admin listing table.
		add_action( 'restrict_manage_posts', [ $this, 'taxonomy_filter_post_type' ] );

	}



	/**
	 * Unregister callbacks for CPT events.
	 *
	 * @since 0.5
	 */
	public function unregister_cpt_hooks() {

		// Remove all CPT listeners.
		remove_action( 'init', [ $this, 'post_type_create' ] );
		remove_action( 'admin_init', [ $this, 'post_type_remove_title' ] );
		remove_action( 'init', [ $this, 'taxonomies_create' ] );
		remove_filter( 'wp_terms_checklist_args', [ $this, 'taxonomy_fix_metabox' ] );
		remove_action( 'restrict_manage_posts', [ $this, 'taxonomy_filter_post_type' ] );

	}



	// -------------------------------------------------------------------------




	/**
	 * Intercept when a Post is been synced from a Contact.
	 *
	 * Sync any associated Terms mapped to CiviCRM Groups.
	 *
	 * @since 0.4
	 *
	 * @param array $args The array of CiviCRM Contact and WordPress Post params.
	 */
	public function contact_sync_to_post( $args ) {

		// Get organization details for this contact
		$contactDetail = \Civi\Api4\Contact::get(FALSE)
			->addSelect('custom.*')
			->addWhere('id', '=', $args['objectId'])
			->execute()
			->first();

		// Process terms for contact
		if ( ! empty( $contactDetail ) ) {

			foreach ($this->taxonomies as $taxonomy) {
				if (isset($contactDetail[$taxonomy['custom_field_key']])) {
					// Update terms
					$terms[$taxonomy['name']] = $contactDetail[$taxonomy['custom_field_key']];
				}
			}

			if ( ! empty ( $terms ) ) {
				// Sync the CiviCRM contact to WordPress Terms.
				$this->terms_update_by_contact( $contactDetail['id'], $terms );
			}

		}

	}


	/**
	 * A CiviCRM Contact's Instant Messenger Record is about to be deleted.
	 *
	 * Before an Instant Messenger Record is deleted, we need to retrieve the
	 * Instant Messenger Record because the data passed via "civicrm_post" only
	 *  contains the ID of the Instant Messenger Record.
	 *
	 * This is not required when creating or editing an Instant Messenger Record.
	 *
	 * @since 0.4
	 *
	 * @param array $args The array of CiviCRM params.
	 */
	public function contact_pre_delete( $args ) {

		// Always clear properties if set previously.
		if ( isset( $this->contact_pre ) ) {
			unset( $this->contact_pre );
		}

		// We just need the Participant ID.
		$contact_id = (int) $args['objectId'];

		// Grab the Participant record from the database.
		$contact_pre = $this->acf_loader->civicrm->contact->get_by_id( $contact_id );

		// Maybe cast previous Participant data as object and stash in a property.
		if ( ! is_object( $contact_pre ) ) {
			$this->contact_pre = (object) $contact_pre;
		} else {
			$this->contact_pre = $contact_pre;
		}

	}



	/**
	 * Delete a WordPress Post when a CiviCRM Participant has been deleted.
	 *
	 * Unusually for this plugin, it is necessary to delete the corresponding
	 * Post when a Participant (or Event Registration) is deleted in CiviCRM.
	 * When the CiviCRM record is removed, it makes no sense to keep data for
	 * historical reasons.
	 *
	 * @since 0.5
	 *
	 * @param array $args The array of CiviCRM params.
	 */
	public function contact_deleted( $args ) {

		// Bail if this is not a Contact.
		if ( $args['objectName'] != 'Contact' ) {
			return;
		}

		// Bail if we don't have a pre-delete Contact record.
		if ( ! isset( $this->contact_pre ) ) {
			return;
		}

		// We just need the Contact ID.
		$contact_id = (int) $args['objectId'];

		// Sanity check.
		if ( $contact_id != $this->contact_pre->id ) {
			return;
		}

		// Overwrite objectRef.
		$args['objectRef'] = $this->contact_pre;

		// Bail if this Contact is not mapped.
		$post_types = $this->acf_loader->civicrm->contact->is_mapped( $args['objectRef'] );
		if ( $post_types === false ) {
			return;
		}

		// Handle each Post Type in turn.
		foreach ( $post_types as $post_type ) {

			// Find the Post ID of this Post Type that this Contact is synced with.
			$post_id = false;
			$post_ids = $this->get_by_contact_id( $args['objectId'], $post_type );
			if ( ! empty( $post_ids ) ) {
				$post_id = array_pop( $post_ids );
			}
			if ( $post_id === false ) {
				continue;
			}

			// Remove WordPress Post callbacks to prevent recursion.
			$this->acf_loader->mapper->hooks_wordpress_post_remove();

			// Delete the WordPress Post if it exists.
			$this->delete_from_contact( $post_id );

			// Reinstate WordPress Post callbacks.
			$this->acf_loader->mapper->hooks_wordpress_post_add();

			// Add our data to the params.
			$args['post_type'] = $post_type;
			$args['post_id'] = $post_id;

			/**
			 * Broadcast that a WordPress Post has been deleted from Contact details.
			 *
			 * @since 0.5
			 *
			 * @param array $args The array of CiviCRM and discovered params.
			 */
			do_action( 'cwps/acf/post/contact/deleted', $args );

		}

	}


	/**
	 * Get the WordPress Post ID(s) for a given CiviCRM Contact ID and Post Type.
	 *
	 * If no Post Type is provided then an array of all synced Posts is returned.
	 *
	 * @since 0.4
	 *
	 * @param integer $contact_id The CiviCRM Contact ID.
	 * @param string $post_type The WordPress Post Type.
	 * @return array|bool $posts An array of Post IDs, or false on failure.
	 */
	public function get_by_contact_id( $contact_id, $post_type = 'any' ) {

		// Init as failed.
		$posts = false;

		/*
		 * Define args for query.
		 *
		 * We need to query multiple Post Statuses because we need to keep the
		 * linkage between the CiviCRM Entity and the Post throughout its
		 * life cycle, e.g.
		 *
		 * * Published: The default status for our purposes.
		 * * Trash: Because we want to avoid a duplicate Post being created.
		 * * Draft: When Posts are moved out of the Trash, this is their status.
		 *
		 * This may need to be revisited.
		 */
		$args = [
			'post_type' => $post_type,
			'post_status' => [ 'publish', 'trash', 'draft' ],
			'no_found_rows' => true,
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_key' => '_civicrm_acf_integration_post_contact_id',
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			'meta_value' => (string) $contact_id,
			'posts_per_page' => -1,
			'order' => 'ASC',
		];

		// Do query.
		$query = new WP_Query( $args );

		// Do the loop.
		if ( $query->have_posts() ) {
			foreach ( $query->get_posts() as $found ) {

				// Add if we want *all* Posts.
				if ( $post_type === 'any' ) {
					$posts[] = $found->ID;

					// Grab what should be the only Post.
				} elseif ( $found->post_type == $post_type ) {
					$posts[] = $found->ID;
					break;
				}

			}
		}

		// Reset Post data just in case.
		wp_reset_postdata();

		// --<
		return $posts;

	}

	/**
	 * Delete a WordPress "Contact" Post.
	 *
	 * @since 0.5
	 *
	 * @param integer $post_id The numeric ID of the WordPress Post.
	 * @return WP_Post|bool $post The deleted WordPress Post object, or false on failure.
	 */
	public function delete_from_contact( $post_id ) {

		// Delete the Post.
		$post = wp_delete_post( $post_id, true );

		// Bail on failure.
		if ( is_wp_error( $post ) || empty( $post ) ) {
			return false;
		}

		// --<
		return $post;

	}


	/**
	 * Process terms for Contacts.
	 *
	 * @since 0.4
	 *
	 * @param integer $contact_id The ID of the CiviCRM Contact.
	 * @param array $terms Array of Terms by taxonomy for the contact.
	 */
	public function terms_update_by_contact( $contact_id, $terms ) {

		if ( empty( $terms ) ) {
			return;
		}

		$taxonomies = array_keys($terms);

		// Get Term IDs that are synced to this Group ID.
		$post_ids = $this->acf_loader->post->get_by_contact_id( $contact_id, $this->post_type_name);

		if ( empty( $post_ids ) ) {
			return;
		}

		foreach ( $post_ids as $post_id ) {

			// Grab Post object.
			$post = get_post( $post_id );

			// Get all synced term IDs for the Post Type.
			$synced_terms_for_post_type = $this->synced_terms_get_for_post_type( $post->post_type );

			// Overwrite with new set of terms.
			foreach ($taxonomies as $taxonomy) {

				$optionValues = $this->tax->get_all($this->taxonomies[$taxonomy]);
				// Find the terms in this taxonomy.
				$args = [ 'taxonomy' => $taxonomy ];
				$terms_in_tax = wp_filter_object_list( $synced_terms_for_post_type, $args );

				$term_ids_for_post = [];
				foreach ( $terms_in_tax as $tax_term ) {
					if (!array_key_exists($tax_term->option_value_id, $optionValues)) {
						continue;
					}
					foreach ((array) $terms[$taxonomy] as $term) {
						if ( $optionValues[$tax_term->option_value_id]['value'] == $term ) {
							$term_ids_for_post[] = $tax_term->term_id;
						}
					}
				}

				wp_set_object_terms($post_id, $term_ids_for_post, $taxonomy, false);
				// Clear cache.
				clean_object_term_cache( $post_id, $taxonomy );
			}

		}

	}


	/**
	 * Get the synced terms for a given WordPress Post Type.
	 *
	 * @since 0.4
	 *
	 * @param string $post_type The name of WordPress Post Type.
	 * @return array|bool $terms The array of term objects, or false on failure.
	 */
	public function synced_terms_get_for_post_type( $post_type ) {

		// Get the taxonomies for this Post Type.
		$taxonomies = get_object_taxonomies( $post_type );

		// Bail if there are no taxonomies.
		if ( empty( $taxonomies ) ) {
			return [];
		}

		// Query terms in those taxonomies.
		$args = [
			'taxonomy' => $taxonomies,
			'hide_empty' => false,
			'meta_query' => [
				[
					'key' => $this->term_meta_key,
					'compare' => 'EXISTS',
				],
			],
		];

		// Grab the terms.
		$terms = get_terms( $args );

		// Bail if there are no terms or there's an error.
		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			return [];
		}

		// Let's add the Group ID to the term object.
		foreach ( $terms as $term ) {
			$term->option_value_id = $this->tax->term_meta_get( $term->term_id );
		}

		// --<
		return $terms;

	}

	// -------------------------------------------------------------------------



	/**
	 * Create our Custom Post Type.
	 *
	 * @since 0.5
	 */
	public function post_type_create() {

		// Only call this once.
		static $registered;
		if ( isset( $registered ) && $registered === true ) {
			return;
		}

		// Set up the Post Type.

		register_post_type( $this->post_type_name, [

				'labels' => [
					'name' => __( 'The Register', 'civicrm-wp-greenregister' ),
					'singular_name' => __( 'The Register', 'civicrm-wp-greenregister' )
				],
				'public' => true,
				'has_archive' => true,
				'hierarchical' => false,
				'show_ui' => true,
				'show_in_rest' => true,
				'supports' => ['title', 'thumbnail', 'editor', 'excerpt', 'custom-fields', 'author'],
				'rewrite' => array( 'slug' => 'companies' ),
				'menu_icon' => 'dashicons-admin-post'
		] );

		//flush_rewrite_rules();

		// Flag done.
		$registered = true;

	}



	/**
	 * Removes the Title Field from our Custom Post Type.
	 *
	 * @since 0.5
	 */
	public function post_type_remove_title() {

		// Remove it.
		remove_post_type_support( $this->post_type_name, 'title' );

	}



	// -------------------------------------------------------------------------



	/**
	 * Create our Custom Taxonomy.
	 *
	 * @since 0.5
	 */
	public function taxonomies_create() {

		// Only call this once.
		static $registered;
		if ( isset( $registered ) && $registered === true ) {
			return;
		}

		// Register a Taxonomy for this CPT.

		register_taxonomy(
			$this->taxonomies['regions']['name'],
			$this->post_type_name,
			[
				'hierarchical' => true,
				'label' => $this->taxonomies['regions']['label'],
				'show_ui' => true,
				'show_in_rest' => true,
				'show_admin_column' => true,
				'rewrite' => [ 'slug' => $this->taxonomies['regions']['name'] ]
			]
		);

		register_taxonomy(
			$this->taxonomies['professions']['name'],
			$this->post_type_name,
			[
				'hierarchical' => true,
				'label' => $this->taxonomies['professions']['label'],
				'show_ui' => true,
				'show_in_rest' => true,
				'show_admin_column' => true,
				'rewrite' => [ 'slug' => $this->taxonomies['professions']['name'] ]
			]
		);

		//flush_rewrite_rules();

		// Flag done.
		$registered = true;

	}



	/**
	 * Fix the Custom Taxonomy metabox.
	 *
	 * @see https://core.trac.wordpress.org/ticket/10982
	 *
	 * @since 0.5
	 *
	 * @param array $args The existing arguments.
	 * @param integer $post_id The WordPress post ID.
	 */
	public function taxonomy_fix_metabox( $args, $post_id )
	{

		foreach ($this->taxonomies as $taxonomy) {
			// If rendering metabox for our Taxonomy.
			if (isset($args['taxonomy']) && $args['taxonomy'] == $taxonomy['name']) {

				// Setting 'checked_ontop' to false seems to fix this.
				$args['checked_ontop'] = false;

			}

			// --<
			return $args;
		}

	}



	/**
	 * Add a filter for this Custom Taxonomy to the Custom Post Type listing.
	 *
	 * @since 0.5
	 */
	public function taxonomy_filter_post_type() {

		// Access current Post Type.
		global $typenow;

		// Bail if not our Post Type,
		if ( $typenow != $this->post_type_name ) {
			return;
		}

		foreach ($this->taxonomies as $taxonomy) {
			// Get Taxonomy object.
			$taxonomyObject = get_taxonomy($taxonomy['name']);

			// Show a dropdown.
			wp_dropdown_categories([
				/* translators: %s: The Taxonomy name */
				'show_option_all' => sprintf(__('Show All %s', 'civicrm-wp-profile-sync'), $taxonomyObject->label),
				'taxonomy' => $taxonomy['name'],
				'name' => $taxonomy['name'],
				'orderby' => 'name',
				'selected' => isset($_GET[$taxonomy['name']]) ? $_GET[$taxonomy['name']] : '',
				'show_count' => true,
				'hide_empty' => true,
				'value_field' => 'slug',
				'hierarchical' => 1,
			]);
		}

	}



} // Class ends.



