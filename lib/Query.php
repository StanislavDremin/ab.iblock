<?php
/**
 * Created by PhpStorm.
 * User: dremin_s
 * Date: 26.07.2016
 * Time: 13:26
 */

namespace AB\Iblock;

use Bitrix\Main\Entity;
use Bitrix\Main;
use PW\Tools\Debug;

class Query extends Entity\Query
{
	/** @var  Manager */
	protected $Manager;
	protected $sql = '';

	/**
	 * Builds and returns SQL query string
	 *
	 * @return string
	 */
	public function buildQuery()
	{
		$arParams = [
			'select' => $this->getSelect(),
			'filter' => $this->getFilter(),
			'order' => $this->getOrder(),
			'group' => $this->getGroup()
		];

		$arProps = Manager::prepareParams($arParams);

		$Manager = $this->getManager();

		$this->entity = $Manager
			->setElementEntity($this->entity)
			->setIblockId($arParams['filter']['IBLOCK_ID'])
			->setQueryParams($arParams)
			->createPropertyEntity($arProps)
			->attachPropertyEntity()
			->getElementEntity();

		if(count($Manager->getModifierQuery()) > 0){
			foreach ($Manager->getModifierQuery() as $type => $value) {
				switch ($type){
					case 'select':
						$this->setSelect($value);
						break;
					case 'filter':
						$this->setFilter($value);
						break;
					case 'group':
						$this->setGroup($value);
						break;
					case 'order';
						$this->setOrder($value);
						break;
				}
			}
		}

		return parent::buildQuery();
	}

	/**
	 * @method getManager - get param Manager
	 * @return Manager
	 */
	public function getManager()
	{
		return $this->Manager;
	}

	/**
	 * @method setManager - set param Manager
	 * @param Manager $Manager
	 */
	public function setManager(Manager $Manager)
	{
		$this->Manager = $Manager;
	}

}
