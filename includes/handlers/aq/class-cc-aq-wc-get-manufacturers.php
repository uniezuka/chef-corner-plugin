<?php

class CC_AQ_WC_Get_Manufacturers extends CC_AQ_WC_Handler {
    protected $handler_type = 'get_manufacturers';

    private $manufacturers = array();

    public function handle() {
        $this->log('getting manufacturers data from AQ api...');

        $selected_manufacturers = explode('|', get_option('chefs_corner_manufacturers'));

        $url = self::AQ_PRODUCTS_API_URL . self::MANUFACTURERS_API;

        $json_data = $this->get_json_data($url);

        foreach($selected_manufacturers as $selected_manufacturer) {
            $selected_manufacturer = trim($selected_manufacturer);

            $filtered_array = array_filter($json_data, function($data) use ($selected_manufacturer) {
                return strtolower($data->mfrName) == strtolower($selected_manufacturer);
            });
            
            if (count($filtered_array) <= 0) continue;

            foreach($filtered_array as $filter_array)
                array_push($this->manufacturers, $filter_array);
        }

        $this->manufacturers = $this->get_array_unique($this->manufacturers);

        $this->log('number of manufacturers data retrieved from AQ api: ' . count($this->manufacturers));

        if (count($this->manufacturers) == 0) {
            $this->next_handler = '';
            return;
        }

        $manufacturer_ids = array_column($this->manufacturers, 'mfrId');
        
        $this->data = array('manufacturer_ids' => $manufacturer_ids, 'manufacturer_id' => $manufacturer_ids[0]);
    }
}