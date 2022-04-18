<?php
class CC_WC_Deactivator {
    public static function deactivate() {
        wp_clear_scheduled_hook('cc_migrate');
    }
}