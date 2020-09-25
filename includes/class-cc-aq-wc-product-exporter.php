<?php

class CC_AQ_WC_Product_Exporter {
    private $filename = 'cc-wc-export.csv';
    private $limit = 50;
    private $total_rows = 0;
    private $page = 1;
    private $exported_row_count = 0;
    private $product_types_to_export = array();
    private $additional_fields = array();

    public function __construct() {
		$this->set_product_types_to_export( array_merge( array_keys( wc_get_product_types() ), array( 'variation' ) ) );
    }
    
    public function set_product_types_to_export( $product_types_to_export ) {
		$this->product_types_to_export = array_map( array ($this , 'wc_clean'), $product_types_to_export );
	}

    public function set_filename($filename) {
		$this->filename = sanitize_file_name(str_replace('.csv', '', $filename) . '.csv');
    }
    
    private function get_default_columns() {
		return array(
            'id'                 => __( 'ID', 'woocommerce' ),
            'type'               => __( 'Type', 'woocommerce' ),
            'sku'                => __( 'SKU', 'woocommerce' ),
            'name'               => __( 'Name', 'woocommerce' ),
            'published'          => __( 'Published', 'woocommerce' ),
            'featured'           => __( 'Is featured?', 'woocommerce' ),
            'catalog_visibility' => __( 'Visibility in catalog', 'woocommerce' ),
            'short_description'  => __( 'Short description', 'woocommerce' ),
            'description'        => __( 'Description', 'woocommerce' ),
            'date_on_sale_from'  => __( 'Date sale price starts', 'woocommerce' ),
            'date_on_sale_to'    => __( 'Date sale price ends', 'woocommerce' ),
            'tax_status'         => __( 'Tax status', 'woocommerce' ),
            'tax_class'          => __( 'Tax class', 'woocommerce' ),
            'stock_status'       => __( 'In stock?', 'woocommerce' ),
            'stock'              => __( 'Stock', 'woocommerce' ),
            'low_stock_amount'   => __( 'Low stock amount', 'woocommerce' ),
            'backorders'         => __( 'Backorders allowed?', 'woocommerce' ),
            'sold_individually'  => __( 'Sold individually?', 'woocommerce' ),
            /* translators: %s: weight */
            'weight'             => sprintf( __( 'Weight (%s)', 'woocommerce' ), get_option( 'woocommerce_weight_unit' ) ),
            /* translators: %s: length */
            'length'             => sprintf( __( 'Length (%s)', 'woocommerce' ), get_option( 'woocommerce_dimension_unit' ) ),
            /* translators: %s: width */
            'width'              => sprintf( __( 'Width (%s)', 'woocommerce' ), get_option( 'woocommerce_dimension_unit' ) ),
            /* translators: %s: Height */
            'height'             => sprintf( __( 'Height (%s)', 'woocommerce' ), get_option( 'woocommerce_dimension_unit' ) ),
            'reviews_allowed'    => __( 'Allow customer reviews?', 'woocommerce' ),
            'purchase_note'      => __( 'Purchase note', 'woocommerce' ),
            'sale_price'         => __( 'Sale price', 'woocommerce' ),
            'regular_price'      => __( 'Regular price', 'woocommerce' ),
            'category_ids'       => __( 'Categories', 'woocommerce' ),
            'tag_ids'            => __( 'Tags', 'woocommerce' ),
            'shipping_class_id'  => __( 'Shipping class', 'woocommerce' ),
            'images'             => __( 'Images', 'woocommerce' ),
            'download_limit'     => __( 'Download limit', 'woocommerce' ),
            'download_expiry'    => __( 'Download expiry days', 'woocommerce' ),
            'parent_id'          => __( 'Parent', 'woocommerce' ),
            'grouped_products'   => __( 'Grouped products', 'woocommerce' ),
            'upsell_ids'         => __( 'Upsells', 'woocommerce' ),
            'cross_sell_ids'     => __( 'Cross-sells', 'woocommerce' ),
            'product_url'        => __( 'External URL', 'woocommerce' ),
            'button_text'        => __( 'Button text', 'woocommerce' ),
            'menu_order'         => __( 'Position', 'woocommerce' ),
        );
    }
    
