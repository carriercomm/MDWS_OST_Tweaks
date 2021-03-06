<?php

/*
 * To change this license header, choose License Headers in Project Properties. To change this template file, choose Tools | Templates and open the template in the editor.
 */

/**
 * Description of equipment
 *
 * @author Alex Pavlunenko <alexp at xpresstek.net>
 */
require_once (INCLUDE_DIR . 'class.plugin.php');
require_once (INCLUDE_DIR . 'class.signal.php');
require_once (INCLUDE_DIR . 'class.app.php');
require_once (INCLUDE_DIR . 'class.dispatcher.php');
require_once (INCLUDE_DIR . 'class.dynamic_forms.php');
require_once (INCLUDE_DIR . 'class.osticket.php');

require_once ('config.php');

define ( 'EQUIPMENT_PLUGIN_VERSION', '0.4' );

class MdwsPlugin extends Plugin {
	var $config_class = 'MdwsConfig';
	public static function autoload($className) {
		$className = ltrim ( $className, '\\' );
		$fileName = '';
		$namespace = '';
		if ($lastNsPos = strrpos ( $className, '\\' )) {
			$namespace = substr ( $className, 0, $lastNsPos );
			$className = substr ( $className, $lastNsPos + 1 );
			$fileName = str_replace ( '\\', DIRECTORY_SEPARATOR, $namespace ) . DIRECTORY_SEPARATOR;
		}
		$fileName .= str_replace ( '_', DIRECTORY_SEPARATOR, $className ) . '.php';
		$fileName = 'include/' . $fileName;
		
		if (file_exists ( EQUIPMENT_PLUGIN_ROOT . $fileName )) {
			require $fileName;
		}
	}
	function bootstrap() {
		if ($this->firstRun ()) {
			if (! $this->configureFirstRun ()) {
				return false;
			}
		}		
		else if ($this->needUpgrade ()) {
			$this->configureUpgrade ();
		}
		
		$config = $this->getConfig ();
		
		if ($config->get ( 'equipment_backend_enable' )) {
			$this->createStaffMenu ();
		}
		if ($config->get ( 'equipment_frontend_enable' )) {
			$this->createFrontMenu ();
		}
		
		Signal::connect ( 'apps.scp', array (
				'EquipmentPlugin',
				'callbackDispatch' 
		) );
	}
	public static function getCustomForm() {
		$sql = 'SELECT id FROM ' . PLUGIN_TABLE . ' WHERE name=\'Equipment Manager\'';
		$res = db_query ( $sql );
		if (isset ( $res )) {
			$ht = db_fetch_array ( $res );
			$config = new EquipmentConfig ( $ht ['id'] );
			return $config->get ( 'equipment_custom_form' );
		}
		return false;
	}
	static public function callbackDispatch($object, $data) {
		$search_url = url ( '^/equipment.*search', patterns ( 'controller\EquipmentItem', url_post ( '^.*', 'searchAction' ) ) );
		
		$categories_url = url ( '^/equipment.*categories/', patterns ( 'controller\EquipmentCategory', url_get ( '^list$', 'listAction' ), url_get ( '^listJson$', 'listJsonAction' ), url_get ( '^listJsonTree$', 'listJsonTreeAction' ), url_get ( '^view/(?P<id>\d+)$', 'viewAction' ), url_get ( '^openTicketsJson/(?P<item_id>\d+)$', 'openTicketsJsonAction' ), url_get ( '^closedTicketsJson/(?P<item_id>\d+)$', 'closedTicketsJsonAction' ), url_get ( '^getItemsJson/(?P<category_id>\d+)$', 'categoryItemsJsonAction' ), url_post ( '^save', 'saveAction' ), url_post ( '^delete', 'deleteAction' ) ) );
		
		$item_url = url ( '^/equipment.*item/', patterns ( 'controller\EquipmentItem', url_get ( '^list$', 'listAction' ), url_get ( '^listJson$', 'listJsonAction' ), url_get ( '^listBelongingJson$', 'listBelongingJsonAction' ), url_get ( '^listNotBelongingJson$', 'listNotBelongingJsonAction' ), url_get ( '^listStaffJson$', 'listStaffJsonAction' ), url_get ( '^view/(?P<id>\d+)$', 'viewAction' ), url_get ( '^new/(?P<category_id>\d+)$', 'newAction' ), url_post ( '^publish', 'publishAction' ), url_post ( '^activate', 'activateAction' ), url_post ( '^save', 'saveAction' ), url_get ( '^openTicketsJson/(?P<item_id>\d+)$', 'openTicketsJsonAction' ), url_get ( '^closedTicketsJson/(?P<item_id>\d+)$', 'closedTicketsJsonAction' ), url_get ( '^getDynamicForm/(?P<id>\d+)$', 'getDynamicForm' ), url_post ( '^search', 'searchAction' ), url_post ( '^delete', 'deleteAction' ), url_post ( '^openNewTicket', 'openNewTicketAction' ) ) );

		
		
		$status_url = url ( '^/equipment.*status/', patterns ( 'controller\EquipmentStatus', url_get ( '^list$', 'listAction' ), url_get ( '^view/(?P<id>\d+)$', 'viewAction' ), url_get ( '^new/(?P<category_id>\d+)$', 'newAction' ), url_get ( '^listJson$', 'listJsonAction' ), url_get ( '^getItemsJson/(?P<status_id>\d+)$', 'statusItemsJsonAction' ), url_post ( '^save', 'saveAction' ), url_post ( '^delete', 'deleteAction' ) ) );
		
		$recurring_url = url ( '^/equipment.*recurring/', patterns ( 'controller\TicketRecurring', url_get ( '^list$', 'listAction' ), url_get ( '^view/(?P<id>\d+)$', 'viewAction' ), url_get ( '^viewByTicket/(?P<id>\d+)$', 'viewByTicketAction' ), url_get ( '^addByTicket/(?P<id>\d+)$', 'addByTicketAction' ), url_get ( '^new/(?P<category_id>\d+)$', 'newAction' ), url_get ( '^listJson$', 'listJsonAction' ), url_get ( '^getItemsJson/(?P<status_id>\d+)$', 'statusItemsJsonAction' ), url_get ( '^listTicketsJson$', 'listTicketsJson' ), url_get ( '^listEquipmentJson$', 'listEquipmentJson' ), url_post ( '^save', 'saveAction' ), url_post ( '^delete', 'deleteAction' ), url_post ( '^enableEvents', 'enableEventsAction' ) ) );
		
		$maintenance_url = url ( '^/equipment.*maintenance/', patterns ( 'controller\Maintenance', url_get ( '^startStructureTest$', 'startDatabaseIntegrityTest' ), url_get ( '^purgeData$', 'startDatabaseDataPurge' ), url_get ( '^recreateDatabase', 'startDatabaseRecreate' ), url_get ( '.*', 'defaultAction' ) ) );
		
		$media_url = url ( '^/equipment.*assets/', patterns ( 'controller\MediaController', url_get ( '^(?P<url>.*)$', 'defaultAction' ) ) );
		
		$dashboard_url = url ( '^/equipment.*dashboard/', patterns ( 'controller\Dashboard', url_get ( '^treeJson', 'treeJsonAction' ), url_get ( '.*', 'viewAction' ) ) );
		
		$redirect_url = url ( '^/equipment.*ostroot/', patterns ( 'controller\MediaController', url_get ( '^(?P<url>.*)$', 'redirectAction' ) ) );
		
		$object->append ( $search_url );
		$object->append ( $media_url );
		$object->append ( $redirect_url );
		$object->append ( $maintenance_url );
		$object->append ( $dashboard_url );
		$object->append ( $categories_url );
		$object->append ( $item_url );
		$object->append ( $status_url );
		$object->append ( $recurring_url );
	}
	
