<?php
class CC_AQ_WC_Delete_WC_Products extends CC_AQ_WC_Handler {
    protected $handler_type = 'delete_wc_products';

    private $limit = 50;
    private $total_rows = 0;
    private $processed_row_count = 0;
    private $page;

    public function handle() {
        $page = isset($this->data['page']) ? absint($this->data['page']) : 1;
        $date = date("Y-m-d h:i:sa");
        $this->page = $page;
        $date_touched = (isset($this->data['date_touched'])) ? $this->data['date_touched'] : $date;

        $products = $this->get_products();
        $this->total_rows  = $products->total;

        foreach ($products->products as $product) {
            $product_id = $product->get_ID();
            $post_meta_date_touched = get_post_meta( $product_id, 'aq_date_touched', true);

            if (!$post_meta_date_touched) {
                $product->delete();
                $this->log($product_id . ' deleted');
                $this->audit($product_id . ' deleted');
            }
            else {
                $post_date_touched = new DateTime($post_meta_date_touched);
                $aq_date_touched = new DateTime($date_touched);

                if ($post_date_touched != $aq_date_touched) {
                    $product->delete();
                    $this->log($product_id . ' deleted');
                    $this->audit($product_id . ' deleted');

                    $this->log('post_date_touched: ', $post_date_touched);
                    $this->log('aq_date_touched: ', $aq_date_touched);
                }
            }
            $this->processed_row_count++;
        }
        
        $percent_completed = $this->get_percent_complete();
        $page++;

        if ($percent_completed != 100) {
            $this->data = array('page' => $page, 'date_touched' => $date_touched);
            $this->next_handler = 'delete_wc_products';
        }
    }

    private function get_percent_complete() {
        return $this->total_rows ? floor( ( $this->get_total_processed() / $this->total_rows ) * 100 ) : 100;
    }

    public function get_total_processed() {
		return ( ( $this->page - 1 ) * $this->limit) + $this->processed_row_count;
	}

    private function get_products() {
        $args = array(
			'status'   => array( 'private', 'publish', 'draft', 'future', 'pending' ),
			'type'     => $this->get_product_types(),
			'limit'    => $this->limit,
			'page'     => $this->page,
			'orderby'  => array(
				'ID' => 'ASC',
			),
			'return'   => 'objects',
			'paginate' => true,
        );

        $products = wc_get_products($args);

        return $products;
    }

    private function get_product_types() {
        $product_types_to_export = array_merge( array_keys( wc_get_product_types() ), array( 'variation' ) );
        return array_map( array ($this , 'wc_clean'), $product_types_to_export );
    }

    public function wc_clean( $var ) {
        if ( is_array( $var ) ) {
            return array_map( array ($this , 'wc_clean'), $var );
        } else {
            return is_scalar( $var ) ? sanitize_text_field( $var ) : $var;
        }
    }
}