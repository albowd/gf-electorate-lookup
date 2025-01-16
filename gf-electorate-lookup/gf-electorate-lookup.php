<?php
/**
 * Plugin Name: Gravity Forms Australian Electorate Lookup
 * Plugin URI: 
 * Description: Adds an Australian Electorate Lookup field to Gravity Forms. Allows lookup for Federal electorates (CED), State electorates (SED) & Local Government Areas (LGA). Electoral boundary data from ABS.
 * Version: 1.2
 * Author: Al Bowd
 * Text Domain: gf-electorate-lookup
 * License: GPL2
 */

if (!defined('ABSPATH')) {
    exit;
}

// Updated version number to force cache refresh
define('GF_ELECTORATE_LOOKUP_VERSION', '1.1.' . time());
define('GF_ELECTORATE_LOOKUP_PATH', plugin_dir_path(__FILE__));
define('GF_ELECTORATE_LOOKUP_URL', plugin_dir_url(__FILE__));

add_action('gform_loaded', 'gf_electorate_lookup_init', 5);

function gf_electorate_lookup_init() {
    if (!class_exists('GF_Field')) {
        return;
    }

    class GF_Field_Electorate_Lookup extends GF_Field {
        public $type = 'electorate_lookup';
        public $boundaryType = 'CED';
        public $stateRestrictions = array();

        public function __construct($data = array()) {
            parent::__construct($data);
            $this->boundaryType = empty($data['boundaryType']) ? 'CED' : $data['boundaryType'];
            $this->stateRestrictions = empty($data['stateRestrictions']) ? array() : $data['stateRestrictions'];
        }

        public function get_form_editor_field_title() {
            return esc_attr__('Electorate Lookup', 'gf-electorate-lookup');
        }

        public function get_form_editor_button() {
            return array(
                'group' => 'standard_fields',
                'text'  => $this->get_form_editor_field_title()
            );
        }

        public function get_form_editor_field_settings() {
            return array(
                'conditional_logic_field_setting',
                'error_message_setting',
                'label_setting',
                'label_placement_setting',
                'admin_label_setting',
                'size_setting',
                'visibility_setting',
                'description_setting',
                'css_class_setting',
                'boundary_type_setting',
                'state_restrictions_setting'
            );
        }

        public function get_field_input($form, $value = '', $entry = null) {
            $id = (int) $this->id;
            $form_id = $form['id'];
            $field_id = $form_id == 0 ? "input_$id" : 'input_' . $form_id . "_$id";

            $class = $this->size;
            $css_class = trim(esc_attr($class) . ' gfield_electorate_lookup');
            $tabindex = $this->get_tabindex();
            $required_attribute = $this->isRequired ? 'aria-required="true"' : '';
            
            // Ensure boundary type is set
            $boundary_type = !empty($this->boundaryType) ? $this->boundaryType : 'CED';
            
            // Add state restrictions as data attribute
            $state_restrictions_attr = '';
            if (!empty($this->stateRestrictions)) {
                $state_restrictions_attr = sprintf(
                    'data-state-restrictions=\'%s\'',
                    esc_attr(wp_json_encode($this->stateRestrictions))
                );
            }

            $input = sprintf(
                "<input name='input_%d' id='%s' type='text' value='%s' class='%s' %s %s autocomplete='off' data-boundary-type='%s' placeholder='%s' %s />",
                $id, 
                $field_id, 
                esc_attr($value), 
                $css_class, 
                $tabindex, 
                $required_attribute, 
                esc_attr($boundary_type),
                esc_attr__('Begin typing your address...', 'gf-electorate-lookup'),
                $state_restrictions_attr
            );

            $input .= sprintf("<div id='%s_geocode_results' class='geocode-results'></div>", $field_id);

            return sprintf("<div class='ginput_container ginput_container_electorate_lookup'>%s</div>", $input);
        }
    }

    GF_Fields::register(new GF_Field_Electorate_Lookup());
}

