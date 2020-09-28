<?php
/**
 * Plugin Name:       Chef's Corner NJ Custom Plugin
 * Plugin URI:        https://stellarwebdev.com/
 * Description:       A custom plugin specifically designed for Chef's Corner NJ (https://chefscornernj.com/)
 * Version:           1.0.0
 * Requires at least: 5.3.4 
 * Requires PHP:      7.2.0
 * Author:            Stellar Webdev
 * Author URI:        https://stellarwebdev.com/
 * Text Domain:       chefs-corner
 */
 
 defined( 'ABSPATH' ) || exit;
 
if (!defined( 'CHEFS_CORNER_PLUGIN_FILE')) {
	define('CHEFS_CORNER_PLUGIN_FILE', __FILE__ );
}

if (!defined( 'CHEFS_CORNER_PLUGIN_DIR')) {
	define('CHEFS_CORNER_PLUGIN_DIR', plugin_dir_path(__FILE__));
}

if (!defined( 'CHEFS_CORNER_PLUGIN_URL')) {
	define('CHEFS_CORNER_PLUGIN_URL', plugin_dir_url(__FILE__));
}

if (!defined( 'CHEFS_CORNER_DATA_DIR')) {
	define('CHEFS_CORNER_DATA_DIR', CHEFS_CORNER_PLUGIN_DIR . '_data');
}

if (!defined( 'CHEFS_CORNER_LOG_FILE')) {
	define('CHEFS_CORNER_LOG_FILE', CHEFS_CORNER_DATA_DIR . '/log.txt');
}

if (!defined( 'CHEFS_CORNER_AUDIT_FILE')) {
	define('CHEFS_CORNER_AUDIT_FILE', CHEFS_CORNER_DATA_DIR . '/audit.txt');
}

require_once(plugin_dir_path( __FILE__ ) . 'woo-includes/woo-functions.php');

if (!is_woocommerce_active()) {
	return;
}

load_plugin_textdomain('chefs-corner', false, dirname(plugin_basename(__FILE__)) . '/');

class ChefsCorner {

    protected $plugin_name;
    protected $version;
    protected $cc_aq_wc_parser = null;

    public function __construct($version) {
        $this->plugin_name = 'chefs-corner';
        $this->version = $version;

        register_activation_hook(CHEFS_CORNER_PLUGIN_FILE, array(&$this, 'activate'));
        register_deactivation_hook(CHEFS_CORNER_PLUGIN_FILE, array(&$this, 'deactivate'));

        add_action('woocommerce_init', array(&$this, 'woocommerce_loaded'));
        add_action('plugins_loaded', array(&$this, 'plugins_loaded'));

        add_action('cc_migrate_from_aq', array(&$this, 'migrate'));

        add_action('plugins_loaded', array($this, 'init'));
    }

    public function init() {
		$this->load_files();

		$this->cc_aq_wc_parser = new CC_AQ_WC_Parser();
	}

    public function activate() {
        require_once plugin_dir_path( __FILE__ ) . 'includes/class-cc-aq-wc-activator.php';
        CC_AQ_WC_Activator::activate();
    }

    public function deactivate() {
        require_once plugin_dir_path( __FILE__ ) . 'includes/class-cc-aq-wc-deactivator.php';
        CC_AQ_WC_Deactivator::deactivate();
    }

    public function woocommerce_loaded() {
        $this->init_objects();
    }

    public function migrate() {
        $this->cc_aq_wc_parser->data(array('handler_type' => 'init'))->dispatch();
    }

    public function plugins_loaded() {
    }

    private function init_objects() { 
        new CC_Admin($this->plugin_name, $this->version, $this->cc_aq_wc_parser);
        new CC_Public($this->plugin_name, $this->version);
    }
    
    private function load_files() {
        require_once CHEFS_CORNER_PLUGIN_DIR . 'admin/class-cc-admin.php';
        require_once CHEFS_CORNER_PLUGIN_DIR . 'public/class-cc-public.php';
        require_once CHEFS_CORNER_PLUGIN_DIR . 'includes/class-cc-aq-wc-parser.php';
    }

    public static function instance($version) {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self($version);
		}
		return self::$instance;
	}
}

$GLOBALS['chefs_corner'] = new ChefsCorner('1.0.0');