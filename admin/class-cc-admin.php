<?php

class CC_Admin {
    private $plugin_name;
    private $version;
    private $cc_aq_wc_parser;

    public function __construct($plugin_name, $version, $cc_aq_wc_parser) {
		$this->plugin_name = $plugin_name;
        $this->version = $version;
        $this->cc_aq_wc_parser = $cc_aq_wc_parser;

        $this->load_files();
        $this->init_hooks();
    }

    private function load_files() {
        require_once CHEFS_CORNER_PLUGIN_DIR . 'includes/class-cc-aq-wc-parser.php';
    }

    private function init_hooks() {
        add_action('admin_menu', array($this, 'add_admin_menu')); 
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array( $this, 'enqueue_styles' ) );
        add_action('admin_enqueue_scripts', array($this, 'enqueue_js'));
    }

    public function enqueue_styles($hook) {
		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/style.css', array(), $this->version, 'all' );
    }

    public function enqueue_js($hook) {
        wp_enqueue_script( $this->plugin_name, plugin_dir_url(__FILE__) . 'js/admin.js');
    }

    public function add_admin_menu() {
        add_menu_page($this->plugin_name, 'Chef\'s Corner', 'administrator', $this->plugin_name, array($this, 'display_admin_settings'), null, 26);

        //add_submenu_page($this->plugin_name, 'CRON Job', 'CRON Job', 'administrator', $this->plugin_name . '-cron-job', array($this, 'display_cron_job_page') );
        add_submenu_page($this->plugin_name, 'Backups', 'Backups', 'administrator', $this->plugin_name . '-backups', array($this, 'display_backup_page') );
    }

    public function display_cron_job_page() {
        if ($_POST['submit']) {
            $this->cc_aq_wc_parser->dispatch();
        }

        require_once CHEFS_CORNER_PLUGIN_DIR . '/admin/partials/cron-job-page.php';
    }

    public function display_backup_page() {
        $files = glob(CHEFS_CORNER_PLUGIN_DIR . '_backups/*.csv');
        $links = array();

        foreach($files as $file) {
            $links[basename($file)] = CHEFS_CORNER_PLUGIN_URL . '_backups/' . basename($file);  
        }

        krsort($links);

        require_once CHEFS_CORNER_PLUGIN_DIR . '/admin/partials/backup-page.php';
    }

    public function display_admin_settings() {
        $active_tab = isset( $_GET['tab'] ) ? $_GET['tab'] : 'general';

        if(isset($_GET['error_message'])) {
            add_action('admin_notices', array($this, 'display_notices'));
            do_action('admin_notices', $_GET['error_message']);
        }

        if (!current_user_can('manage_options') && (!wp_doing_ajax()) && check_admin_referer())
            wp_die( __( 'You are not allowed to access this part of the site' ) );

        if (!empty($_POST)) {
            $this->update_settings();
        }

        require_once CHEFS_CORNER_PLUGIN_DIR . '/admin/partials/admin-settings-display.php';
    }

    private function update_settings() {
        update_option('chefs_corner_aq_api_key', wp_unslash($_POST["chefs_corner_aq_api_key"]));
        update_option('chefs_corner_manufacturers', wp_unslash($_POST["chefs_corner_manufacturers"]));
        update_option('chefs_corner_notify_email', wp_unslash($_POST["chefs_corner_notify_email"]));

        $category_ruleset = array();

        if (isset($_POST["old_category_name"])) {
            $old_category_names = $_POST["old_category_name"];
            $new_category_names = $_POST["new_category_name"];

            foreach($old_category_names as $key => $value) {
                $ruleset = (object) array(
                    'rule_type' => 'rename', 
                    'old_category_name' => wp_unslash($value),
                    'new_category_name' => wp_unslash($new_category_names[$key])
                );
                
                $category_ruleset[] = $ruleset;
            }
        }

        if (isset($_POST["from_category_id"])) {
            $from_category_ids = $_POST["from_category_id"];
            $to_category_ids = $_POST["to_category_id"];

            foreach($from_category_ids as $key => $value) {
                $ruleset = (object) array(
                    'rule_type' => 'move_products', 
                    'from_category_id' => wp_unslash($value),
                    'to_category_id' => wp_unslash($to_category_ids[$key])
                );
                
                $category_ruleset[] = $ruleset;
            }
        }

        if (isset($_POST["exclude_category_id"])) {
            $exclude_category_id = $_POST["exclude_category_id"];

            foreach($exclude_category_id as $key => $value) {
                $ruleset = (object) array(
                    'rule_type' => 'exclude', 
                    'exclude_category_id' => wp_unslash($value)
                );
                
                $category_ruleset[] = $ruleset;
            }
        }

        update_option('chefs_corner_category_ruleset', $category_ruleset);
    }

    public function display_notices($notice){
        switch ($notice) {
            case '1':
                $message = __('There was an error adding this setting. Please try again.', $this->plugin_name);                 
                $err_code = esc_attr($this->plugin_name . '-setting');                 
                $setting_field = $this->plugin_name . '-setting';                 
                break;
        }

        $type = 'error';

        add_settings_error(
            $setting_field,
            $err_code,
            $message,
            $type
        );
    }

    public function register_settings() {
        register_setting($this->plugin_name . '-group', 'chefs_corner_aq_api_key');
        register_setting($this->plugin_name . '-group', 'chefs_corner_manufacturers');
        register_setting($this->plugin_name . '-group', 'chefs_corner_notify_email');
        register_setting($this->plugin_name . '-group', 'chefs_corner_category_ruleset');
    }
}