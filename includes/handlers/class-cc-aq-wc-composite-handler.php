<?php 

class CC_AQ_WC_Composite_Handler extends CC_AQ_WC_Handler {
    private $handlers = array();

    public function add($handler) {
        array_push($this->handlers, $handler);
        return $this;
    }

    public function handle() {
        $current_handler = null;

        foreach($this->handlers as $handler) {
            if ($this->get_handler_type() == $handler->get_handler_type()) {
                $current_handler = $handler;
                break;
            }
        }

        if ($current_handler == null) return;

        $current_handler->set_data($this->get_data());
        $current_handler->handle();

        $this->next_handler = $current_handler->get_next_handler();
        $this->data = $current_handler->get_data();
    }
}