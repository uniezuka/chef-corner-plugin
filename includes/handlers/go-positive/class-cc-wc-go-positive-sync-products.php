<?php
class CC_WC_Go_Positive_Sync_Products extends CC_WC_Go_Positive_Handler {
    protected $handler_type = 'sync_products';

    private $products = array();
    private $date_touched;
    private $total_blocks = 0;

    public function handle() {
        $date = date("Y-m-d h:i:sa");
        $date_touched = (isset($this->data['date_touched'])) ? $this->data['date_touched'] : $date;
        
        $this->date_touched = $date_touched;
        $this->total_blocks = (isset($this->data['total_blocks'])) ? $this->data['total_blocks'] : 1;

        $this->get_products_data();
        $this->import_products();
    }

    private function import_products() {
        $block = !empty($this->data['block']) ? (int) $this->data['block'] : 1;
        
        if ($block == 1)
            $this->log('total number of blocks: ' . $this->total_blocks);

        $this->log('processing products(block: ' . $block . ' of ' . $this->total_blocks . ')');
        
        foreach($this->products as $product) {
            $post_id = 0;
            $post = $this->get_post_by_meta(array('meta_key' => 'go_positive_product_id', 'meta_value' => $product->productid));

            $term = $this->get_product_term($product);

            $wc_product_title = $product->manufacturer . ' ' . $product->sku . ' ' . $product->description;
            $wc_product_content = $product->longdescription;

            // $wc_product_content .= '<div class="aq_documents">';
            // foreach($product->documents as $document) {
            //     $wc_product_content .= '<a href="' . $document->url . '" class="aq_document custom_button" target="_blank">' . $document->name . '</a>';
            // }
            // $wc_product_content .= '</div>';

            $action = '';

            if (!$post) {
                $args = array(	   
                    'post_author' => 1, 
                    'post_content' => $wc_product_content,
                    'post_status' => "publish", 
                    'post_title' => $wc_product_title,
                    'post_parent' => '',
                    'post_type' => "product"
                ); 

                $post_id = wp_insert_post( $args );
                $action = 'create';

                update_post_meta($post_id, 'go_positive_date_touched', $this->date_touched);
            }
            else {
                $should_update = $this->should_update($post->ID, $product, $wc_product_title);
                $post_id = $post->ID;

                if ($should_update) {
                    $args = array(	   
                        'ID' => $post->ID, 
                        'post_content' => $wc_product_content,
                        'post_title' => $wc_product_title
                    );
                    $post_id = wp_update_post($args);
                    $action = 'update';
                }

                update_post_meta($post->ID, 'go_positive_date_touched', $this->date_touched);
            }

            $this->set_product_category($term, $post_id);

            if ($action != '') {
                if ($post_id === 0) {
                    $this->log('unable to process product: ', $product);
                    $this->audit('unable to process product: ', $product->productid);
                    continue;
                }

                wp_set_object_terms($post_id, 'simple', 'product_type');
                
                $this->update_wc_meta_data($post_id, $product);
                $this->update_wc_go_positive_meta_data($post_id, $product);

                if ($action == 'create') {
                    $this->audit('product was created: ' . $wc_product_title . ' [' . $product->productid . ']');
                }
                else {
                    $this->audit('product was updated: ' . $wc_product_title . ' [' . $product->productid . ']');
                }
            }
        }

        $this->data = array(
            'total_blocks' => $this->total_blocks,
            'block' => $block,
            'date_touched' => $this->date_touched);
    }

    private function set_product_category($term, $post_id) {
        $product_terms = get_the_terms($post_id, 'product_cat');

        foreach($product_terms as $product_term){
            wp_remove_object_terms($post_id, $product_term->term_id, 'product_cat');
        }

        if ($term) wp_set_object_terms($post_id, $term->term_id, 'product_cat');
    }

    private function get_product_term($product) {
        return $this->get_term_by_meta(array('meta_key' => 'go_positive_category_id', 'meta_value' => $product->categoryid));
    }

