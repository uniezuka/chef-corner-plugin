<?php
class CC_AQ_WC_Sync_WC_Products extends CC_AQ_WC_Handler {
    protected $handler_type = 'sync_wc_products';

    private $products = array();
    private $aq_manufacturers = array();
    private $manufacturer_id;
    private $manufacturer_ids;
    private $date_touched;
    private $total_pages = 0;

    private $ruleset = array();

    public function handle() {
        $date = date("Y-m-d h:i:sa");
        $manufacturer_ids = $this->data['manufacturer_ids'];
        $manufacturer_id = $this->data['manufacturer_id'];
        $date_touched = (isset($this->data['date_touched'])) ? $this->data['date_touched'] : $date;

        if (!isset($manufacturer_id)) return;

        $this->ruleset = get_option('chefs_corner_category_ruleset');

        $this->manufacturer_id = $manufacturer_id;
        $this->manufacturer_ids = $manufacturer_ids;
        $this->aq_manufacturers = $this->get_aq_manufacturers();
        $this->date_touched = $date_touched;

        $this->get_products_data();
        $this->import_products();
    }

    private function import_products() {
        $page = !empty($this->data['page']) ? (int) $this->data['page'] : 1;
        
        if ($page == 1)
            $this->log('total number of pages: ' . $this->total_pages);

        $this->log('processing products(page: ' . $page . ' of ' . $this->total_pages . ') for manufacturer[' . $this->manufacturer_id . ']');
        
        foreach($this->products as $product) {
            $post_id = 0;
            $post = $this->get_post_by_meta(array('meta_key' => 'aq_product_id', 'meta_value' => $product->productId));

            $manufacturer = $this->get_manufacturer($product->mfrId);

            $term = $this->get_product_term($product);

            if (!$term) {
                $this->log('no category for ' . $manufacturer->mfrShortName . ' ' . $product->models->mfrModel);
                continue;
            }

            $wc_product_title = $manufacturer->mfrShortName . ' ' . $product->models->mfrModel;
            $wc_product_content = $product->specifications->AQSpecification;

            $wc_product_content .= '<div class="aq_documents">';
            foreach($product->documents as $document) {
                $wc_product_content .= '<a href="' . $document->url . '" class="aq_document custom_button" target="_blank">' . $document->name . '</a>';
            }
            $wc_product_content .= '</div>';

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

                update_post_meta($post_id, 'aq_date_touched', $this->date_touched);
            }
            else {
                $should_update = $this->should_update($post->ID, $manufacturer, $product, $wc_product_title);
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

                update_post_meta($post->ID, 'aq_date_touched', $this->date_touched);
            }

            $this->set_product_category($term, $post_id);

            if ($action != '') {
                if ($post_id === 0) {
                    $this->log('unable to process product: ', $product);
                    $this->audit('unable to process product: ', $product->productId);
                    continue;
                }

                wp_set_object_terms($post_id, 'simple', 'product_type');
                
                $this->update_wc_meta_data($post_id, $product);
                $this->update_wc_aq_meta_data($post_id, $manufacturer, $product);

                if ($action == 'create') {
                    $this->audit('product was created: ' . $wc_product_title . ' [' . $product->productId . ']');
                }
                else {
                    $this->audit('product was updated: ' . $wc_product_title . ' [' . $product->productId . ']');
                }
            }
        }

        $this->data = array(
            'manufacturer_ids' => $this->manufacturer_ids, 
            'manufacturer_id' => $this->manufacturer_id,
            'page' => $page,
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
        if (!isset($product->productCategory)) return false;

        $category_id = $this->get_aq_moved_product_category_id($product->productCategory);

        return $this->get_term_by_meta(array('meta_key' => 'aq_category_id', 'meta_value' => $category_id));
    }

    private function get_aq_moved_product_category_id($productCategory) {
        $rules = array_filter($this->ruleset, function($data) {
            return $data->rule_type == 'move_products';
        });

        if (count($rules) <= 0) return $productCategory->categoryId;

        $new_product_id = $productCategory->categoryId;

        foreach($rules as $rule) {
            if ($rule->from_category_id == $productCategory->categoryId) {
                $new_product_id = $rule->to_category_id;
                break;
            }
        }

        return $new_product_id;
    }

