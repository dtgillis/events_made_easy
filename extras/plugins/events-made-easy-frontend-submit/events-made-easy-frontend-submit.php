<?php
/*
Plugin Name: Events Made Easy Frontend Submit
Plugin URI: https://github.com/halles/events-manager-extended-frontend-submit/
Description: Displays a form in a page where users can submit events for publishing. Heavily Based on Code from malo.conny at http://bueltge.de/
Author: Matías Halles
Version: 0.3.0
Author URI: http://halles.cl/
License: GNU General Public License
*/

/* License Stuff Goes Here */

/**
 *  Default Data used by the plugin
 *
 */

$emefs_event_data = array(
	"event_name" => '',
	"event_status" => 5,
	"event_start_date" => '',
	"event_end_date" => '',
	"event_start_time" => '00:00',
	"event_end_time" => '00:00',
	"event_rsvp" => 0,
	"rsvp_number_days" => 0,
	"registration_requires_approval" => 0,
	"registration_wp_users_only" => 0,
	"event_seats" => 0,
	"event_contactperson_id" => '-1',
	"event_notes" => '',
	'event_page_title_format' => '',
	'event_single_event_format' => '',
	'event_contactperson_email_body' => '',
	'event_respondent_email_body' => '',
	'event_url' => '',
	'event_category_ids' => '',
	'event_attributes' => 'a:0:{}',
	'location_id' => '',
	'location_name' => '',
	'location_address' => '',
	'location_town' => '',
	'location_latitude' => 0,
	'location_longitude' => 0,
);

$emefs_event_errors = array(
	"event_name" => false,
	"event_status" => false,
	"event_start_date" => false,
	"event_end_date" => false,
	"event_start_time" => false,
	"event_end_time" => false,
	"event_time" => false,
	"event_rsvp" => false,
	"rsvp_number_days" => false,
	"registration_requires_approval" => false,
	"registration_wp_users_only" => false,
	"event_seats" => false,
	"event_contactperson_id" => false,
	"event_notes" => false,
	'event_page_title_format' => false,
	'event_single_event_format' => false,
	'event_contactperson_email_body' => false,
	'event_respondent_email_body' => false,
	'event_url' => false,
	'event_category_ids' => false,
	'event_attributes' => false,
	'location_id' => false,
	'location_name' => false,
	'location_address' => false,
	'location_town' => false,
	'location_latitude' => false,
	'location_longitude' => false,
);

$emefs_has_errors = false;

$emefs_config = array(
	'success_page' => false,
	'auto_publish' => false,
	'public_submit' => true,
	'public_not_allowed_page' => false,
	'enabled' => false, // Do not override
	'display_notice' => false, // Do not override
);

class EMEFS {

	/**
	 * Function that loads up configuration, sets up hooks, and all, on the condition that EME is activated as well.
	 *
	 **/
	
	public static function init() {
		global $emefs_config;
		
      if (function_exists('is_multisite') && is_multisite() && array_key_exists('events-made-easy/events-manager.php',get_site_option('active_sitewide_plugins'))) {
			$emefs_config['enabled'] = true;
		} elseif (in_array('events-made-easy/events-manager.php', apply_filters('active_plugins', get_option( 'active_plugins' )))) {
			$emefs_config['enabled'] = true;
		} else {
			$emefs_config['display_notice'] = true;
		}
		
		if (!is_admin() && $emefs_config['enabled']) {
								
			$config_filename = locate_template(array(
				'events-made-easy-frontend-submit/config.php',
				'emefs/config.php',
				'events-made-easy/config.php',
				'eme/config.php',
			));
			
			if (!empty($config_filename)) {
				include($config_filename);
				$emefs_config = array_merge($emefs_config, $config);
			}
			
			add_action('template_redirect', array(__CLASS__, 'pageHasForm'));
			
			self::processForm();
			self::registerAssets();
		
		}else{
		
			/** Dependencies Notice **/
			
			add_action('admin_notices', array(__CLASS__, 'do_dependencies_notice'));
		
		}
		
	}
	
