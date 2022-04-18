<?php

class CC_AQ_WC_Get_AQ_Categories extends CC_AQ_WC_Handler {
    protected $handler_type = 'get_aq_categories';

    private $products = array();
    private $categories = array();

    public function handle() {
        $manufacturer_ids = $this->data['manufacturer_ids'];
        $manufacturer_id = $this->data['manufacturer_id'];

        if (!isset($manufacturer_id)) {
            $this->next_handler = '';
            return;
        }

        $url = self::AQ_PRODUCTS_API_URL . self::MANUFACTURERS_API . '/%s/products';

        $this->log('getting products from manufacturer[' . $manufacturer_id . ']...');

        $product_url = sprintf($url, $manufacturer_id);

        $json_data = $this->get_json_data($product_url);

        foreach($json_data as $data) {
            $this->add_product_category($data);

            array_push($this->products, $data);
        }

        $this->create_manufacturer_products_json_file($manufacturer_id);

        $this->log('number of products data retrieved for manufacturer[' . $manufacturer_id . ']: ' . count($this->products));
        $this->log('number of categories data retrieved for manufacturer[' . $manufacturer_id . ']: ' . count($this->categories));

        $this->data = array(
            'manufacturer_ids' => $manufacturer_ids, 
            'manufacturer_id' => $manufacturer_id,
            'category_ids' => array_column($this->categories, 'categoryId'));
    }

    private function create_manufacturer_products_json_file($manufacturer_id, $page = 1) {
        $total = count($this->products);  
        $limit = 50; 
        $total_pages = ceil( $total/ $limit );

        if ($total_pages < $page) return;

        $page = max($page, 1); 
        $page = min($page, $total_pages); 
        $offset = ($page - 1) * $limit;

        $product_items = array_slice($this->products, $offset, $limit);

        $this->save_json_file($manufacturer_id, $product_items, $page, $total_pages);
    }

    private function save_json_file($manufacturer_id, $product_items, $page, $total_pages) {
        $file = CHEFS_CORNER_DATA_DIR . '/products-' . $manufacturer_id . '-' . $page . '.json';

        if (!file_exists($file)) {
            $fd = fopen($file, 'xb');
            fclose($fd);
        } 

        $data = [
            'data' => $product_items,
            'page' => $page,
            'total_pages' => $total_pages,
        ];

        file_put_contents($file, json_encode($data));

        $page++;

        $this->create_manufacturer_products_json_file($manufacturer_id, $page);
    }

    private function add_product_category($product_json_data) {
        if (!isset($product_json_data->productCategory)) return;

        $product_category = $product_json_data->productCategory;

        $filtered_array = array_filter($this->categories, function($data) use ($product_category) {
            return $data->categoryId == $product_category->categoryId;
        });

        $product_category_exists = count($filtered_array) > 0;

        if ($product_category_exists) return;

        array_push($this->categories, $product_category);
    }
}