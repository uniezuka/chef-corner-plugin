<?php
class CC_WC_Go_Positive_Attach_Images extends CC_WC_Go_Positive_Handler {
    protected $handler_type = 'attach_images';

    private $products = array();
    private $total_blocks = 0;
    private $date_touched;

    public function handle() {
        $date_touched = $this->data['date_touched'];
        $this->date_touched = $date_touched;
        $this->total_blocks = (isset($this->data['total_blocks'])) ? $this->data['total_blocks'] : 1;

        $this->get_products_data();
        $this->attach_images();
    }

    private function attach_images() {
        $block = !empty($this->data['block']) ? (int) $this->data['block'] : 1;

        $this->log('attaching images for block[' . $block . ']...');

        foreach($this->products as $product) {
            $post_id = 0;
            $post = $this->get_post_by_meta(array('meta_key' => 'go_positive_product_id', 'meta_value' => $product->productid));

            if (!$post) continue;

            $post_id = $post->ID;
            $flag = 0;
        }

        if ($block >= $this->total_blocks) {   
            $this->data = array('date_touched' => $this->date_touched);
        }
        else {
            $block++;

            $this->data = array(
                'total_blocks' => $this->total_blocks,
                'block' => $block,
                'date_touched' => $this->date_touched);

            $this->next_handler = 'sync_products';
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
        $block = !empty($this->data['block']) ? (int) $this->data['block'] : 1;

        $json_data = $this->get_json_data_from_file($block);

        if ($json_data) {
            $this->products = $json_data->product_list_response->product_list->products;
        }
    }

    private function get_json_data_from_file($block = 1) {
        if(ini_get('allow_url_fopen')) {
            $file = CHEFS_CORNER_DATA_DIR . '/products-' . $block . '.json';
            
            $file_content = file_get_contents($file);
            $response = json_decode($file_content);
    
            return $response;
        }
        else {
            $url = CHEFS_CORNER_PLUGIN_URL . '_data/products-' . $block . '.json';

            $ch = curl_init($url);

            $options = array(
                CURLOPT_RETURNTRANSFER => true
            );

            curl_setopt_array($ch, $options);

            $result = curl_exec($ch);
            $response = json_decode($result);

            if(curl_errno($ch)) {
                $this->log('curl error for url: ' . $url, curl_error($ch));
                $response = null;
            }
            
            curl_close($ch);

            return $response;
        }
    }
}