	public static function pageHasForm() { 
		global $wp_query, $emefs_config;
		if ( is_page() || is_single() ) {
			$post = $wp_query->get_queried_object();
			if ( false !== strpos($post->post_content, '[submit_event_form]') ) {
				if(!$emefs_config['public_submit'] && !current_user_can('edit_posts')){
					wp_redirect(get_permalink($emefs_config['public_not_allowed_page']));
				}
				
				/** Display Form Shortcode & Wrapper **/
				add_shortcode( 'submit_event_form', array(__CLASS__, 'deployForm'));
				
				/** Scripts and Styles **/
				add_action( 'wp_print_scripts', array(__CLASS__, 'printScripts') );
				add_action( 'wp_print_styles', array(__CLASS__, 'printStyles') );
			}
		}
	}
	
	/**
	 * Tells user to activate EME before using EMEFS
	 *
	 **/
	
	public static function do_dependencies_notice() {
		global $emefs_config;
		
		if ($emefs_config['display_notice'] == true) {
			$message = __( "The Events Made Easy Frontend Submit plugin is an extension to the Events Made Easy plugin, which has to be installed and activated first. The plugin has been deactivated.", 'emefs' );
			echo sprintf('<div class="error"><p>%s</p></div>', $message);
		}
	}

	/**
	 * Function that processes the form submitted data.
	 *
	 */

	public static function processForm() {
	
		global $emefs_event_errors, $emefs_event_data, $emefs_has_errors, $emefs_config;
		
		if (!$emefs_config['success_page']) {
			return false;
		}
			
		if ( 'POST' == $_SERVER['REQUEST_METHOD'] && !empty( $_POST['event']['action'] ) && wp_verify_nonce( $_POST['new-event'], 'action_new_event' ) ) {
			
			$hasErrors = false;
			
			$event_data = $_POST['event'];
			
			if ( isset($event_data['event_name']) && !empty($event_data['event_name']) ) { 
				$event_data['event_name'] = esc_attr( $event_data['event_name'] );
			} else {
				$emefs_event_errors['event_name'] = __('Please enter a name for the event', 'emefs');
			}
			
			if ( isset($event_data['event_start_date']) && !empty($event_data['event_start_date']) ) { 
				$event_data['event_start_date'] = esc_attr( $event_data['event_start_date'] );
			} else {
				$emefs_event_errors['event_start_date'] = __('Enter the event\'s start date', 'emefs');
			}
			
			if ( isset($event_data['event_start_time']) && !empty($event_data['event_start_time']) ) { 
				$event_data['event_start_time'] = date ("H:i:00", strtotime ($event_data['event_start_time']));
			} else {
				$event_data['event_start_time'] = '00:00';
			}
			
			if ( isset($event_data['event_end_time']) && !empty($event_data['event_end_time']) ) { 
				$event_data['event_end_time'] = date ("H:i:00", strtotime ($event_data['event_end_time']));
			} else {
				$event_data['event_end_time'] = $event_data['event_start_time'];
			}
			
			if ( isset($event_data['event_end_date']) && !empty($event_data['event_end_date']) ) { 
				$event_data['event_end_date'] = esc_attr( $event_data['event_end_date'] );
			} else {
				$event_data['event_end_date'] = $event_data['event_start_date'];
			}
			
			$time_start = strtotime($event_data['event_start_date'].' '.$event_data['event_start_time']);
			$time_end = strtotime($event_data['event_end_date'].' '.$event_data['event_end_time']);
			
			if(!$time_start){
				$emefs_event_errors['event_start_time'] = __('Check the start\'s date and time', 'emefs');
			}
			
			if(!$time_end){
				$emefs_event_errors['event_end_time'] =  __('Check the end\'s date and time', 'emefs');
			}
			
			if($time_start && $time_end && $time_start > $time_end){
				$emefs_event_errors['event_time'] =  __('The event\'s end must be <strong>after</strong> the event\'s start', 'emefs');
			}
			
			if ( isset($event_data['event_notes']) && !empty($event_data['event_notes']) ) { 
				$event_data['event_notes'] = esc_attr( $event_data['event_notes'] ); 
			} else { 
				$emefs_event_errors['event_notes'] = __('Please enter a description for the event', 'emefs'); 
			}
			
			if ( isset($event_data['event_category_ids']) && !empty($event_data['event_category_ids']) && $event_data['event_category_ids'] != 0 ) { 
				$event_data['event_category_ids'] = (int) esc_attr( $event_data['event_category_ids'] ); 
			} else { 
				$emefs_event_errors['event_category_ids'] = __('Please select an Event Category', 'emefs');
			}
			 
			$event_data['event_contactperson_email_body'] = esc_attr( $event_data['event_contactperson_email_body'] );
			
			$event_data['event_url'] = esc_url( $event_data['event_url'] );
			
			$event_data = self::processLocation($event_data);
			
			foreach ($emefs_event_errors as $error) {
				if($error){
					$emefs_has_errors = true;
					break;
				}	
			}
			
			if ( !$emefs_has_errors ) {
			
				$emefs_event_data_compiled = array_merge($emefs_event_data, $event_data);
				unset($emefs_event_data_compiled['action']);
				
				foreach ($emefs_event_data_compiled as $key => $value) {
					if (strpos($key,'location') !== false && $key != 'location_id') {
						unset($emefs_event_data_compiled[$key]);
						$location_data[$key] = $value;
					}
				}
				
				if ($emefs_config['auto_publish']) {
					$emefs_event_data_compiled['event_status'] = $emefs_config['auto_publish'];
				}

				if ($event_id = eme_db_insert_event($emefs_event_data_compiled)) {
					if ($emefs_config['auto_publish']) {
						wp_redirect(html_entity_decode(eme_event_url(eme_get_event($event_id))));
					} else {
						wp_redirect(get_permalink($emefs_config['success_page']));
					}
					exit;
				} else {
					$emefs_has_errors = true;
				}
				
			} else {
				$emefs_event_data = array_merge($emefs_event_data, $event_data);	
			}
		
		}
		
	}
	
