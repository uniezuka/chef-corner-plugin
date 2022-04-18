<?php

class CC_WC_Go_Positive_Init extends CC_WC_Go_Positive_Handler {
    protected $handler_type = 'init';

    public function handle() {

        if (file_exists(CHEFS_CORNER_LOG_FILE))
            unlink(CHEFS_CORNER_LOG_FILE);

        if (file_exists(CHEFS_CORNER_AUDIT_FILE))
            unlink(CHEFS_CORNER_AUDIT_FILE);

        $date = date("Y-m-d h:i:sa");
        $this->log('request start at: ' . $date);
        $this->audit('request start at: ' . $date);

        if (!$this->developer_id) {
            $this->log('developer id not found');
            $this->audit('developer id not found');
            $this->next_handler = '';
            return;
        }

        if (!$this->developer_key) {
            $this->log('developer key not found');
            $this->audit('developer key not found');
            $this->next_handler = '';
            return;
        }
    }
}