// Add custom field settings
add_action('gform_field_standard_settings', 'add_electorate_lookup_settings', 10, 2);
function add_electorate_lookup_settings($position, $form_id) {
    if ($position == 25) {
        // Boundary Type Setting
        ?>
        <li class="boundary_type_setting field_setting">
            <label for="field_boundary_type" class="section_label">
                <?php esc_html_e('Boundary Type', 'gf-electorate-lookup'); ?>
            </label>
            <select id="field_boundary_type" onchange="SetFieldProperty('boundaryType', this.value);">
                <option value="CED"><?php esc_html_e('Federal Electorate (CED)', 'gf-electorate-lookup'); ?></option>
                <option value="SED"><?php esc_html_e('State Electoral District (SED)', 'gf-electorate-lookup'); ?></option>
                <option value="LGA"><?php esc_html_e('Local Government Area (LGA)', 'gf-electorate-lookup'); ?></option>
            </select>
        </li>
        
        <!-- State Restrictions Setting -->
        <li class="state_restrictions_setting field_setting">
            <label class="section_label">
                <?php esc_html_e('Restrict to States/Territories', 'gf-electorate-lookup'); ?>
                <?php gform_tooltip('form_field_state_restrictions'); ?>
            </label>
            <div>
                <input type="checkbox" id="field_state_nsw" value="NSW" onclick="UpdateStateRestrictions();" />
                <label for="field_state_nsw"><?php esc_html_e('New South Wales', 'gf-electorate-lookup'); ?></label>
            </div>
            <div>
                <input type="checkbox" id="field_state_vic" value="VIC" onclick="UpdateStateRestrictions();" />
                <label for="field_state_vic"><?php esc_html_e('Victoria', 'gf-electorate-lookup'); ?></label>
            </div>
            <div>
                <input type="checkbox" id="field_state_qld" value="QLD" onclick="UpdateStateRestrictions();" />
                <label for="field_state_qld"><?php esc_html_e('Queensland', 'gf-electorate-lookup'); ?></label>
            </div>
            <div>
                <input type="checkbox" id="field_state_wa" value="WA" onclick="UpdateStateRestrictions();" />
                <label for="field_state_wa"><?php esc_html_e('Western Australia', 'gf-electorate-lookup'); ?></label>
            </div>
            <div>
                <input type="checkbox" id="field_state_sa" value="SA" onclick="UpdateStateRestrictions();" />
                <label for="field_state_sa"><?php esc_html_e('South Australia', 'gf-electorate-lookup'); ?></label>
            </div>
            <div>
                <input type="checkbox" id="field_state_tas" value="TAS" onclick="UpdateStateRestrictions();" />
                <label for="field_state_tas"><?php esc_html_e('Tasmania', 'gf-electorate-lookup'); ?></label>
            </div>
            <div>
                <input type="checkbox" id="field_state_act" value="ACT" onclick="UpdateStateRestrictions();" />
                <label for="field_state_act"><?php esc_html_e('Australian Capital Territory', 'gf-electorate-lookup'); ?></label>
            </div>
            <div>
                <input type="checkbox" id="field_state_nt" value="NT" onclick="UpdateStateRestrictions();" />
                <label for="field_state_nt"><?php esc_html_e('Northern Territory', 'gf-electorate-lookup'); ?></label>
            </div>
        </li>
        <?php
    }
}

// Add setting to field object
add_action('gform_editor_js', 'editor_script_electorate_lookup_settings');
function editor_script_electorate_lookup_settings(){
    ?>
    <script type='text/javascript'>
        fieldSettings.electorate_lookup += ', .boundary_type_setting, .state_restrictions_setting';
        
        jQuery(document).on('gform_load_field_settings', function(event, field, form) {
            if (field.type == 'electorate_lookup') {
                jQuery('#field_boundary_type').val(field.boundaryType || 'CED');
                
                // Set state restriction checkboxes
                var states = field.stateRestrictions || [];
                jQuery('[id^="field_state_"]').each(function() {
                    jQuery(this).prop('checked', states.indexOf(jQuery(this).val()) !== -1);
                });
            }
        });

        function UpdateStateRestrictions() {
            var states = [];
            jQuery('[id^="field_state_"]:checked').each(function() {
                states.push(jQuery(this).val());
            });
            SetFieldProperty('stateRestrictions', states);
        }
    </script>
    <?php
}

add_action('gform_tooltips', 'add_electorate_lookup_tooltips');
function add_electorate_lookup_tooltips($tooltips) {
    $tooltips['form_field_state_restrictions'] = '<h6>State/Territory Restrictions</h6>Select which states/territories to restrict address lookup to. Leave all unchecked to allow addresses from all of Australia.';
    return $tooltips;
}

