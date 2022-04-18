<?php

class CC_WC_Parser extends WP_Async_Request {
    protected $action = 'cc_wc_parser';

    private $handler = null;

    public function __construct() {
        require_once CHEFS_CORNER_PLUGIN_DIR . 'includes/handlers/aq/class-cc-aq-wc-handler.php';

        require_once CHEFS_CORNER_PLUGIN_DIR . 'includes/handlers/go-positive/class-cc-wc-go-positive-handler.php';
        require_once CHEFS_CORNER_PLUGIN_DIR . 'includes/handlers/go-positive/class-cc-wc-go-positive-init.php';
        require_once CHEFS_CORNER_PLUGIN_DIR . 'includes/handlers/go-positive/class-cc-wc-go-positive-backup.php';
        require_once CHEFS_CORNER_PLUGIN_DIR . 'includes/handlers/go-positive/class-cc-wc-go-positive-download-departments-categories.php';
        require_once CHEFS_CORNER_PLUGIN_DIR . 'includes/handlers/go-positive/class-cc-wc-go-positive-sync-categories.php';
        require_once CHEFS_CORNER_PLUGIN_DIR . 'includes/handlers/go-positive/class-cc-wc-go-positive-download-products.php';
        require_once CHEFS_CORNER_PLUGIN_DIR . 'includes/handlers/go-positive/class-cc-wc-go-positive-sync-products.php';
        require_once CHEFS_CORNER_PLUGIN_DIR . 'includes/handlers/go-positive/class-cc-wc-go-positive-attach-images.php';
        require_once CHEFS_CORNER_PLUGIN_DIR . 'includes/handlers/go-positive/class-cc-wc-go-positive-delete-wc-products.php';
        require_once CHEFS_CORNER_PLUGIN_DIR . 'includes/handlers/class-cc-wc-send-mail.php';
        require_once CHEFS_CORNER_PLUGIN_DIR . 'includes/handlers/class-cc-wc-composite-handler.php';

        $this->handler = new CC_WC_Composite_Handler();

        parent::__construct();
    }

    protected function handle() {
        $handler_type = !empty( $_POST['handler_type'] ) ? $_POST['handler_type'] : '';
        $data = !empty( $_POST['data'] ) ? $_POST['data'] : '';

        $this
            ->handler
            ->set_handler_type($handler_type)
            ->set_data($data)
            ->add(new CC_WC_Go_Positive_Init('create_woocommerce_backup'))
            ->add(new CC_WC_Go_Postive_Backup('download_departments_categories'))
            ->add(new CC_WC_Go_Positive_Download_Departments_Categories('sync_categories'))
            ->add(new CC_WC_Go_Positive_Sync_Categories('download_products'))
            ->add(new CC_WC_Go_Positive_Download_Products('sync_products'))
            ->add(new CC_WC_Go_Positive_Sync_Products('attach_images'))
            ->add(new CC_WC_Go_Positive_Attach_Images('delete_wc_products'))
            ->add(new CC_WC_Go_Positive_Delete_WC_Products('send_mail'))
            ->add(new CC_WC_Send_Mail())
            ->handle();

        $next_handler = $this->handler->get_next_handler();
        $next_data = $this->handler->get_data();

        if ($next_handler == '' || $next_handler == 'completed') {
            $date = date("Y-m-d h:i:sa");

            file_put_contents(CHEFS_CORNER_LOG_FILE, 'finished importing on ' . $date, FILE_APPEND);
        }
        else {
            $this->data(array('handler_type' => $next_handler, 'data' => $next_data))->dispatch();
        }
    }
}