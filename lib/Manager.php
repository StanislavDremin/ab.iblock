<?php
/**
 * Created by PhpStorm.
 * User: dremin_s
 * Date: 26.07.2016
 * Time: 12:16
 */

namespace AB\Iblock;

use Bitrix\Main\Entity;
use AB\Iblock\Exceptions\ArgumentException;

class Manager
{
	/** @var  int */
	private $iblockId;

	/** @var  Entity\Base */
	private $ElementEntity;

	/** @var  Entity\Base */
	private $PropertyEntity;
	private static $operandsInProp = ['<','>','=','!','%','@'];

	/** @var  array */
	private $queryParams;
	private $modifierQuery = [];
	private $version = 2;

	/**
	 * Manager constructor.
	 *
	 * @param $iblockId
	 *
	 * @throws ArgumentException
	 */
	public function __construct($iblockId = 0)
	{
		$this->iblockId = $iblockId;
	}

	/**
	 * @method createElementEntity
	 * @param string $name
	 *
	 * @return $this
	 */
	public function createElementEntity($name = '')
	{
		if(strlen($name) == 0 || is_null($name)){
			$name = 'OrmElement'.$this->iblockId;
		}

		$fullName = '\\'.__NAMESPACE__.'\\'.$name;
		if(Entity\Base::isExists($fullName)){
			$entity = Entity\Base::getInstance($fullName);
		} else {
			$entity = Entity\Base::compileEntity(
				$name,
				Model\ElementTable::getMap(),
				['namespace'=>__NAMESPACE__, 'table_name'=>Model\ElementTable::getTableName()]
			);
		}

		$this->ElementEntity = $entity;

		return $this;
	}

	/**
	 * @method createPropertyEntity
	 * @param array $arProps
	 *
	 * @return $this
	 */
	public function createPropertyEntity($arProps = ['*'])
	{
		$Property = new Property($this->iblockId, $arProps);
		$Property->setQueryParams($this->getQueryParams());

		$this->PropertyEntity = $Property->compile();

		$this->setModifierQuery($Property->getModifierQuery());

		return $this;
	}

	/**
	 * @method compileEntity
	 * @param $name
	 * @param array $arProps
	 *
	 * @return $this
	 */
	public function compileEntity($name, $arProps = [])
	{
		$this->createElementEntity($name);
		if(count($arProps) > 0){
			$this->createPropertyEntity($arProps);
			$this->attachPropertyEntity();
		}

		return $this;
	}

	/**
	 * @method attachPropertyEntity
	 * @return $this
	 */
	public function attachPropertyEntity()
	{
		$this->ElementEntity->addField(new Entity\ReferenceField(
			'PROPERTY',
			$this->PropertyEntity,
			['ref.IBLOCK_ELEMENT_ID' => 'this.ID'],
			['join_type' => 'LEFT']
		));

		return $this;
	}

	/**
	 * @method prepareParams
	 * @param array $params
	 *
	 * @return array
	 */
	public static function prepareParams(array $params = [])
	{
		foreach ($params['filter'] as $code => $v) {
			unset($params['filter'][$code]);

			$code = self::delOperands($code);
			$params['filter'][$code] = $v;
		}

		$params['filter'] = self::prepareFilterProp($params['filter']);

		$arPropsKey = self::prepareOnKeys(array_keys(array_merge($params['filter'], $params['order'])));
		$arPropsVal = self::prepareOnValues(array_values(array_merge($params['select'], $params['group'])));
		$arProps = array_unique(array_merge($arPropsKey, $arPropsVal));

 		return $arProps;
	}

	/**
	 * @method prepareOnKeys
	 * @param $params
	 *
	 * @return array
	 */
	protected static function prepareOnValues($params)
	{
		$arProps = [];

		foreach ($params as $value){
			if (is_string($value) && preg_match('#^PROPERTY.(.*)#i', $value, $parseProp)) {
				$arProps[] = $parseProp[1];
			}
		}

		$arProps = array_unique($arProps);

		return $arProps;
	}

	/**
	 * @method prepareOnValues
	 * @param $params
	 *
	 * @return array
	 */
	protected static function prepareOnValues($params)
	{
		$params = array_unique($params);
		$arProps = [];

		foreach ($params as $value){
			if(preg_match('#^PROPERTY.(.*)#i', $value, $parseProp)){
				$arProps[] = $parseProp[1];
			}
		}

		return $arProps;
	}