	/** 
	 *  Function that processes the data for a new location
	 *  
	 */
	
	public static function processLocation($event_data) {
	
		global $wpdb;

		if ( isset($event_data['location_name']) && '' != $event_data['location_name'] ) {
			$event_data['location_name'] = esc_attr( $event_data['location_name'] );
		}
		
		if ( isset($event_data['location_address']) && '' != $event_data['location_address'] ) {
			$event_data['location_address'] = esc_attr( $event_data['location_address'] );
		}
		
		if ( isset($event_data['location_town']) && '' != $event_data['location_town'] ) {
			$event_data['location_town'] = esc_attr( $event_data['location_town'] );
		}
		
		if ( !empty($event_data['location_name']) && !empty($event_data['location_address']) && !empty($event_data['location_town'])) {
		
			$locations_table = $wpdb->prefix . 'dbem_locations';
			$sql = sprintf("SELECT * FROM %s WHERE location_town = '%s' AND location_address = '%s'", $locations_table, $event_data['location_town'], $event_data['location_address']);
			$location = $wpdb->get_row($sql, ARRAY_A);
			
			if ( !$location['location_id'] ) {
				$location = array (
					'location_name' => $event_data['location_name'],
					'location_address' => $event_data['location_address'],
					'location_town' => $event_data['location_town'],
					'location_latitude' => $event_data['location_latitude'],
					'location_longitude' => $event_data['location_longitude'],
				);
				$location = eme_insert_location($location);
			}
			
			$event_data['location_id'] = $location['location_id'];
			
		}
		
		return $event_data;
	}
	
		
	/**
	 *  Prints out the Submitting Form
	 *
	 */
	
	public static function deployForm($atts, $content) {
		global $emefs_event_errors, $emefs_event_data, $emefs_config;
		
		if (!$emefs_config['success_page']) {
			?>
			<div class="emefs_error">
				<h2><?php _e('Basic Configuration is Missing', 'emefs'); ?></h2>
				<p>You have to configure the page where successful submissions will be redirected to.</p>
				<p>
					Please refer to the <a href="https://github.com/halles/events-manager-extended-frontend-submit/wiki/Customization" title="Customization Page">Customization Page</a> in order to set the basic parameter with which <strong>Events Made Easy Frontend Submit</strong> will run.
				</p>
			</div>
			<?php
			return false;
		}
		
		if (!$emefs_config['public_submit'] && !$emefs_config['public_not_allowed_page']) {
			?>
			<div class="emefs_error">
				<h2><?php _e('Basic Configuration is Missing', 'emefs'); ?></h2>
				<p>Since you have chosen no to accept public submissions. You have to select a page where to redirect unauthorized users.</p>
				<p>
					Please refer to the <a href="https://github.com/halles/events-manager-extended-frontend-submit/wiki/Customization" title="Customization Page">Customization Page</a> in order to set the basic parameter with which <strong>Events Made Easy Frontend Submit</strong> will run.
				</p>
			</div>
			<?php
			return false;
		}
		
		$filename = locate_template(array(
			'events-made-easy-frontend-submit/form.php',
			'emefs/form.php',
			'events-made-easy/form.php',
			'eme/form.php',
		));
		if (empty($filename)) {
			$filename = 'templates/form.php';
		}
      // check if the user wants AM/PM or 24 hour notation
      $time_format = get_option('time_format');
      $show24Hours = 'true';
      if (preg_match ( "/a/i", $time_format ))
         $show24Hours = 'false';

		ob_start();
		require($filename);
		?>
		<script type="text/javascript">
		jQuery(document).ready( function(){
			emefs_autocomplete_url = "<?php bloginfo('url'); ?>/wp-content/plugins/events-made-easy/locations-search.php";
			emefs_gmap_enabled = 1;
			emefs_gmap_hasSelectedLocation = <?php echo ($emefs_event_data['location_id'])?'1':'0'; ?>;
			emefs_deploy(<?php echo $show24Hours; ?>);
		});
		</script>
		<?php
		$form = ob_get_clean();
		return $form;
	}
	

	
	/**
	 *  Prints fields which act as security and blocking methods
	 *  preventing unwanted submitions.
	 *
	 */
	