    private function get_column_names() {
        $names = array();

        foreach($this->get_default_columns() as $key => $value) 
            $names[] = $value;

        foreach($this->additional_fields as $key => $value) 
            $names[] = $value;
        
        return $names;
    }

    private function get_data_to_export() {
        $args = array(
			'status'   => array( 'private', 'publish', 'draft', 'future', 'pending' ),
			'type'     => $this->product_types_to_export,
			'limit'    => $this->get_limit(),
			'page'     => $this->get_page(),
			'orderby'  => array(
				'ID' => 'ASC',
			),
			'return'   => 'objects',
			'paginate' => true,
        );

        $data = array();
        $fields_ids = array();

        $products = wc_get_products($args);
        $this->total_rows  = $products->total;

        foreach($this->get_default_columns() as $key => $value) 
            $fields_ids[] = $key;

        foreach ($products->products as $product) {
            $data[] = $this->generate_row_data($product, $fields_ids);
		}

		return $data;
    }

    private function generate_row_data($product, $fields_ids) {
        $row = array();
                
        foreach($fields_ids as $field_id) {
            $value= '';

            if (is_callable(array( $this, "get_column_value_{$field_id}"))) {
                $value = $this->{"get_column_value_{$field_id}"}($product);
            }
            elseif (is_callable(array( $product, "get_{$field_id}" ) ) ) {
                $value = $product->{"get_{$field_id}"}('edit');
            }
            else {
                $value = '';
            }

            if ( 'description' === $field_id || 'short_description' === $field_id ) {
                $value = $this->filter_description_field($value);
            }

            $row[] = $this->format_data($value);
        }

        $this->prepare_meta_for_export($product, $row);

        return $row;
    }

    private function prepare_meta_for_export( $product, &$row ) {
        $meta_data = $product->get_meta_data();

        if ( count( $meta_data ) ) {
            $i = 1;
            foreach ( $meta_data as $meta ) {

                if ( !is_scalar($meta->value)) {
                    continue;
                }

                $column_key = 'meta:' . esc_attr( $meta->key );
                /* translators: %s: meta data name */
                $this->additional_fields[$column_key] = sprintf( __( 'Meta: %s', 'woocommerce' ), $meta->key );
                $row[] = $meta->value;
                $i ++;
            }
        }
	}

    private function format_data( $data ) {
		if ( ! is_scalar( $data ) ) {
			if ( is_a( $data, 'WC_Datetime' ) ) {
				$data = $data->date( 'Y-m-d G:i:s' );
			} else {
				$data = ''; // Not supported.
			}
		} elseif ( is_bool( $data ) ) {
			$data = $data ? 1 : 0;
		}

		return $this->escape_data( $data );
    }
    
    private function escape_data( $data ) {
		$active_content_triggers = array( '=', '+', '-', '@' );

		if ( in_array( mb_substr( $data, 0, 1 ), $active_content_triggers, true ) ) {
			$data = "'" . $data;
		}

		return $data;
	}

    protected function filter_description_field( $description ) {
		$description = str_replace( '\n', "\\\\n", $description );
		$description = str_replace( "\n", '\n', $description );
		return $description;
	}

    protected function get_column_value_low_stock_amount( $product ) {
		return $product->managing_stock() && $product->get_low_stock_amount( 'edit' ) ? $product->get_low_stock_amount( 'edit' ) : '';
	}

    protected function get_column_value_backorders( $product ) {
		$backorders = $product->get_backorders( 'edit' );

		switch ( $backorders ) {
			case 'notify':
				return 'notify';
			default:
				return wc_string_to_bool( $backorders ) ? 1 : 0;
		}
	}

