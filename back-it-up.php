<?php
defined('ABSPATH') or die('Access denied');
/*
Plugin Name: Back-it-up
Description: Plugin for generating website backups
Version: 0.1
Author: Jadon Naas
*/

function backup_install(){
    if (!wp_next_scheduled ('generate_hourly_backup')) {
        wp_schedule_event(time(), 'hourly', 'generate_hourly_backup');
    }
}

register_activation_hook(__FILE__, 'backup_install');
register_deactivation_hook(__FILE__, 'backup_deactivation');

add_action('generate_hourly_backup', 'generate_backup');
add_action('admin_menu', 'backup_menu');

function backup_deactivation(){
    wp_clear_scheduled_hook('generate_hourly_backup');
}

function backup_menu(){
    add_menu_page('Backup Generator', 'Backup', 'manage_options',
    'backup/backup.php', 'backup_admin', 'none', '4.105');
}

function backup_admin(){
    if(!current_user_can('manage_options')){
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }
    ob_start();
    backup_admin_code();
    backup_admin_submitted();
    echo ob_get_clean();
}

function backup_admin_submitted(){
    if(isset($_POST['backup-admin-submitted'])){
        $backup_status = generate_backup();
        if($backup_status == 1){
            echo "<h3>Backup request processed</h3>";
        }
    }
}

function backup_admin_code(){
    echo "
    <h1>File Backup Generator Plugin</h1>
    <p>Use the button below to generate a backup of this WordPress website. It may take a few minutes for the backup to complete.</p>
    <form action='" . esc_url($_SERVER['REQUEST_URI']) . "' method='post'>
    <input type='submit' name='backup-admin-submitted' value='Submit' />
    </form>
    ";
    $timestamp =  wp_next_scheduled('generate_hourly_backup');
    if($timestamp){
        echo "<p>Next backup scheduled for: " . date('Y-m-d H-m-s e', $timestamp) . "</p>";
    } else {
        echo "<p>No currently scheduled backup.</p>";
    }
}

     
function generate_backup(){
    $backup_log = fopen(__DIR__ . '/saved_backups/backup.log', 'w+');
    $path = $_SERVER['DOCUMENT_ROOT'];
    $date = new DateTime();
    $formatted_date = $date->format('Y-m-d_H_i_s-e');
    $zip = new ZipArchive();
    $zip_file = __DIR__ . '/saved_backups/backup-' . $formatted_date . '.zip';
    $zip_opened = $zip->open($zip_file, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path),
        RecursiveIteratorIterator::LEAVES_ONLY);
        
    foreach ($files as $name => $file){
        if(!$file->isDir())
        {
            $file_path = $file->getRealPath();
            $relative_path = substr($file_path, strlen($path) + 1);
            $relative_path_extension = substr($relative_path, -4);
            if($relative_path_extension != '.zip'){
                fwrite($backup_log, $file_path . "\n");
                $zip->addFile($file_path, $relative_path);
            }
        }
    }
    fwrite($backup_log, "Backup completed.\n");
    fclose($backup_log);
    $success = $zip->close();
    return $success;
}
