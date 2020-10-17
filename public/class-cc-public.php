<?php

class CC_Public {
    private $plugin_name;
    private $version;

    public function __construct($plugin_name, $version) {
		$this->plugin_name = $plugin_name;
        $this->version = $version;

        $this->init_hooks();
    }

    private function init_hooks() {
        add_filter('woocommerce_product_tabs', array($this, 'woo_custom_additional_info_tab'), 98);
    }

    public function woo_custom_additional_info_tab( $tabs ) {
        $tabs['additional_information']['callback'] = array($this, 'woo_custom_additional_info_tab_content');

        return $tabs;
    }

    public function woo_custom_additional_info_tab_content() {
        
        woocommerce_product_additional_information_tab();

        require_once CHEFS_CORNER_PLUGIN_DIR . '/public/partials/aq-additional-info-tab-content.php';
    }
}