    private function get_term_by_meta($args = array()) {
        $args = (object)wp_parse_args($args);

        $args = array(
            'hide_empty' => false,
            'meta_query' => array(
                array(
                   'key'       => $args->meta_key,
                   'value'     => $args->meta_value
                )
            ),
            'taxonomy'  => 'product_cat',
        );

        $terms = get_terms($args);

        if (is_wp_error($terms) || count($terms) == 0) return false;
    
        return $terms[0];
    }


    private function should_update($post_id, $product, $wc_product_title) {

        $go_positive_sku = get_post_meta($post_id, 'go_positive_sku', true);
        if ($product->sku != $go_positive_sku) { 
            $this->audit('product[' . $wc_product_title . ']');
            $this->audit('old GoPositive SKU value: ' . $go_positive_sku);
            $this->audit('new GoPositive SKU value: ' . $product->sku);
            return true;
        }

        $go_positive_manufacturer = get_post_meta($post_id, 'go_positive_manufacturer', true);
        if ($product->manufacturer != $go_positive_manufacturer) { 
            $this->audit('product[' . $wc_product_title . ']');
            $this->audit('old GoPositive Manufacturer value: ' . $go_positive_manufacturer);
            $this->audit('new GoPositive Manufacturer value: ' . $product->manufacturer);
            return true;
        }

        $go_positive_categoryid = get_post_meta($post_id, 'go_positive_categoryid', true);
        if ($product->categoryid != $go_positive_categoryid) { 
            $this->audit('product[' . $wc_product_title . ']');
            $this->audit('old GoPositive Categoryid value: ' . $go_positive_categoryid);
            $this->audit('new GoPositive Categoryid value: ' . $product->categoryid);
            return true;
        }

        $go_positive_retail_price = get_post_meta($post_id, 'go_positive_retail_price', true);
        if ($product->retail_price != $go_positive_retail_price) { 
            $this->audit('product[' . $wc_product_title . ']');
            $this->audit('old GoPositive Retail Price value: ' . $go_positive_retail_price);
            $this->audit('new GoPositive Retail Price value: ' . $product->retail_price);
            return true;
        }
    
        return false;
    }

    private function update_wc_go_positive_meta_data($post_id, $product) {
        update_post_meta($post_id, 'go_positive_product_id', $product->productid);
        update_post_meta($post_id, 'go_positive_sku', $product->sku);
        update_post_meta($post_id, 'go_positive_manufacturer', $product->manufacturer);
        update_post_meta($post_id, 'go_positive_categoryid', $product->categoryid);
        update_post_meta($post_id, 'go_positive_retail_price', $product->retail_price);
    }

    private function update_wc_meta_data($post_id, $product) {
        update_post_meta($post_id, '_visibility', 'visible' );
        update_post_meta($post_id, '_stock_status', 'instock');
        update_post_meta($post_id, 'total_sales', '0' );
        update_post_meta($post_id, '_downloadable', 'no' );
        update_post_meta($post_id, '_virtual', 'no' );
        update_post_meta($post_id, '_regular_price', $product->retail_price);
        update_post_meta($post_id, '_weight', $product->weight);
        //update_post_meta( $post_id, '_length', '11' );
        // update_post_meta($post_id, '_width', $product->productDimension->productWidth);
        // update_post_meta($post_id, '_height', $product->productDimension->productHeight);
        update_post_meta( $post_id, '_sku', $product->sku);
        update_post_meta($post_id, '_product_attributes', array() );
        //update_post_meta( $post_id, '_sale_price_dates_from', '' );
        //update_post_meta( $post_id, '_sale_price_dates_to', '' );
        update_post_meta($post_id, '_price', $product->retail_price);
        //update_post_meta( $post_id, '_sold_individually', '' );
        update_post_meta($post_id, '_manage_stock', 'yes' );
        //wc_update_product_stock($post_id, $single['qty'], 'set');
        update_post_meta($post_id, '_backorders', 'no');
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

    private function get_products_data() {
        $block = !empty($this->data['block']) ? (int) $this->data['block'] : 1;

        if ($block == 1)
            $this->log('getting products...');

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