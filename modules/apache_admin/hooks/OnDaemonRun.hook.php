<?php


	echo fs_filehandler::NewLine() . "START Apache Config Hook." . fs_filehandler::NewLine();
	if (ui_module::CheckModuleEnabled('Apache Config')){
		echo "Apache Admin module ENABLED..." . fs_filehandler::NewLine();
		if (ctrl_options::GetOption('apache_changed') == strtolower("true")){
			echo "Apache Config has changed..." . fs_filehandler::NewLine();
			if (ctrl_options::GetOption('apache_backup') == strtolower("true")){
				echo "Backing up Apache Config to: ".ctrl_options::GetOption('apache_budir'). fs_filehandler::NewLine();
				BackupVhostConfigFile();
			}
			echo "Begin writing Apache Config to: ".ctrl_options::GetOption('apache_vhost'). fs_filehandler::NewLine();
			WriteVhostConfigFile();
			echo "Finished writting Apache Config... Now reloading Apache..." . fs_filehandler::NewLine();
		} else {
			echo "Apache Config has NOT changed...nothing to do." . fs_filehandler::NewLine();
		}
	} else {
		echo "Apache Admin module DISABLED...nothing to do." . fs_filehandler::NewLine();
	}
	echo "END Apache Config Hook." . fs_filehandler::NewLine();

    function WriteVhostConfigFile() {
		include('cnf/db.php');
		$z_db_user = $user;
		$z_db_pass = $pass;
		try {	
			$zdbh = new db_driver("mysql:host=localhost;dbname=" . $dbname . "", $z_db_user, $z_db_pass);
		} catch (PDOException $e) {

		}

        $line = "################################################################" 			. fs_filehandler::NewLine();
        $line .= "# Apache VHOST configuration file" 		                      			. fs_filehandler::NewLine();
        $line .= "# Automatically generated by ZPanel " . sys_versions::ShowZpanelVersion() . fs_filehandler::NewLine();
        $line .= "################################################################" 		. fs_filehandler::NewLine();
        $line .= "" 																		. fs_filehandler::NewLine();

        // ZPanel default virtual host container
        $line .= "NameVirtualHost *:" . ctrl_options::GetOption('apache_port') . "" 		. fs_filehandler::NewLine();
        $line .= "" 																		. fs_filehandler::NewLine();
        $line .= "# Configuration for ZPanel control panel." 								. fs_filehandler::NewLine();
        $line .= "<VirtualHost *:" . ctrl_options::GetOption('apache_port') . ">" 			. fs_filehandler::NewLine();
        $line .= "ServerAdmin zadmin@ztest.com" . fs_filehandler::NewLine();
        $line .= "DocumentRoot \"" . ctrl_options::GetOption('zpanel_root') . "\"" 			. fs_filehandler::NewLine();
        $line .= "ServerName " . ctrl_options::GetOption('zpanel_domain') . "" 				. fs_filehandler::NewLine();
        $line .= "ServerAlias *." . ctrl_options::GetOption('zpanel_domain') . "" 			. fs_filehandler::NewLine();
        $line .= "AddType application/x-httpd-php .php" 									. fs_filehandler::NewLine();
        $line .= "<Directory \"" . ctrl_options::GetOption('zpanel_root') . "\">" 			. fs_filehandler::NewLine();
        $line .= "Options FollowSymLinks" 													. fs_filehandler::NewLine();
        $line .= "	AllowOverride All" 														. fs_filehandler::NewLine();
        $line .= "	Order allow,deny" 														. fs_filehandler::NewLine();
        $line .= "	Allow from all" 														. fs_filehandler::NewLine();
        $line .= "</Directory>" 															. fs_filehandler::NewLine();
        $line .= "" 																		. fs_filehandler::NewLine();
        $line .= "# Custom settings are loaded below this line (if any exist)" 				. fs_filehandler::NewLine();

        // Global custom zpanel entry
        $line .= ctrl_options::GetOption('global_zpcustom');

        $line .= "</VirtualHost>" 															. fs_filehandler::NewLine();

        $line .= "" . fs_filehandler::NewLine();
        $line .= "################################################################" 		. fs_filehandler::NewLine();
        $line .= "# ZPanel generated VHOST configurations below....."		 				. fs_filehandler::NewLine();
        $line .= "################################################################" 		. fs_filehandler::NewLine();
        $line .= "" 																		. fs_filehandler::NewLine();

        // Zpanel virtual host container configuration
        $sql = $zdbh->prepare("SELECT * FROM x_vhosts WHERE vh_deleted_ts IS NULL");
        $sql->execute();
        while ($rowvhost = $sql->fetch()) {
            //Domain is enabled
            if ($rowvhost['vh_enabled_in'] == 1 && ctrl_users::CheckUserEnabled($rowvhost['vh_acc_fk']) || $rowvhost['vh_enabled_in'] == 1 && ctrl_options::GetOption('apache_allow_disabled') == strtolower("true")) {
			
		
				
				// Set the vhosts to "LIVE"
        		$vsql = $zdbh->prepare("UPDATE x_vhosts SET vh_active_in=1 WHERE vh_id_pk=". $rowvhost['vh_id_pk'] . "");
        		$vsql->execute();

                // Get account username vhost is create with
                $username = $zdbh->query("SELECT ac_user_vc FROM x_accounts where ac_id_pk=" . $rowvhost['vh_acc_fk'] . "")->fetch();

                $line .= "# DOMAIN: " . $rowvhost['vh_name_vc'] . fs_filehandler::NewLine();
                $line .= "<virtualhost *:" . ctrl_options::GetOption('apache_port') . ">" . fs_filehandler::NewLine();

                // Bandwidth Settings
                //$line .= "Include C:/ZPanel/bin/apache/conf/mod_bw/mod_bw/mod_bw_Administration.conf" . fs_filehandler::NewLine();
                // Server name, alias, email settings
                $line .= "ServerName " . $rowvhost['vh_name_vc'] . fs_filehandler::NewLine();
                $line .= "ServerAlias " . $rowvhost['vh_name_vc'] . " www." . $rowvhost['vh_name_vc'] . fs_filehandler::NewLine();
                $line .= "ServerAdmin postmaster@txt-clan.com" . fs_filehandler::NewLine();

                // Document root
                $line .= "DocumentRoot \"" . ctrl_options::GetOption('hosted_dir') . $username['ac_user_vc'] . "/public_html" . $rowvhost['vh_directory_vc'] . "\"" . fs_filehandler::NewLine();

                // Get Package openbasedir and suhosin enabled options
                if (ctrl_options::GetOption('use_openbase') == "true") {
                    if ($rowvhost['vh_obasedir_in'] <> 0) {
                        $line .= "php_admin_value open_basedir \"" . ctrl_options::GetOption('hosted_dir') . $username['ac_user_vc'] . "/public_html" . $rowvhost['vh_directory_vc'] . ctrl_options::GetOption('openbase_seperator') . ctrl_options::GetOption('openbase_temp') . "\"" . fs_filehandler::NewLine();
                    }
                }
                if (ctrl_options::GetOption('use_suhosin') == "true") {
                    if ($rowvhost['vh_suhosin_in'] <> 0) {
                        $line .= ctrl_options::GetOption('suhosin_value') . fs_filehandler::NewLine();
                    }
                }
                // Logs
				if (!is_dir(ctrl_options::GetOption('log_dir'). "domains/" . $username['ac_user_vc'] . "/")) {
                    fs_director::CreateDirectory(ctrl_options::GetOption('log_dir'). "domains/" . $username['ac_user_vc'] . "/");
                }
                $line .= "ErrorLog \"" . ctrl_options::GetOption('log_dir') . "domains/" . $username['ac_user_vc'] . "/" . $rowvhost['vh_name_vc'] . "-error.log\" " . fs_filehandler::NewLine();
                $line .= "CustomLog \"" . ctrl_options::GetOption('log_dir') . "domains/" . $username['ac_user_vc'] . "/" . $rowvhost['vh_name_vc'] . "-access.log\" " . ctrl_options::GetOption('access_log_format') . fs_filehandler::NewLine();
                $line .= "CustomLog \"" . ctrl_options::GetOption('log_dir') . "domains/" . $username['ac_user_vc'] . "/" . $rowvhost['vh_name_vc'] . "-bandwidth.log\" " . ctrl_options::GetOption('bandwidth_log_format') . fs_filehandler::NewLine();

                // Directory options
                $line .= "<Directory />" 					. fs_filehandler::NewLine();
                $line .= "Options FollowSymLinks Indexes" 	. fs_filehandler::NewLine();
                $line .= "AllowOverride All" 				. fs_filehandler::NewLine();
                $line .= "Order Allow,Deny" 				. fs_filehandler::NewLine();
                $line .= "Allow from all" 					. fs_filehandler::NewLine();
                $line .= "</Directory>" 					. fs_filehandler::NewLine();

                // Get Package php and cgi enabled options
                $rows = $zdbh->prepare("SELECT * FROM x_packages WHERE pk_reseller_fk=" . $rowvhost['vh_acc_fk'] . " AND pk_deleted_ts IS NULL");
                $rows->execute();
                $dbvals = $rows->fetch();
                if ($dbvals['pk_enablephp_in'] <> 0) {
                    $line .= ctrl_options::GetOption('php_handler') . fs_filehandler::NewLine();
                }
                if ($dbvals['pk_enablecgi_in'] <> 0) {
                    $line .= ctrl_options::GetOption('cgi_handler') . fs_filehandler::NewLine();
					if (!is_dir(ctrl_options::GetOption('hosted_dir') . $username['ac_user_vc'] . "/public_html" . $rowvhost['vh_directory_vc'] . "/_cgi-bin")) {
                    	fs_director::CreateDirectory(ctrl_options::GetOption('hosted_dir') . $username['ac_user_vc'] . "/public_html" . $rowvhost['vh_directory_vc'] . "/_cgi-bin");
                	}
                }

                // Error documents:- Error pages are added automatically if they are found in the _errorpages directory
                // and if they are a valid error code, and saved in the proper format, i.e. <error_number>.html
                $errorpages = ctrl_options::GetOption('hosted_dir') . $username['ac_user_vc'] . $rowvhost['vh_directory_vc'] . "/_errorpages";
                if (is_dir($errorpages)) {
                    if ($handle = opendir($errorpages)) {
                        while (($file = readdir($handle)) !== false) {
                            if ($file != "." && $file != "..") {
                                $page = explode(".", $file);
                                if (!fs_director::CheckForEmptyValue(CheckErrorDocument($page[0]))) {
                                    $line .= "ErrorDocument " . $page[0] . " /_errorpages/" . $page[0] . ".html" . fs_filehandler::NewLine();
                                }
                            }
                        }
                        closedir($handle);
                    }
                }

                // Directory indexes
                $line .= ctrl_options::GetOption('dir_index') . fs_filehandler::NewLine();

                // Global custom global vh entry
                $line .= "# Custom Global Settings (if any exist)" . fs_filehandler::NewLine();
                $line .= ctrl_options::GetOption('global_vhcustom') . fs_filehandler::NewLine();

                // Client custom vh entry
                $line .= "# Custom VH settings (if any exist)" . fs_filehandler::NewLine();
                $line .= $rowvhost['vh_custom_tx'] . fs_filehandler::NewLine();

                // End Virtual Host Settings
                $line .= "</virtualhost>" . fs_filehandler::NewLine();
                $line .= "# END DOMAIN: " . $rowvhost['vh_name_vc'] . fs_filehandler::NewLine();
                $line .= "################################################################" . fs_filehandler::NewLine();
				
				
				
            } else {
                //Domain is NOT enabled
                $line .= "# DOMAIN: " . $rowvhost['vh_name_vc'] . fs_filehandler::NewLine();
                $line .= "# THIS DOMAIN HAS BEEN DISABLED" . fs_filehandler::NewLine();
                $line .= "<virtualhost *:" . ctrl_options::GetOption('apache_port') . ">" . fs_filehandler::NewLine();
                // Server name, alias, email settings
                $line .= "ServerName " . $rowvhost['vh_name_vc'] . fs_filehandler::NewLine();
                $line .= "ServerAlias " . $rowvhost['vh_name_vc'] . " www." . $rowvhost['vh_name_vc'] . fs_filehandler::NewLine();
                $line .= "ServerAdmin postmaster@txt-clan.com" . fs_filehandler::NewLine();
                // Document root
                $line .= "DocumentRoot \"" . ctrl_options::GetOption('static_dir') . "disabled\"" . fs_filehandler::NewLine();
                // Directory options
                $line .= "<Directory />" . fs_filehandler::NewLine();
                $line .= "Options FollowSymLinks Indexes" . fs_filehandler::NewLine();
                $line .= "AllowOverride All" . fs_filehandler::NewLine();
                $line .= "Order Allow,Deny" . fs_filehandler::NewLine();
                $line .= "Allow from all" . fs_filehandler::NewLine();
                $line .= "</Directory>" . fs_filehandler::NewLine();
                $line .= ctrl_options::GetOption('dir_index') . fs_filehandler::NewLine();
                $line .= "</virtualhost>" . fs_filehandler::NewLine();
                $line .= "# END DOMAIN: " . $rowvhost['vh_name_vc'] . fs_filehandler::NewLine();
                $line .= "################################################################" . fs_filehandler::NewLine();
				
            }
        }
				
        // write the vhost config file
        $vhconfigfile = ctrl_options::GetOption('apache_vhost');
        if (fs_filehandler::UpdateFile($vhconfigfile, 0777, $line)) {
			// Reset Apache settings to reflect that config file has been written, until the next change.
        	$vsql = $zdbh->prepare("UPDATE x_settings
									SET so_value_tx='".time()."'
									WHERE so_name_vc='apache_changed'");
            $vsql->execute();
			if (sys_versions::ShowOSPlatformVersion() == "Windows") {
                system("".ctrl_options::GetOption('httpd_exe')." ".ctrl_options::GetOption('apache_restart')."");
            } else {
				system("".ctrl_options::GetOption('zsudo')." service ".ctrl_options::GetOption('apache_sn')." ".ctrl_options::GetOption('apache_restart')."");
            }
			
            return true;
        } else {
            return false;
        }
    }

    function CheckErrorDocument($error) {
        $errordocs = array(100, 101, 102, 200, 201, 202, 203, 204, 205, 206, 207,
				           300, 301, 302, 303, 304, 305, 306, 307, 400, 401, 402,
				           403, 404, 405, 406, 407, 408, 409, 410, 411, 412, 413,
				           414, 415, 416, 417, 418, 419, 420, 421, 422, 423, 424,
				           425, 426, 500, 501, 502, 503, 504, 505, 506, 507, 508,
				           509, 510);
        if (in_array($error, $errordocs)) {
            return true;
        } else {
            return false;
        }
    }
	
    function BackupVhostConfigFile() {
		echo "Apache VHost backups are enabled... Backing up current vhost.conf to: " .ctrl_options::GetOption('apache_budir') . fs_filehandler::NewLine();
	    if (!is_dir(ctrl_options::GetOption('apache_budir'))) {
        	fs_director::CreateDirectory(ctrl_options::GetOption('apache_budir'));
        }
		copy(ctrl_options::GetOption('apache_vhost'), ctrl_options::GetOption('apache_budir'). "VHOST_BACKUP_".time()."");
		fs_director::SetFileSystemPermissions(ctrl_options::GetOption('apache_budir'). ctrl_options::GetOption('apache_vhost').".BU", 0777);
		if(ctrl_options::GetOption('apache_purgebu') == strtolower("true")){
			echo "Apache VHost purges are enabled... Purging backups older than: " .ctrl_options::GetOption('apache_purge_date') . " days..." . fs_filehandler::NewLine();
			echo "[FILE][PURGE_DATE][FILE_DATE][ACTION]" . fs_filehandler::NewLine();
			$purge_date = ctrl_options::GetOption('apache_purge_date');
			if ($handle = @opendir(ctrl_options::GetOption('apache_budir'))) {
	   			while (false !== ($file = readdir($handle))){
	          		if ($file != "." && $file != ".."){
						$filetime = @filemtime(ctrl_options::GetOption('apache_budir') . $file);
						if($filetime == NULL){
    						$filetime = @filemtime(utf8_decode(ctrl_options::GetOption('apache_budir') . $file));
						} 
						$filetime = floor((time() - $filetime)/86400);
						echo "" . $file . " - " . $purge_date ." - " . $filetime . "";
						if ($purge_date < $filetime){
							//delete the file
							echo " - Deleting file...\r\n";
							unlink(ctrl_options::GetOption('apache_budir') . $file);
						} else {
							echo " - Skipping file...\r\n";
						}
    	      		}
	       		}
			}
			echo "Purging old backups complete..." . fs_filehandler::NewLine();
		}
		echo "Apache backups complete..." . fs_filehandler::NewLine();
    }
?>