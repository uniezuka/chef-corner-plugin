<?php

class CC_AQ_WC_Init extends CC_AQ_WC_Handler {
    protected $handler_type = 'init';

    public function handle() {
        if (file_exists(CHEFS_CORNER_LOG_FILE))
            unlink(CHEFS_CORNER_LOG_FILE);

        if (file_exists(CHEFS_CORNER_AUDIT_FILE))
            unlink(CHEFS_CORNER_AUDIT_FILE);

        $date = date("Y-m-d h:i:sa");
        $this->log('request start at: ' . $date);
        $this->audit('request start at: ' . $date);

        if (!$this->api_key) {
            $this->log('api key not found');
            $this->audit('api key not found');
            $this->next_handler = '';
            return;
        }

        if (!get_option('chefs_corner_manufacturers')) { 
            $this->log('please provide manufacturers to get data');
            $this->audit('manufacturers not found');
            $this->next_handler = '';
            return;
        }
    }
}