	/**
	 * Creates menu links in the staff backend.
	 */
	function createStaffMenu() {
		Application::registerStaffApp ( 'Equipment', 'dispatcher.php/equipment/dashboard/', array (
				iconclass => 'faq-categories' 
		) );
	}
	
	/**
	 * Creates menu link in the client frontend.
	 * Useless as of OSTicket version 1.9.2.
	 */
	function createFrontMenu() {
		Application::registerClientApp ( 'Equipment Status', 'equipment_front/index.php', array (
				iconclass => 'equipment' 
		) );
	}
	
	/**
	 * Checks if this is the first run of our plugin.
	 *
	 * @return boolean
	 */
	function firstRun() {
		$sql = 'SHOW TABLES LIKE \'' . EQUIPMENT_TABLE . '\'';
		$res = db_query ( $sql );
		return (db_num_rows ( $res ) == 0);
	}
	function needUpgrade() {
		$sql = 'SELECT version FROM ' . PLUGIN_TABLE . ' WHERE name=\'Equipment Manager\'';
		
		if (! ($res = db_query ( $sql ))) {
			return true;
		} else {
			$ht = db_fetch_array ( $res );
			if (floatval ( $ht ['version'] ) < floatval ( EQUIPMENT_PLUGIN_VERSION )) {
				return true;
			}
		}
		return false;
	}
	function configureUpgrade() {
		$installer = new \util\EquipmentInstaller ();
		
		if (! $installer->upgrade ()) {
			echo "Upgrade configuration error. " . "Unable to upgrade database tables!";
		}
	}
	
	/**
	 * Necessary functionality to configure first run of the application
	 */
	function configureFirstRun() {
		if (! $this->createDBTables ()) {
			echo "First run configuration error.  " . "Unable to create database tables!";
			return false;
		}
		return true;
	}
	
	/**
	 * Kicks off database installation scripts
	 *
	 * @return boolean
	 */
	function createDBTables() {
		$installer = new \util\EquipmentInstaller ();
		return $installer->install ();
	}
	
	/**
	 * Uninstall hook.
	 *
	 * @param type $errors        	
	 * @return boolean
	 */
	function pre_uninstall(&$errors) {
		$installer = new \util\EquipmentInstaller ();
		return $installer->remove ();
	}
}