	public static function end_form($submit = 'Submit Event') {
		echo sprintf('<input type="submit" value="%s" id="submit" />', __($submit));
		echo '<input type="hidden" name="event[action]" value="new_event" />';
		wp_nonce_field( 'action_new_event', 'new-event' );
	}
	
	/**
	 *  Prints event data fields (not location data)
	 *
	 */
	
	public static function field($field = false, $type = 'text', $field_id = false, $more = null) {
		global $emefs_event_data;
		
		if (!$field || !isset($emefs_event_data[$field]))
			return false;
		
		if (is_array($field)) {
			$field = $field[0];
			$context = $field[1]; 
		} else {
			$context = 'event';
		}
		
		switch($field) {
			case 'event_notes':
				$type = 'textarea';
				break;
			case 'event_category_ids':
				$type = ($type != 'radio')?'select':'radio';
				break;
			case 'location_latitude':
			case 'location_longitude':
				$type = 'hidden';
				break;
			case 'event_start_time':
			case 'event_end_time':
				$more = 'readonly="readonly"';
			default:
				$type = 'text';
		}
		
		$html_by_type = array(
			'text' => '<input type="text" id="%s" name="event[%s]" value="%s" %s/>',
			'textarea' => '<textarea id="%s" name="event[%s]">%s</textarea>',
			'hidden' => '<input type="hidden" id="%s" name="event[%s]" value="%s" %s />',
		);
		
		$field_id = ($field_id)?$field_id:$field;
	
		switch($type) {
			case 'text':
			case 'textarea':
			case 'hidden':
				echo sprintf($html_by_type[$type], $field_id, $field, $emefs_event_data[$field], $more);
				break;
			case 'select':
				echo self::getCategoriesSelect();
				break;
			case 'radio':
				echo self::getCategoriesRadio();
				break;
		}
	}
	
	/**
	 *  Prints event data fields error messages (not location data)
	 *
	 */
	
	public static function error($field = false, $html = '<span class="error">%s</span>') {
		global $emefs_event_errors;
		if (!$field || !$emefs_event_errors[$field])
			return false;
		echo sprintf($html, $emefs_event_errors[$field]);
	}
	
	/**
	 *  Wrapper function to get categories form eme
	 *
	 */
	
	public static function getCategories() {
		$categories = eme_get_categories();
      if (has_filter('emefs_categories_filter')) $categories=apply_filters('emefs_categories_filter',$categories);
	}
	
	/**
	 *  Function that creates and returns a radio input set from the existing categories
	 *
	 */
	
	public static function getCategoriesRadio() {
		global $emefs_event_data;
		
		$categories = self::getCategories();
		$category_radios = array();
		if ( $categories ) {
			$category_radios[] = '<input type="hidden" name="event[event_category_ids]" value="0" />';
			foreach ($categories as $category){
				$checked = ($emefs_event_data['event_category_ids'] == $category['category_id'])?'checked="checked"':'';
				$category_radios[] = sprintf('<input type="radio" id="event_category_ids_%s" value="%s" name="event[event_category_ids]" %s />', $category['category_id'], $category['category_id'], $checked);
				$category_radios[] = sprintf('<label for="event_category_ids_%s">%s</label><br/>', $category['category_id'], $category['category_name']);
			}
		}
		
		return implode("\n", $category_radios);	
	}
	