    private function should_update($post_id, $manufacturer, $product, $wc_product_title) {

        $aq_product_mfrShortName = get_post_meta($post_id, 'aq_product_mfrShortName', true);
        if ($manufacturer->mfrShortName != $aq_product_mfrShortName) { 
            $this->audit('product[' . $wc_product_title . ']');
            $this->audit('old mfrShortName value: ' . $aq_product_mfrShortName);
            $this->audit('new mfrShortName value: ' . $manufacturer->mfrShortName);
            return true;
        }

        $aq_product_mfrModel = get_post_meta($post_id, 'aq_product_mfrModel', true);
        if ($product->models->mfrModel != $aq_product_mfrModel) { 
            $this->audit('product[' . $wc_product_title . ']');
            $this->audit('old mfrModel value: ' . $aq_product_mfrModel);
            $this->audit('new mfrModel value: ' . $product->models->mfrModel);
            return true;
        }

        $aq_product_AQSpecification = get_post_meta($post_id, 'aq_product_AQSpecification', true);
        if ($product->specifications->AQSpecification != $aq_product_AQSpecification) { 
            $this->audit('product[' . $wc_product_title . ']');
            $this->audit('old AQSpecification value: ' . $aq_product_AQSpecification);
            $this->audit('new AQSpecification value: ' . $product->specifications->AQSpecification);
            return true;
        }

        $aq_product_categoryId = get_post_meta($post_id, 'aq_product_categoryId', true);
        if (isset($product->productCategory)) {
            if ($aq_product_categoryId == '') { 
                $this->audit('product[' . $wc_product_title . ']');
                $this->audit('new categoryId');
                return true;
            }

            if ($product->productCategory->categoryId != $aq_product_categoryId)  { 
                $this->audit('product[' . $wc_product_title . ']');
                $this->audit('old categoryId value: ' . $aq_product_categoryId);
                $this->audit('new categoryId value: ' . $product->productCategory->categoryId);
                return true;
            }
        }
        else {
            if ($aq_product_categoryId != '') { 
                $this->audit('product[' . $wc_product_title . ']');
                $this->audit('categoryId unset');
                return true;
            }
        }

        $aq_product_productHeight = get_post_meta($post_id, 'aq_product_productHeight', true);
        if ($product->productDimension->productHeight != $aq_product_productHeight) { 
            $this->audit('product[' . $wc_product_title . ']');
            $this->audit('old productHeight value: ' . $aq_product_productHeight);
            $this->audit('new productHeight value: ' . $product->productDimension->productHeight);
            return true;
        }

        $aq_product_productWidth = get_post_meta($post_id, 'aq_product_productWidth', true);
        if ($product->productDimension->productWidth != $aq_product_productWidth) { 
            $this->audit('product[' . $wc_product_title . ']');
            $this->audit('old productWidth value: ' . $aq_product_productWidth);
            $this->audit('new productWidth value: ' . $product->productDimension->productWidth);
            return true;
        }

        $aq_product_productDepth = get_post_meta($post_id, 'aq_product_productDepth', true);
        if ($product->productDimension->productDepth != $aq_product_productDepth) { 
            $this->audit('product[' . $wc_product_title . ']');
            $this->audit('old productDepth value: ' . $aq_product_productDepth);
            $this->audit('new productDepth value: ' . $product->productDimension->productDepth);
            return true;
        }

        $aq_product_shippingCube = get_post_meta($post_id, 'aq_product_shippingCube', true);
        if ($product->productDimension->shippingCube != $aq_product_shippingCube) { 
            $this->audit('product[' . $wc_product_title . ']');
            $this->audit('old shippingCube value: ' . $aq_product_shippingCube);
            $this->audit('new shippingCube value: ' . $product->productDimension->shippingCube);
            return true;
        }
        
        $aq_product_shippingWeight = get_post_meta($post_id, 'aq_product_shippingWeight', true);
        if ($product->productDimension->shippingWeight != $aq_product_shippingWeight) { 
            $this->audit('product[' . $wc_product_title . ']');
            $this->audit('old shippingWeight value: ' . $aq_product_shippingWeight);
            $this->audit('new shippingWeight value: ' . $product->productDimension->shippingWeight);
            return true;
        }

        $aq_product_freightClass = get_post_meta($post_id, 'aq_product_freightClass', true);
        if ($product->freightClass != $aq_product_freightClass) { 
            $this->audit('product[' . $wc_product_title . ']');
            $this->audit('old freightClass value: ' . $aq_product_freightClass);
            $this->audit('new freightClass value: ' . $product->freightClass);
            return true;
        }
        
        $aq_product_shipFromZip = get_post_meta($post_id, 'aq_product_shipFromZip', true);
        if ($product->shipFromZip != $aq_product_shipFromZip) { 
            $this->audit('product[' . $wc_product_title . ']');
            $this->audit('old shipFromZip value: ' . $aq_product_shipFromZip);
            $this->audit('new shipFromZip value: ' . $product->shipFromZip);
            return true;
        }

        $aq_product_unitsPerCase = get_post_meta($post_id, 'aq_product_unitsPerCase', true);
        if ($product->packingData->unitsPerCase != $aq_product_unitsPerCase) { 
            $this->audit('product[' . $wc_product_title . ']');
            $this->audit('old unitsPerCase value: ' . $aq_product_unitsPerCase);
            $this->audit('new unitsPerCase value: ' . $product->packingData->unitsPerCase);
            return true;
        }

        $aq_product_listPrice = get_post_meta($post_id, 'aq_product_listPrice', true);
        if ($product->pricing->listPrice != $aq_product_listPrice) { 
            $this->audit('product[' . $wc_product_title . ']');
            $this->audit('old listPrice value: ' . $aq_product_listPrice);
            $this->audit('new listPrice value: ' . $product->pricing->listPrice);
            return true;
        }

        $aq_product_prop65 = get_post_meta($post_id, 'aq_product_prop65', true);
        if ($product->prop65 != $aq_product_prop65) { 
            $this->audit('product[' . $wc_product_title . ']');
            $this->audit('old prop65 value: ' . $aq_product_prop65);
            $this->audit('new prop65 value: ' . $product->prop65);
            return true;
        }

        $aq_product_certifications = get_post_meta($post_id, 'aq_product_certifications', true);
        if ($product->certifications != $aq_product_certifications) { 
            $this->audit('product[' . $wc_product_title . ']');
            $this->audit('old certifications value: ' . $aq_product_certifications);
            $this->audit('new certifications value: ' . $product->certifications);
            return true;
        }

        $aq_product_pictures = get_post_meta($post_id, 'aq_product_pictures', true);
        if ($aq_product_pictures) {
            if (count($aq_product_pictures) != count($product->pictures)) { 
                $this->audit('product[' . $wc_product_title . ']');
                $this->audit('old pictures count: ' . count($aq_product_pictures));
                $this->audit('new pictures count: ' . count($product->pictures));
                return true;
            }
            $i = 0;
            foreach($product->pictures as $picture) {
                if ($picture->url != $aq_product_pictures[$i]) {
                    $this->audit('product[' . $wc_product_title . ']');
                    $this->audit('pictures need to update');

                    $this->log('product[' . $wc_product_title . ']');
                    $this->log('picture->url: ', $picture->url);
                    $this->log('aq_product_pictures: ', $aq_product_pictures);
                    $this->log('aq_product_pictures[i]: ', $aq_product_pictures[$i]);
                    return true;
                }
                $i++;
            }
        }

        $aq_product_documents = get_post_meta($post_id, 'aq_product_documents', true);
        if ($aq_product_documents) {
            if (count($aq_product_documents) != count($product->documents)) { 
                $this->audit('product[' . $wc_product_title . ']');
                $this->audit('old documents count: ' . count($aq_product_documents));
                $this->audit('new documents count: ' . count($product->documents));
                $this->audit('documents need to update');
                return true;
            }
            $i = 0;
            foreach($product->documents as $document) {
                if ($document->url != $aq_product_documents[$i]->url) {
                    $this->audit('product[' . $wc_product_title . ']');
                    $this->audit('documents need to update');
                    return true;
                }
                $i++;
            }
        }
    
        return false;
    }

