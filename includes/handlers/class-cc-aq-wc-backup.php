<?php

class CC_AQ_WC_Backup extends CC_AQ_WC_Handler {
    protected $handler_type = 'create_woocommerce_backup';

    public function __construct($next_handler) {
        require_once CHEFS_CORNER_PLUGIN_DIR . 'includes/class-cc-aq-wc-product-exporter.php';

        parent::__construct($next_handler);
    }

    public function handle() {

        $file_name = isset($this->data['file_name']) ? $this->data['file_name'] :
            'wc-product-export-' . date('d-m-Y-') . time() . '.csv';
        $step = isset($this->data['step']) ? absint($this->data['step']) : 1;

        if ($step == 1)
            $this->log('creating WooCommerce products backup...');

        $exporter = new CC_AQ_WC_Product_Exporter();
        $exporter->set_filename(wp_unslash($file_name));
        $exporter->set_page($step);
        $exporter->export();

        $percent_completed = $exporter->get_percent_complete();
        $step++;

        $this->log('backup percent completed: '. $percent_completed);

        $manufacturer_ids = $this->data['manufacturer_ids'];

        if (!$this->is_valid($manufacturer_ids)) {
            $this->next_handler = '';
            return;
        }

        if ($percent_completed == 100) {
            $this->log('backup file created: ' . $file_name);
            $this->audit('backup file created: ' . $file_name);

            $this->data = array('manufacturer_ids' => $manufacturer_ids, 'manufacturer_id' => $manufacturer_ids[0]);
        }
        else {
            $this->data = array(
                'manufacturer_ids' => $manufacturer_ids,
                'file_name' => $file_name,
                'step' => $step
            );
            $this->next_handler = 'create_woocommerce_backup';
        }
    }

    public function is_valid($manufacturer_ids) {
        if (!isset($manufacturer_ids)) return false;
        if (!is_array($manufacturer_ids)) return false;
        if (count($manufacturer_ids) == 0) return false;

        return true;
    }
}