    protected function get_column_value_stock_status( $product ) {
		$status = $product->get_stock_status( 'edit' );

		if ( 'onbackorder' === $status ) {
			return 'backorder';
		}

		return 'instock' === $status ? 1 : 0;
	}

    protected function get_column_value_stock( $product ) {
		$manage_stock   = $product->get_manage_stock( 'edit' );
		$stock_quantity = $product->get_stock_quantity( 'edit' );

		if ( $product->is_type( 'variation' ) && 'parent' === $manage_stock ) {
			return 'parent';
		} elseif ( $manage_stock ) {
			return $stock_quantity;
		} else {
			return '';
		}
	}

    protected function get_column_value_download_expiry( $product ) {
		return $product->is_downloadable() && $product->get_download_expiry( 'edit' ) ? $product->get_download_expiry( 'edit' ) : '';
	}

    protected function get_column_value_download_limit( $product ) {
		return $product->is_downloadable() && $product->get_download_limit( 'edit' ) ? $product->get_download_limit( 'edit' ) : '';
	}

    protected function get_column_value_grouped_products( $product ) {
		if ( 'grouped' !== $product->get_type() ) {
			return '';
		}

		$grouped_products = array();
		$child_ids        = $product->get_children( 'edit' );
		foreach ( $child_ids as $child_id ) {
			$child = wc_get_product( $child_id );
			if ( ! $child ) {
				continue;
			}

			$grouped_products[] = $child->get_sku( 'edit' ) ? $child->get_sku( 'edit' ) : 'id:' . $child_id;
		}
		return $this->implode_values( $grouped_products );
	}

    protected function get_column_value_parent_id( $product ) {
		if ( $product->get_parent_id( 'edit' ) ) {
			$parent = wc_get_product( $product->get_parent_id( 'edit' ) );
			if ( ! $parent ) {
				return '';
			}

			return $parent->get_sku( 'edit' ) ? $parent->get_sku( 'edit' ) : 'id:' . $parent->get_id();
		}
		return '';
    }
    
    protected function prepare_linked_products_for_export( $linked_products ) {
		$product_list = array();

		foreach ( $linked_products as $linked_product ) {
			if ( $linked_product->get_sku() ) {
				$product_list[] = $linked_product->get_sku();
			} else {
				$product_list[] = 'id:' . $linked_product->get_id();
			}
		}

		return $this->implode_values( $product_list );
	}

    protected function get_column_value_upsell_ids( $product ) {
		return $this->prepare_linked_products_for_export( array_filter( array_map( 'wc_get_product', (array) $product->get_upsell_ids( 'edit' ) ) ) );
	}

    protected function get_column_value_cross_sell_ids( $product ) {
		return $this->prepare_linked_products_for_export( array_filter( array_map( 'wc_get_product', (array) $product->get_cross_sell_ids( 'edit' ) ) ) );
	}

    protected function get_column_value_images( $product ) {
		$image_ids = array_merge( array( $product->get_image_id( 'edit' ) ), $product->get_gallery_image_ids( 'edit' ) );
		$images    = array();

		foreach ( $image_ids as $image_id ) {
			$image = wp_get_attachment_image_src( $image_id, 'full' );

			if ( $image ) {
				$images[] = $image[0];
			}
		}

		return $this->implode_values( $images );
	}

    protected function get_column_value_shipping_class_id( $product ) {
		$term_ids = $product->get_shipping_class_id( 'edit' );
		return $this->format_term_ids( $term_ids, 'product_shipping_class' );
	}

    protected function get_column_value_tag_ids( $product ) {
		$term_ids = $product->get_tag_ids( 'edit' );
		return $this->format_term_ids( $term_ids, 'product_tag' );
	}

