<?php
class CC_AQ_WC_Attach_Images extends CC_AQ_WC_Handler {
    protected $handler_type = 'attach_images';

    private $products = array();
    private $total_pages = 0;
    private $date_touched;

    public function handle() {
        $manufacturer_ids = $this->data['manufacturer_ids'];
        $manufacturer_id = $this->data['manufacturer_id'];
        $date_touched = $this->data['date_touched'];

        if (!isset($manufacturer_id)) return;

        $this->manufacturer_id = $manufacturer_id;
        $this->manufacturer_ids = $manufacturer_ids;
        $this->date_touched = $date_touched;

        $this->get_products_data();
        $this->attach_images();
    }

    private function attach_images() {
        $page = !empty($this->data['page']) ? (int) $this->data['page'] : 1;

        $this->log('attaching images...');

        foreach($this->products as $product) {
            $post_id = 0;
            $post = $this->get_post_by_meta(array('meta_key' => 'aq_product_id', 'meta_value' => $product->productId));

            if (!$post) continue;

            $post_id = $post->ID;
            $flag = 0;

            foreach($product->pictures as $picture){
                $this->attach_product_thumbnail($post_id, $picture->url, $flag);
                if ($flag == 0) $flag = 1;
            }
        }

        if ($page >= $this->total_pages) {   
            $this->log('finished importing products for manufacturer[' . $this->manufacturer_id . ']');

            $key = array_search($this->manufacturer_id, $this->manufacturer_ids);
            $key++;

            if (count($this->manufacturer_ids) > $key) {
                $this->data = array(
                    'manufacturer_ids' => $this->manufacturer_ids, 
                    'manufacturer_id' => $this->manufacturer_ids[$key], 
                    'date_touched' => $this->date_touched);
                $this->next_handler = 'sync_wc_products';
            }
            else {
                $this->data = array('date_touched' => $this->date_touched);
            }
        }
        else {
            $page++;

            $this->data = array(
                'manufacturer_ids' => $this->manufacturer_ids, 
                'manufacturer_id' => $this->manufacturer_id,
                'page' => $page,
                'date_touched' => $this->date_touched);

            $this->next_handler = 'sync_wc_products';
        }
    }

    private function get_post_by_meta($args = array()) {
        $args = (object)wp_parse_args($args);
    
        $args = array(
            'meta_query'        => array(
                array(
                    'key'       => $args->meta_key,
                    'value'     => $args->meta_value
                )
            ),
            'post_type'         => 'product',
            'posts_per_page'    => '1'
        );

        $posts = get_posts( $args );

        if (is_wp_error($posts) || count($posts) == 0) return false;
    
        return $posts[0];
    }

    private function attach_product_thumbnail($post_id, $url, $flag) {
        $image_url = $url;
        $url_array = explode('/', $image_url);
        $image_id = $url_array[count($url_array)-2];

        $path_parts = pathinfo(basename($image_url, '?' . parse_url($image_url, PHP_URL_QUERY)));
        $image_name = 'aq_' . $image_id . '.' . $path_parts['extension'];
        $filename = basename($image_name); 

        $attachment = $this->get_attachment($filename);
        $attach_id = 0;

        if ($attachment) {
            $attach_id = $attachment->ID;
        }
        else {
            $attach_id = $this->create_attachment($image_url, $filename, $post_id);
        }

        if( $flag == 0) {
            set_post_thumbnail($post_id, $attach_id);
        }

        if( $flag == 1 ){
            $attach_id_array = get_post_meta($post_id, '_product_image_gallery', true);
            $attach_id_array .= ',' . $attach_id;
            update_post_meta($post_id, '_product_image_gallery', $attach_id_array);
        }

        //$this->log('image attached to post with id: ' . $post_id);
        //$this->audit('image attached to post with id: ' . $post_id);
    }

    private function get_products_data() {
        $page = !empty($this->data['page']) ? (int) $this->data['page'] : 1;

        $json_data = $this->get_json_data_from_file($page);

        if ($json_data) {
            $this->total_pages = $json_data->total_pages;
            $this->products = $json_data->data;
        }
    }

    private function get_json_data_from_file($page = 1) {
        if(ini_get('allow_url_fopen')) {
            $file = CHEFS_CORNER_DATA_DIR . '/products-' . $this->manufacturer_id . '-' . $page . '.json';
            
            $file_content = file_get_contents($file);
            $data = json_decode($file_content);
    
            return $data;
        }
        else {
            $url = CHEFS_CORNER_PLUGIN_URL . '_data/products-' . $this->manufacturer_id . '-' . $page . '.json';

            $ch = curl_init($url);

            $options = array(
                CURLOPT_RETURNTRANSFER => true
            );

            curl_setopt_array($ch, $options);

            $result = curl_exec($ch);
            $data = json_decode($result);

            if(curl_errno($ch)) {
                $this->log('curl error for url: ' . $url, curl_error($ch));
                $data = null;
            }
            
            curl_close($ch);

            return $data;
        }
    }
}