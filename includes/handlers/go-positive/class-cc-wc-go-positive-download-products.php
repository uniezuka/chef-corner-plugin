<?php

class CC_WC_Go_Positive_Download_Products extends CC_WC_Go_Positive_Handler {
    protected $handler_type = 'download_products';

    public function handle() {

        $product_url = self::API_URL . '/product_list';

        $block = !isset($this->data['block']) ? 1 : $this->data['block'];        

        $this->log('getting products for block[' . $block . ']');

        $obj = new stdClass();
        $obj->options = new stdClass();
        $obj->options->block = $block;

        $json_data = $this->get_json_data($product_url, $obj );

        $returned_records = $json_data->product_list_response->result->returned_records;
        $total_blocks = $json_data->product_list_response->result->total_blocks;

        if (isset($this->data['original_next_handler'])) {
            $this->next_handler = $this->data['original_next_handler'];
        }

        if ($returned_records > 0) {
            $this->create_products_json_file($json_data, $block);
            $block++;

            if ($block > $total_blocks) { 
                $this->data = array('total_blocks' => $total_blocks);
            }
            else {

                $this->data = array(
                    'total_blocks' => $total_blocks,
                    'block' => $block,
                    'original_next_handler' => $this->next_handler);

                $this->next_handler = $this->handler_type;
            }
        }
        else {
            $this->data = array('total_blocks' => $total_blocks);
        }
    }   

    private function create_products_json_file($json_data, $block) {
        $this->log('creating products-' . $block . '.json file...');

        $file = CHEFS_CORNER_DATA_DIR . '/products-' . $block . '.json';

        if (!file_exists($file)) {
            $fd = fopen($file, 'xb');
            fclose($fd);
        } 

        file_put_contents($file, json_encode($json_data));
    }
}