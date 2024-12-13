<?php
/*
 * Plugin Name: Gravity Forms Offload File Uploads
 * Plugin URI: https://github.com/FalkensMazeDev/gravityforms-offload-uploads/
 * Description: Offloads file uploads in Gravity Forms to Amazon S3 or FTP storage
 * Version: 1.0.0
 * Requires at least: 4.0
 * Requires PHP: 7.0
 * Author: Vital
 * Author URI: https://vtldesign.com
 * Author: FalkensMazeDev
 * Author URI: https://falkensmaze.dev
 * Text Domain: falken
 */
if (!defined('ABSPATH')) {
	exit;
}

define('GF_SIMPLE_ADDON_VERSION', '2.1');

add_action('gform_loaded', ['GF_Offload_File_Uploads_Addon', 'load'], 5);

class GF_Offload_File_Uploads_Addon {

	public static function load() {

		if (!method_exists('GFForms', 'include_addon_framework')) {
			return;
		}

		if (!class_exists('S3')) {
			require_once('includes/S3.php');
		}

		require_once('class-gravityforms-offload-file-uploads.php');

		GFAddOn::register('GF_Offload_File_Uploads');
	}
}

function gf_simple_addon() {
	return GF_Offload_S3_Uploads::get_instance();
}
// added 2024-12-13
if (!class_exists('S3')) { //checks to see if class exists before handling deleted files.
	add_action('gform_delete_lead', 'handle_deleted_entry_files', 10, 1);
}
function handle_deleted_entry_files($entry_id) {
    // Retrieve the entry data
    $entry = GFAPI::get_entry($entry_id);

    // Check if the entry retrieval was successful
    if (is_wp_error($entry)) {
        error_log("Error retrieving entry with ID {$entry_id}: " . $entry->get_error_message());
        return;
    }
    
    // Get the form associated with the entry
    $form_id = $entry['form_id'];
    $form = GFAPI::get_form($form_id);
    
    if (is_wp_error($form)) {
        error_log("Error retrieving form with ID {$form_id}: " . $form->get_error_message());
        return;
    }

    // Initialize an array to store file URLs
    $delete_files_urls = [];

    // Loop through the form fields to find file upload fields
    foreach ($form['fields'] as $field) {
        if ($field->type === 'fileupload') {
            $field_id = $field->id;
            if (!empty($entry[$field_id])) {
		    // checks to see if it is a multifile field and if so adds all files to the array
	            if ($field->multipleFiles == 1) {
	            	$files_string = str_replace( array('"', '[',']'), '', str_replace(array('\/'), '/', $entry[$field_id]) );
	            	$files_array = explode(",", $files_string);
	            	foreach ($files_array as $file) {
            			$delete_files_urls[] = $file;
            		}
	            } else {
	            	$delete_files_urls[] = $entry[$field_id];
	            }
	        }
        }
    }
    if (count($delete_files_urls) > 0) {
    	$gftoS3 = new GF_Offload_File_Uploads();
    	$gftoS3->init();

	    foreach ($delete_files_urls as $delete_file_url) {
			if ($delete_file_url != '') $gftoS3->delete_files_s3($delete_file_url);
	    }
	}

}
