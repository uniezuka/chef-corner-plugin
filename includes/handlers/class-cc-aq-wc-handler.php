<?php

abstract class CC_AQ_WC_Handler {
    const AQ_PRODUCTS_API_URL = 'https://api.aq-fes.com/products-api/';
    const MANUFACTURERS_API = 'manufacturers';
    const CATEGORIES_API = 'categories';

    protected $api_key = '';
    protected $handler_type = '';

    protected $next_handler = '';
    protected $data = array();

    public function __construct($next_handler = '') {
        $this->next_handler = $next_handler;
        $this->api_key = get_option('chefs_corner_aq_api_key');
    }

    protected function log($message, $data = null) {
        file_put_contents(CHEFS_CORNER_LOG_FILE, $message . "\n", FILE_APPEND);

        if ($data) {
            file_put_contents(CHEFS_CORNER_LOG_FILE, var_export($data, true) . "\n", FILE_APPEND);
        }
    }

    protected function audit($message) {
        file_put_contents(CHEFS_CORNER_AUDIT_FILE, $message . "\n", FILE_APPEND);
    }

    protected function get_json_data($url) {
        if(ini_get('allow_url_fopen')) {
            $opts = array(
                'http'=>array(
                    'method'=>"GET",
                    'header'=>"Ocp-Apim-Subscription-Key: " . $this->api_key
                )
            );
    
            $context = stream_context_create($opts);
            $file_content = file_get_contents($url, false, $context);
            $data = json_decode($file_content);
    
            return $data->data;
        } 
        else {
            $ch = curl_init($url);

            $options = array(
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => array('Ocp-Apim-Subscription-Key: ' . $this->api_key)
            );

            curl_setopt_array($ch, $options);

            $result = curl_exec($ch);
            $data = json_decode($result);

            if(curl_errno($ch))
                $this->log('curl error for url: ' . $url, curl_error($ch));
            
            curl_close($ch);

            return $data->data;
        }
    }

    protected function get_array_unique($array, $keep_key_assoc = false){
        $duplicate_keys = array();
        $tmp = array();       
    
        foreach ($array as $key => $val){
            // convert objects to arrays, in_array() does not support objects
            if (is_object($val))
                $val = (array)$val;
    
            if (!in_array($val, $tmp))
                $tmp[] = $val;
            else
                $duplicate_keys[] = $key;
        }
    
        foreach ($duplicate_keys as $key)
            unset($array[$key]);
    
        return $keep_key_assoc ? $array : array_values($array);
    }

    protected function get_ancestral_aq_categories($category_id, $aq_categories) {
        $categories = array();

        foreach($aq_categories as $aq_category) {
            if ($aq_category->categoryId == $category_id) {
                $categories[] = $aq_category;
                break;
            }
            elseif (count($aq_category->subcategories)) {
                $sub_aq_category = $this->get_ancestral_aq_categories($category_id, $aq_category->subcategories);

                if (count($sub_aq_category)) {
                    $categories[] = $aq_category;
                    $categories = array_merge($categories, $sub_aq_category);

                    break;
                }
            }
        }

        return $categories;
    }

    protected function get_aq_manufacturers() {
        $url = self::AQ_PRODUCTS_API_URL . self::MANUFACTURERS_API;

        $json_data = $this->get_json_data($url);

        return $json_data;
    }

    protected function get_aq_categories() {
        $url = self::AQ_PRODUCTS_API_URL . self::CATEGORIES_API;

        $json_data = $this->get_json_data($url);

        return $json_data;
    }

    protected function get_sanitize_category_name($category_names) {
        $total = count($category_names);

        if ($total == 1) return sanitize_title($category_names[0]);

        $sanitized_name = '';
        $sanitized_name_prev = '';

        for ($ctr = $total- 1; $ctr >= 0; $ctr--) {
            $sanitized_name_current = sanitize_title($category_names[$ctr]);

            if ($sanitized_name_prev == '') {
                $sanitized_name_prev = $sanitized_name_current;
                $sanitized_name = $sanitized_name_current;
                continue;
            }

            if ($sanitized_name_prev == $sanitized_name_current) {
                $sanitized_name = $sanitized_name_current . '-' .  $sanitized_name;
                $sanitized_name_prev = $sanitized_name_current;
                continue;
            }

            break;
        } 

        return ($sanitized_name == '') ? sanitize_title($category_names[$total - 1]) : $sanitized_name;
    }

    protected function get_image_data($url) {
        if(ini_get('allow_url_fopen')) {
            $data = file_get_contents($url);

            return $data;
        }
        else {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_BINARYTRANSFER, 1);

            $data = curl_exec($ch);

            if(curl_errno($ch)) {
                $this->log('curl error for url: ' . $url, curl_error($ch));
                $data = null;
            }
            
            curl_close($ch);

            return $data;
        }
    }

    protected function is_attachment_exists($filename) {
        $attachment_args = array(
            'posts_per_page' => 1,
            'post_type'      => 'attachment',
            'name'           => $filename
        );

        $attachment_check = new Wp_Query($attachment_args);

        return $attachment_check->have_posts();
    }

    public function set_handler_type($handler_type = '') {
        $this->handler_type = $handler_type;
        return $this;
    }

    public function get_handler_type() {
        return $this->handler_type;
    }

    public function get_next_handler() {
        return $this->next_handler;
    }

    public function set_data($data = array()) {
        $this->data = $data;
        return $this;
    }

    public function get_data() {
        return $this->data;
    }

    public function handle() { }
}