	/**
	 * @method mbUcFirst
	 * @param $string
	 * @param string $e
	 *
	 * @return string
	 */
	public static function mbUcFirst($string, $e = 'utf-8') {
		if (function_exists('mb_strtoupper') && function_exists('mb_substr') && !empty($string)) {
			$string = mb_strtolower($string, $e);
			$upper = mb_strtoupper($string, $e);
			preg_match('#(.)#us', $upper, $matches);
			$string = $matches[1] . mb_substr($string, 1, mb_strlen($string, $e), $e);
		} else {
			$string = ucfirst($string);
		}
		return $string;
	}

	/**
	 * @method delOperands
	 * @param $fieldName
	 *
	 * @return mixed
	 */
	public static function delOperands($fieldName)
	{
		return str_replace(self::$operandsInProp, '', $fieldName);
	}

	/**
	 * @method prepareFilterProp
	 * @param $filter
	 * @param null $value
	 *
	 * @return mixed
	 */
	public static function prepareFilterProp(&$filter, $value = NULL) {
		$value = $value?:$filter;
		foreach ($value as $k => $v) {
			if (is_numeric($k) && $k == intval($k) && is_array($v) && count($v) > 0) {
				unset($filter[$k]);
				self::prepareFilterProp($filter, $v);
			} elseif($k != 'LOGIC') {
				unset($filter[$k]);
				$k = self::delOperands($k);
				$filter[$k] = $v;
			} else {
				unset($filter[$k]);
			}
		}
		return $filter;
	}

	/* ======================================= getters|setters ====================================================== */

	/**
	 * @method getIblockId - get param iblockId
	 * @return int
	 */
	public function getIblockId()
	{
		return $this->iblockId;
	}

	/**
	 * @method setIblockId - set param IblockId
	 * @param int $iblockId
	 *
	 * @return Manager
	 */
	public function setIblockId($iblockId)
	{
		$this->iblockId = $iblockId;

		return $this;
	}

	/**
	 * @method getElementEntity - get param ElementEntity
	 * @return Entity\Base
	 */
	public function getElementEntity()
	{
		return $this->ElementEntity;
	}

	/**
	 * @method setElementEntity - set param ElementEntity
	 * @param Entity\Base $ElementEntity
	 *
	 * @return $this
	 */
	public function setElementEntity($ElementEntity)
	{
		$this->ElementEntity = $ElementEntity;

		return $this;
	}

	/**
	 * @method getPropertyEntity - get param PropertyEntity
	 * @return Entity\Base
	 */
	public function getPropertyEntity()
	{
		return $this->PropertyEntity;
	}

	/**
	 * @method setPropertyEntity - set param PropertyEntity
	 * @param Entity\Base $PropertyEntity
	 */
	public function setPropertyEntity($PropertyEntity)
	{
		$this->PropertyEntity = $PropertyEntity;
	}

	/**
	 * @method getQueryParams - get param queryParams
	 * @return mixed
	 */
	public function getQueryParams()
	{
		return $this->queryParams;
	}

	/**
	 * @param mixed $queryParams
	 *
	 * @return Manager
	 */
	public function setQueryParams($queryParams)
	{
		$this->queryParams = $queryParams;

		return $this;
	}

	/**
	 * @method getModifierQuery - get param modifierQuery
	 * @param string $k
	 *
	 * @return array
	 */
	public function getModifierQuery($k = '')
	{
		if(strlen($k) > 0)
			return $this->modifierQuery[$k];

		return $this->modifierQuery;
	}

	/**
	 * @param array $modifierQuery
	 *
	 * @return Manager
	 */
	public function setModifierQuery($modifierQuery)
	{
		$this->modifierQuery = null;

		if(count($modifierQuery) > 0)
			$this->modifierQuery = $modifierQuery;

		return $this;
	}

	/**
	 * @method getVersion - get param version
	 * @return int
	 */
	public function getVersion()
	{
		return $this->version;
	}

	/**
	 * @param int $version
	 *
	 * @return Manager
	 */
	public function setVersion($version)
	{
		$this->version = $version;

		return $this;
	}
}