    protected function get_column_value_type($product) {
		$types   = array();
		$types[] = $product->get_type();

		if ( $product->is_downloadable() ) {
			$types[] = 'downloadable';
		}

		if ( $product->is_virtual() ) {
			$types[] = 'virtual';
		}

		return $this->implode_values($types);
    }

    public function format_term_ids( $term_ids, $taxonomy ) {
		$term_ids = wp_parse_id_list( $term_ids );

		if ( ! count( $term_ids ) ) {
			return '';
		}

		$formatted_terms = array();

		if ( is_taxonomy_hierarchical( $taxonomy ) ) {
			foreach ( $term_ids as $term_id ) {
				$formatted_term = array();
				$ancestor_ids   = array_reverse( get_ancestors( $term_id, $taxonomy ) );

				foreach ( $ancestor_ids as $ancestor_id ) {
					$term = get_term( $ancestor_id, $taxonomy );
					if ( $term && ! is_wp_error( $term ) ) {
						$formatted_term[] = $term->name;
					}
				}

				$term = get_term( $term_id, $taxonomy );

				if ( $term && ! is_wp_error( $term ) ) {
					$formatted_term[] = $term->name;
				}

				$formatted_terms[] = implode( ' > ', $formatted_term );
			}
		} else {
			foreach ( $term_ids as $term_id ) {
				$term = get_term( $term_id, $taxonomy );

				if ( $term && ! is_wp_error( $term ) ) {
					$formatted_terms[] = $term->name;
				}
			}
		}

		return $this->implode_values( $formatted_terms );
	}

    protected function get_column_value_category_ids( $product ) {
		$term_ids = $product->get_category_ids( 'edit' );
		return $this->format_term_ids( $term_ids, 'product_cat' );
	}

    protected function get_column_value_regular_price( $product ) {
		return wc_format_localized_price($product->get_regular_price());
	}

    protected function get_column_value_published( $product ) {
		$statuses = array(
			'draft'   => -1,
			'private' => 0,
			'publish' => 1,
		);

		$status = $product->get_status('edit');

		return isset( $statuses[ $status ] ) ? $statuses[ $status ] : -1;
    }
    
    protected function get_column_value_sale_price( $product ) {
		return wc_format_localized_price($product->get_sale_price( 'view' ) );
	}
    
    protected function implode_values($values) {
		$values_to_implode = array();

		foreach ( $values as $value ) {
			$value               = (string) is_scalar( $value ) ? $value : '';
			$values_to_implode[] = str_replace( ',', '\\,', $value );
		}

		return implode( ', ', $values_to_implode );
	}

    public function export() {
        $data_to_export = $this->get_data_to_export();
        $titles = $this->get_column_names();

        $data = array();
        $csv_file = CHEFS_CORNER_PLUGIN_DIR . '_backups/'. $this->filename;

        if ($this->page == 1) {
            $data[] = $titles;

            if (file_exists($csv_file)) {
                unlink($csv_file);
            }
        }

        $data = array_merge($data, $data_to_export);

        $csv_handler = fopen($csv_file, 'a');

        foreach ($data as $record) {
            fputcsv($csv_handler, $record);
            $this->exported_row_count++;
        }

        fclose($csv_handler);
    }

    public function get_page() {
		return $this->page;
	}

	public function set_page( $page ) {
		$this->page = absint( $page );
	}

	public function get_total_exported() {
		return ( ( $this->get_page() - 1 ) * $this->get_limit() ) + $this->exported_row_count;
	}

	public function get_percent_complete() {
		return $this->total_rows ? floor( ( $this->get_total_exported() / $this->total_rows ) * 100 ) : 100;
    }
    
    public function get_limit() {
		return $this->limit;
    }
    
    public function wc_clean( $var ) {
        if ( is_array( $var ) ) {
            return array_map( array ($this , 'wc_clean'), $var );
        } else {
            return is_scalar( $var ) ? sanitize_text_field( $var ) : $var;
        }
    }
}