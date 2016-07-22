<?php
if(!defined('WP_UNINSTALL_PLUGIN')){
    exit();
}

wp_clear_scheduled_hook('generate_hourly_backup');