    private function update_wc_aq_meta_data($post_id, $manufacturer, $product) {
        update_post_meta($post_id, 'aq_product_mfrShortName', $manufacturer->mfrShortName);
        update_post_meta($post_id, 'aq_product_id', $product->productId);
        update_post_meta($post_id, 'aq_product_mfrModel', $product->models->mfrModel);
        update_post_meta($post_id, 'aq_product_AQSpecification', $product->specifications->AQSpecification);

        if (isset($product->productCategory)) {
            update_post_meta($post_id, 'aq_product_categoryId', $product->productCategory->categoryId);
        }
        else {
            update_post_meta($post_id, 'aq_product_categoryId', '');
        }

        $pictures = array();
        delete_post_meta($post_id, 'aq_product_pictures');
        if (count($product->pictures) > 0) {
            foreach($product->pictures as $picture) {
                $pictures[] = $picture->url;
            }
            update_post_meta($post_id, 'aq_product_pictures', $pictures);
        }

        $documents = array();
        delete_post_meta($post_id, 'aq_product_documents');
        if (count($product->documents) > 0) {
            foreach($product->documents as $document) {
                $documents[] = $document;
            }
            update_post_meta($post_id, 'aq_product_documents', $documents);
        }

        update_post_meta($post_id, 'aq_product_productHeight', $product->productDimension->productHeight);
        update_post_meta($post_id, 'aq_product_productWidth', $product->productDimension->productWidth);
        update_post_meta($post_id, 'aq_product_productDepth', $product->productDimension->productDepth);
        update_post_meta($post_id, 'aq_product_shippingCube', $product->productDimension->shippingCube);
        update_post_meta($post_id, 'aq_product_shippingWeight', $product->productDimension->shippingWeight);
        update_post_meta($post_id, 'aq_product_freightClass', $product->freightClass);
        update_post_meta($post_id, 'aq_product_shipFromZip', $product->shipFromZip);
        update_post_meta($post_id, 'aq_product_unitsPerCase', $product->packingData->unitsPerCase);
        update_post_meta($post_id, 'aq_product_listPrice', $product->pricing->listPrice);
        update_post_meta($post_id, 'aq_product_prop65', $product->prop65);
        update_post_meta($post_id, 'aq_product_certifications', $product->certifications);
    }

