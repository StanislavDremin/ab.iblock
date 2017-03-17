<?php IncludeModuleLangFile(__FILE__);
if (class_exists("ab_iblock"))
	return;

use Bitrix\Main\Localization\Loc as Loc;
use Bitrix\Main\ModuleManager;

class ab_iblock extends \CModule
{
	public $MODULE_ID = "ab.iblock";
	public $MODULE_VERSION;
	public $MODULE_VERSION_DATE;
	public $MODULE_NAME;
	public $MODULE_DESCRIPTION;
	public $MODULE_CSS;
	public $PARTNER_NAME;
	public $PARTNER_URI;

	private $APP;
	private $DB;

	function __construct()
	{
		global $DB, $APPLICATION;
		$arModuleVersion = array();

		include(dirname(__FILE__)."/version.php");

		$this->MODULE_VERSION = $arModuleVersion["VERSION"];
		$this->MODULE_VERSION_DATE = $arModuleVersion["VERSION_DATE"];

		$this->MODULE_NAME = Loc::getMessage("AB_IBLOCK_INSTALL_NAME");
		$this->MODULE_DESCRIPTION = Loc::getMessage("AB_IBLOCK_INSTALL_DESCRIPTION");
		$this->PARTNER_NAME = Loc::getMessage("AB_PARTNER_NAME");
//		$this->PARTNER_URI = GetMessage("ST_PARTNER_URI");

		$this->DB = $DB;
		$this->APP = $APPLICATION;
	}

	public function DoInstall()
	{
		ModuleManager::registerModule($this->MODULE_ID);
		\Bitrix\Main\Loader::includeModule($this->MODULE_ID);
		$Event = \Bitrix\Main\EventManager::getInstance();

		$Event->registerEventHandlerCompatible('iblock','OnAfterIBlockPropertyAdd', 'ab.iblock', '\AB\Iblock\Handlers','resetCache');
		$Event->registerEventHandlerCompatible('iblock','OnAfterIBlockPropertyUpdate', 'ab.iblock', '\AB\Iblock\Handlers','resetCache');
		$Event->registerEventHandlerCompatible('iblock','OnBeforeIBlockPropertyDelete', 'ab.iblock', '\AB\Iblock\Handlers','resetCacheDelete');

		return true;
	}

	public function DoUninstall()
	{
		\Bitrix\Main\Loader::includeModule($this->MODULE_ID);
		$Event = \Bitrix\Main\EventManager::getInstance();
		$Event->unRegisterEventHandler('iblock','OnAfterIBlockPropertyAdd', 'ab.iblock', '\AB\Iblock\Handlers','resetCache');
		$Event->unRegisterEventHandler('iblock','OnAfterIBlockPropertyUpdate', 'ab.iblock', '\AB\Iblock\Handlers','resetCache');
		$Event->unRegisterEventHandler('iblock','OnBeforeIBlockPropertyDelete', 'ab.iblock', '\AB\Iblock\Handlers','resetCacheDelete');

		ModuleManager::unRegisterModule($this->MODULE_ID);
		return true;
	}
}