<?php

class CC_WC_Go_Positive_Download_Departments_Categories extends CC_WC_Go_Positive_Handler {
    protected $handler_type = 'download_departments_categories';

    public function handle() {

        $product_url = self::API_URL . '/product_department_category_list';

        $this->log('getting departments and its categories');

        $json_data = $this->get_json_data($product_url, new stdClass());

        $this->create_json_file($json_data);
    }   

    private function create_json_file($json_data) {
        $this->log('creating departments_catgories.json file...');

        $file = CHEFS_CORNER_DATA_DIR . '/departments_catgories.json';

        if (!file_exists($file)) {
            $fd = fopen($file, 'xb');
            fclose($fd);
        } 

        file_put_contents($file, json_encode($json_data));
    }
}