    private function update_wc_meta_data($post_id, $product) {
        update_post_meta($post_id, '_visibility', 'visible' );
        update_post_meta($post_id, '_stock_status', 'instock');
        update_post_meta($post_id, 'total_sales', '0' );
        update_post_meta($post_id, '_downloadable', 'no' );
        update_post_meta($post_id, '_virtual', 'no' );
        update_post_meta($post_id, '_regular_price', $product->pricing->listPrice);
        //update_post_meta( $post_id, '_sale_price', '' );
        //update_post_meta( $post_id, '_purchase_note', '' );
        //update_post_meta( $post_id, '_featured', 'no' );
        update_post_meta($post_id, '_weight', $product->productDimension->shippingWeight);
        //update_post_meta( $post_id, '_length', '11' );
        update_post_meta($post_id, '_width', $product->productDimension->productWidth);
        update_post_meta($post_id, '_height', $product->productDimension->productHeight);
        //update_post_meta( $post_id, '_sku', '');
        update_post_meta($post_id, '_product_attributes', array() );
        //update_post_meta( $post_id, '_sale_price_dates_from', '' );
        //update_post_meta( $post_id, '_sale_price_dates_to', '' );
        update_post_meta($post_id, '_price', $product->pricing->listPrice);
        //update_post_meta( $post_id, '_sold_individually', '' );
        update_post_meta($post_id, '_manage_stock', 'yes' );
        //wc_update_product_stock($post_id, $single['qty'], 'set');
        update_post_meta($post_id, '_backorders', 'no');
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

    private function get_manufacturer($manufacturer_id) {
        $filtered_array = array_filter($this->aq_manufacturers, function($data) use ($manufacturer_id) {
            return $data->mfrId == $manufacturer_id;
        });

        $item_exists = count($filtered_array) > 0;

        return $item_exists ? array_shift($filtered_array) : null;
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
        $page = !empty($this->data['page']) ? (int) $this->data['page'] : 1;

        if ($page == 1)
            $this->log('getting products from manufacturer[' . $this->manufacturer_id . '] for importing...');

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