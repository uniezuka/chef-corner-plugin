<?php
class CC_AQ_WC_Sync_WC_Categories extends CC_AQ_WC_Handler {
    protected $handler_type = 'sync_wc_categories';

    private $ruleset = array();

    public function handle() {
        $manufacturer_ids = $this->data['manufacturer_ids'];
        $manufacturer_id = $this->data['manufacturer_id'];
        $category_ids = $this->data['category_ids'];

        $this->ruleset = get_option('chefs_corner_category_ruleset');

        if (!isset($manufacturer_id)) {
            $this->next_handler = '';
            return;
        }

        $this->log('importing categories from manufacturer[' . $manufacturer_id . ']...');

        $this->aq_categories = $this->get_aq_categories();

        foreach($category_ids as $category_id) {
            $ancestral_aq_categories = $this->get_ancestral_aq_categories($category_id, $this->aq_categories);

            $parent_term_id = 0;
            $category_names = array();
            $is_excluded = false;

            foreach($ancestral_aq_categories as $ancestral_aq_category) {
                $category_name = $ancestral_aq_category->name;
                $term = $this->get_term_by_meta(array('meta_key' => 'aq_category_id', 'meta_value' => $ancestral_aq_category->categoryId));

                $category_name = $this->get_renamed_category_name($category_name);
                $is_excluded = ($is_excluded) ? $is_excluded : $this->is_excluded_aq_category($ancestral_aq_category->categoryId);

                if ($is_excluded) {
                    if ($term) {
                        wp_delete_term($term->term_id, 'product_cat');
                        $this->audit('category[' . $category_name . '] has been removed.');
                    }

                    continue;
                }

                if ($term) {
                    wp_update_term($term->term_id, 'product_cat', array('name' => $category_name, 'slug' => sanitize_title( $category_name )));
                    $parent_term_id = $term->term_id;
                    $this->attach_thumbnail($term->term_id, $ancestral_aq_category);
                    continue;
                }

                $term = wp_insert_term($category_name, 'product_cat', array('parent' => $parent_term_id));

                if ($term instanceof WP_Error) {
                    $this->log('failed on creating a category: ' . $category_name);
                    $this->log('', $term);
                    continue;
                }

                if ($term) {
                    add_term_meta($term['term_id'], 'aq_category_id', $ancestral_aq_category->categoryId);
                    $parent_term_id = $term['term_id'];
                    $this->attach_thumbnail($term['term_id'], $ancestral_aq_category);
                    $this->audit('new category has been added: ' . $category_name);
                }
            }
        }

        $this->log('finished importing categories for manufacturer[' . $manufacturer_id . ']');

        $key = array_search($manufacturer_id, $manufacturer_ids);
        $key++;

        if (count($manufacturer_ids) > $key) {
            $this->data = array('manufacturer_ids' => $manufacturer_ids, 'manufacturer_id' => $manufacturer_ids[$key]);
            $this->next_handler = 'get_aq_categories';
        }
        else {
            $date = date("Y-m-d h:i:sa");
            $this->data = array('manufacturer_ids' => $manufacturer_ids, 'manufacturer_id' => $manufacturer_ids[0], 'date_touched' => $date);
        }
    }

    private function attach_thumbnail($term_id, $aq_category) {
        if (isset($aq_category->picture)) {
            if ($aq_category->picture->mediaType != 'picture') return;

            $image_url = $aq_category->picture->url;
            $url_array = explode('/', $image_url);
            $image_id = $url_array[count($url_array)-2];

            $path_parts = pathinfo(basename($image_url, '?' . parse_url($image_url, PHP_URL_QUERY)));
            $image_name = 'aq_cat_' . $image_id . '.' . $path_parts['extension'];
            $filename = basename($image_name); 

            $attachment = $this->get_attachment($filename);
            $attach_id = 0;

            if ($attachment) {
                $attach_id = $attachment->ID;
            }
            else {
                $attach_id = $this->create_attachment($image_url, $filename, 0);
            }

            update_term_meta( $term_id, 'thumbnail_id', $attach_id );

            //$this->log('image attached to product category with id: ' . $term_id);
            //$this->audit('image attached to product category with id: ' . $term_id);
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

    private function get_renamed_category_name($category_name) {
        $renamed_rules = array_filter($this->ruleset, function($data) {
            return $data->rule_type == 'rename';
        });

        if (count($renamed_rules) <= 0) return $category_name;

        $new_name = $category_name;

        foreach($renamed_rules as $value) {
            if ($value->old_category_name == $category_name) {
                $new_name = $value->new_category_name;
                break;
            }
        }

        return $new_name;
    }

    private function is_excluded_aq_category($categoryId) {
        $exclude_rules = array_filter($this->ruleset, function($data) {
            return $data->rule_type == 'exclude';
        });

        if (count($exclude_rules) <= 0) return false;

        $is_excluded = false;

        foreach($exclude_rules as $value) {
            if ($value->exclude_category_id == $categoryId) {
                $is_excluded = true;
                break;
            }
        }

        return $is_excluded;
    }
}