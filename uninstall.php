<?php
if(!defined('WP_UNINSTALL_PLUGIN')){ 
	exit;
}

delete_option('automatic_post_date_filler_meta');
delete_option('automatic_post_date_filler_settings');
