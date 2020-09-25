<?php
class CC_AQ_WC_Activator {
    public static function activate() {
        if (!wp_next_scheduled( 'cc_migrate_from_aq')) {
            wp_schedule_event(time(), 'fifteendays', 'cc_migrate_from_aq');
        }
    }
}