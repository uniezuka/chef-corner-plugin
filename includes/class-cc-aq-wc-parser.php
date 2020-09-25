<?php

class CC_AQ_WC_Parser extends WP_Async_Request {
    protected $action = 'cc_aq_wc_parser';

    private $handler = null;

    public function __construct() {
        require_once CHEFS_CORNER_PLUGIN_DIR . 'includes/handlers/class-cc-aq-wc-handler.php';
        require_once CHEFS_CORNER_PLUGIN_DIR . 'includes/handlers/class-cc-aq-wc-composite-handler.php';
        require_once CHEFS_CORNER_PLUGIN_DIR . 'includes/handlers/class-cc-aq-wc-init.php';
        require_once CHEFS_CORNER_PLUGIN_DIR . 'includes/handlers/class-cc-aq-wc-get-manufacturers.php';
        require_once CHEFS_CORNER_PLUGIN_DIR . 'includes/handlers/class-cc-aq-wc-backup.php';
        require_once CHEFS_CORNER_PLUGIN_DIR . 'includes/handlers/class-cc-aq-wc-get-aq-categories.php';
        require_once CHEFS_CORNER_PLUGIN_DIR . 'includes/handlers/class-cc-aq-wc-sync-wc-categories.php';
        require_once CHEFS_CORNER_PLUGIN_DIR . 'includes/handlers/class-cc-aq-wc-sync-wc-products.php';
        require_once CHEFS_CORNER_PLUGIN_DIR . 'includes/handlers/class-cc-aq-wc-attach-images.php';
        require_once CHEFS_CORNER_PLUGIN_DIR . 'includes/handlers/class-cc-aq-wc-delete-wc-products.php';
        require_once CHEFS_CORNER_PLUGIN_DIR . 'includes/handlers/class-cc-aq-wc-send-mail.php';

        $this->handler = new CC_AQ_WC_Composite_Handler();

        parent::__construct();
    }

    protected function handle() {
        $handler_type = !empty( $_POST['handler_type'] ) ? $_POST['handler_type'] : '';
        $data = !empty( $_POST['data'] ) ? $_POST['data'] : '';
        
        $this
            ->handler
            ->set_handler_type($handler_type)
            ->set_data($data)
            ->add(new CC_AQ_WC_Init('get_manufacturers'))
            ->add(new CC_AQ_WC_Get_Manufacturers('create_woocommerce_backup'))
            ->add(new CC_AQ_WC_Backup('get_aq_categories'))
            ->add(new CC_AQ_WC_Get_AQ_Categories('sync_wc_categories'))
            ->add(new CC_AQ_WC_Sync_WC_Categories('sync_wc_products'))
            ->add(new CC_AQ_WC_Sync_WC_Products('attach_images'))
            ->add(new CC_AQ_WC_Attach_Images('delete_wc_products'))
            ->add(new CC_AQ_WC_Delete_WC_Products('send_mail'))
            ->add(new CC_AQ_WC_Send_Mail())
            ->handle();
        
        /*
        $this
            ->handler
            ->set_handler_type($handler_type)
            ->set_data($data)
            ->add(new CC_AQ_WC_Init('get_manufacturers'))
            ->add(new CC_AQ_WC_Get_Manufacturers('get_aq_categories'))
            ->add(new CC_AQ_WC_Get_AQ_Categories('sync_wc_products'))
            ->add(new CC_AQ_WC_Sync_WC_Products('attach_images'))
            ->add(new CC_AQ_WC_Attach_Images('send_mail'))
            ->handle();
        */

        /*
        $this
            ->handler
            ->set_handler_type($handler_type)
            ->set_data($data)
            ->add(new CC_AQ_WC_Init('delete_wc_products'))
            ->add(new CC_AQ_WC_Delete_WC_Products())
            ->handle();
        */

        $next_handler = $this->handler->get_next_handler();
        $next_data = $this->handler->get_data();

        if ($next_handler == '' || $next_handler == 'completed') {
            $date = date("Y-m-d h:i:sa");

            file_put_contents(CHEFS_CORNER_LOG_FILE, 'finished importing', FILE_APPEND);
        }
        else {
            $this->data(array('handler_type' => $next_handler, 'data' => $next_data))->dispatch();
        }
    }
}