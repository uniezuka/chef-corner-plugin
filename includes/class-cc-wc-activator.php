<?php
class CC_WC_Activator {
    public static function activate() {
        if (!wp_next_scheduled( 'cc_migrate')) {
            wp_schedule_event(time(), 'fifteendays', 'cc_migrate');
        }
    }
}