// Initialize the Settings Framework
add_action('init', function() {
    if (class_exists('GFForms')) {
        GFForms::include_addon_framework();
        
        class GF_Electorate_Lookup_Settings extends GFAddOn {
            protected $_version = GF_ELECTORATE_LOOKUP_VERSION;
            protected $_min_gravityforms_version = '2.5';
            protected $_slug = 'gf_electorate_lookup';
            protected $_path = 'gf-electorate-lookup/gf-electorate-lookup.php';
            protected $_full_path = __FILE__;
            protected $_title = 'Electorate Lookup Settings';
            protected $_short_title = 'Electorate Lookup';
            protected $_capabilities_settings_page = 'manage_options';
            protected $_capabilities_form_settings = '_noaccess_'; // Prevents form settings from showing
            protected $_capabilities_plugin_settings = 'manage_options';
            protected $_capabilities_app_settings = 'manage_options';
            
            private static $_instance = null;
            
            public static function get_instance() {
                if (self::$_instance == null) {
                    self::$_instance = new self();
                }
                return self::$_instance;
            }
            
            public function init() {
                parent::init();
            }
            
            // Remove form settings
            public function form_settings_fields($form) {
                return array();
            }
            
            // Only implement plugin-wide settings
            public function plugin_settings_fields() {
                return array(
                    array(
                        'title'  => esc_html__('Electorate Lookup Settings', 'gf-electorate-lookup'),
                        'fields' => array(
                            array(
                                'name'    => 'google_maps_api_key',
                                'tooltip' => esc_html__('Enter your Google Maps API key. This key must have the Places API enabled.', 'gf-electorate-lookup'),
                                'label'   => esc_html__('Google Maps API Key', 'gf-electorate-lookup'),
                                'type'    => 'text',
                                'class'   => 'medium',
                            ),
                        ),
                    ),
                );
            }
        }
        
        // Initialize the addon
        GF_Electorate_Lookup_Settings::get_instance();
    }
});

// Helper function to get API key
function gf_electorate_lookup_get_api_key() {
    if (class_exists('GF_Electorate_Lookup_Settings')) {
        $settings = GF_Electorate_Lookup_Settings::get_instance()->get_plugin_settings();
        return !empty($settings['google_maps_api_key']) ? $settings['google_maps_api_key'] : '';
    }
    return get_option('gf_electorate_lookup_google_maps_api_key', '');
}

// Add admin notice if API key is missing
function gf_electorate_lookup_api_key_notice() {
    $class = 'notice notice-error';
    $settings_url = admin_url('admin.php?page=gform_settings&subview=gf_electorate_lookup');
    $message = sprintf(
        __('Electorate Lookup requires a Google Maps API key. Please <a href="%s">configure it in the settings</a>.', 'gf-electorate-lookup'),
        esc_url($settings_url)
    );

    printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), $message);
}

// Enqueue scripts and styles
add_action('gform_enqueue_scripts', 'gf_electorate_lookup_enqueue_scripts', 10, 2);
function gf_electorate_lookup_enqueue_scripts($form, $is_ajax) {
    // Check if form has electorate lookup field
    $has_electorate_lookup = false;
    foreach ($form['fields'] as $field) {
        if ($field->type == 'electorate_lookup') {
            $has_electorate_lookup = true;
            break;
        }
    }

    if (!$has_electorate_lookup) return;

    $api_key = gf_electorate_lookup_get_api_key();
    
    // If no API key is set, show admin notice and return
    if (empty($api_key) && is_admin()) {
        add_action('admin_notices', 'gf_electorate_lookup_api_key_notice');
        return;
    }

    // Enqueue Google Maps API with key from settings
    wp_enqueue_script(
        'google-maps',
        'https://maps.googleapis.com/maps/api/js?key=' . esc_attr($api_key) . '&libraries=places',
        array('jquery'),
        null,
        true
    );

    // Enqueue our custom script
    wp_enqueue_script(
        'gf-electorate-lookup',
        GF_ELECTORATE_LOOKUP_URL . 'js/electorate-lookup.js',
        array('jquery', 'google-maps'),
        GF_ELECTORATE_LOOKUP_VERSION,
        true
    );

    // Pass configurations to JavaScript
    wp_localize_script('gf-electorate-lookup', 'gfElectorateLookupConfig', array(
        'debug' => WP_DEBUG,
        'boundaries' => array(
            'CED' => array(
                'url' => 'https://geo.abs.gov.au/arcgis/rest/services/ASGS2024/CED/MapServer/0/query',
                'nameField' => 'CED_NAME_2024',
                'label' => 'Federal Electorate'
            ),
            'SED' => array(
                'url' => 'https://geo.abs.gov.au/arcgis/rest/services/ASGS2024/SED/MapServer/0/query',
                'nameField' => 'sed_name_2024',
                'label' => 'State Electoral District'
            ),
            'LGA' => array(
                'url' => 'https://geo.abs.gov.au/arcgis/rest/services/ASGS2024/LGA/MapServer/0/query',
                'nameField' => 'lga_name_2024',
                'label' => 'Local Government Area'
            )
        ),
        'version' => GF_ELECTORATE_LOOKUP_VERSION
    ));

    // Enqueue our styles
    wp_enqueue_style(
        'gf-electorate-lookup',
        GF_ELECTORATE_LOOKUP_URL . 'css/electorate-lookup.css',
        array(),
        GF_ELECTORATE_LOOKUP_VERSION
    );
}