	/**
	 *  Prints what self::getCategoriesRadio returns
	 *
	 */
	
	public static function categoriesRadio() {
		echo self::getCategoriesRadio();
	}
	
	/**
	 *  Function that creates and returns a select input set from the existing categories
	 *
	 */
	
	public static function getCategoriesSelect($select_id = 'event_category_ids') {
		global $emefs_event_data;
		
		$category_select = array();
		$category_select[] = sprintf('<select id="%s" name="event[event_category_ids]" >', $select_id);
		$categories = self::getCategories();
		if ( $categories ) {
			$category_select[] = sprintf('<option value="%s">%s</option>', 0, '--');
			foreach ($categories as $category){
				$selected = ($emefs_event_data['event_category_ids'] == $category['category_id'])?'selected="selected"':'';
				$category_select[] = sprintf('<option value="%s" %s>%s</option>', $category['category_id'], $selected, $category['category_name']);
			}
		}
		$category_select[] = '</select>';
		return implode("\n", $category_select);
		
	}
	
	/**
	 *  Prints what self::getCategoriesSelect returns
	 *
	 */
	
	public static function categoriesSelect() {
		echo self::getCategoriesSelect();
	}
	
	/**
	 *  Sets up style and scripts assets the plugin uses
	 *
	 */

	public static function registerAssets() {
		global $emefs_config;

		wp_register_script( 'jquery-ui-datepicker', EME_PLUGIN_URL.'js/jquery-ui-datepicker/ui.datepicker.js', array('jquery-ui-core'));
		wp_register_script( 'jquery-timeentry', EME_PLUGIN_URL.'js/timeentry/jquery.timeentry.js', array('jquery'));
		
		wp_register_script( 'google-maps', 'http://maps.google.com/maps/api/js?v=3.1&sensor=false');
		
		wp_register_script( 'jquery-autocomplete-bgiframe', EME_PLUGIN_URL.'js/jquery-autocomplete/lib/jquery.bgiframe.min.js', array('jquery'));
		wp_register_script( 'jquery-autocomplete-ajaxqueue', EME_PLUGIN_URL.'js/jquery-autocomplete/lib/jquery.ajaxQueue.js', array('jquery'));
		wp_register_script( 'jquery-autocomplete', EME_PLUGIN_URL.'js/jquery-autocomplete/jquery.autocomplete.min.js', array('jquery', 'jquery-autocomplete-bgiframe', 'jquery-autocomplete-ajaxqueue'));
		
		wp_register_script( 'emefs', WP_PLUGIN_URL.'/events-made-easy-frontend-submit/emefs.js', array('jquery-ui-datepicker', 'jquery-timeentry', 'jquery-autocomplete', 'google-maps'));
      	
		$style_filename = locate_template(array(
			'events-made-easy-frontend-submit/style.css',
			'emefs/style.css',
			'events-made-easy/style.css',
			'eme/style.css',
		));
		
		if(empty($style_filename)){
			$style_filename = WP_PLUGIN_URL.'/events-made-easy-frontend-submit/templates/style.css';
		}else{
			$style_filename = get_bloginfo('url').'/'.str_replace(ABSPATH, '', $style_filename);
		}
		
		wp_register_style( 'emefs', $style_filename );
		wp_register_style( 'emefs-internal', WP_PLUGIN_URL.'/events-made-easy-frontend-submit/templates/style.internal.css');
		wp_register_style( 'jquery-ui-datepicker', EME_PLUGIN_URL.'js/jquery-ui-datepicker/ui.datepicker.css');
		wp_register_style( 'jquery-autocomplete', EME_PLUGIN_URL.'js/jquery-autocomplete/jquery.autocomplete.css');
		
	}
	
	/**
	 *  Deliver scripts for output on the theme 
	 *
	 */
	
	public static function printScripts() {
		if (!is_admin()) {
			wp_enqueue_script( 'emefs' );
		}
	}
	
	/**
	 *  Deliver styles for output on the theme 
	 *
	 */
	
	public static function printStyles() {
		if (!is_admin()) {
			wp_enqueue_style('emefs');
			wp_enqueue_style('emefs-internal');
			wp_enqueue_style('jquery-ui-datepicker');
			wp_enqueue_style('jquery-autocomplete');
		}
	}

}

add_action('init', array('EMEFS', 'init'), 1);
