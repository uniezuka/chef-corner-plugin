<?php
class CC_AQ_WC_Deactivator {
    public static function deactivate() {
        wp_clear_scheduled_hook('cc_migrate_from_aq');
    }
}