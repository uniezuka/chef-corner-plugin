<?php

class CC_WC_Go_Positive_Sync_Categories extends CC_WC_Go_Positive_Handler {
    protected $handler_type = 'sync_categories';

    private $departments = array();

    public function handle() {

        $this->log('getting departments and its categories');

        $this->get_department_data();

        foreach($this->departments as $department) { 
            $department_term = $this->get_term_by_meta(array('meta_key' => 'go_positive_department_id', 'meta_value' => $department->departmentid));

            if ($department_term) {
                wp_update_term($department_term->term_id, 'product_cat', array('name' => $department->description, 'slug' => sanitize_title( $department->description )));
            }
            else {
                $department_term = wp_insert_term($department->description, 'product_cat', array('parent' => 0));

                if ($department_term instanceof WP_Error) {
                    $this->log('failed on creating a category: ' . $department->description);
                    $this->log('', $department_term);
                }
                else {
                    add_term_meta($department_term['term_id'], 'go_positive_department_id', $department->departmentid);
                    $this->audit('new category has been added: ' . $department->description);
                }
            }

            foreach($department->categories as $category) { 
                $category_term = $this->get_term_by_meta(array('meta_key' => 'go_positive_category_id', 'meta_value' => $category->categoryid));

                if ($category_term) {
                    wp_update_term($category_term->term_id, 'product_cat', array('name' => $category->description, 'slug' => sanitize_title( $category->description )));
                }
                else {
                    $category_term = wp_insert_term($category->description, 'product_cat', array('parent' => $department_term['term_id']));

                    if ($category_term instanceof WP_Error) {
                        $this->log('failed on creating a category: ' . $category->description);
                        $this->log('', $category_term);
                    }
                    else {
                        add_term_meta($category_term['term_id'], 'go_positive_category_id', $category->categoryid);
                        $this->audit('new category has been added: ' . $category->description);
                    }
                }
            }

        }
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

    private function get_department_data() {
        $json_data = $this->get_json_data_from_file();

        if ($json_data) {
            $this->departments = $json_data->product_department_category_response->departments_categories->departments;
        }
    }

    private function get_json_data_from_file() {
        if(ini_get('allow_url_fopen')) {
            $file = CHEFS_CORNER_DATA_DIR . '/departments_catgories.json';
            
            $file_content = file_get_contents($file);
            $response = json_decode($file_content);
    
            return $response;
        }
        else {
            $url = CHEFS_CORNER_PLUGIN_URL . '_data/departments_catgories.json';

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