<?php
/**
 * Created by PhpStorm.
 * User: dremin_s
 * Date: 26.07.2016
 * Time: 14:29
 */

namespace AB\Iblock;

use AB\Iblock\Exceptions\ArgumentException;
use Bitrix\Main\Entity;
use Bitrix\Main\Data;
use Bitrix\Iblock\PropertyEnumerationTable;
use Bitrix\Main\Type\Dictionary;

/**
 * Class Property
 * @package AB\Iblock
 */
class Property
{
	/** @var  int */
	protected $iblockId;

	/** @var array */
	protected $propCode;

	private static $usePropCache = true;
	private static $cachePropId = 'ab_iblock_meta_props_';
	private static $cachePropTime = 86400 * 30;
	private static $cachePropDir = '/ab/IBlock';
	private static $enumCacheTag = 'meta_enum_prop';
	private $propTypes = [];
	/** @var  Entity\Base */
	protected $entityProp;
	private $enumValues;

	/** @var null|array */
	private $metaData = null;
	private $nextLevelProp = [];
	private $nextLevelField = [];
	private $modifierQuery = [];
	/** @var  Dictionary */
	private $queryParams;
	private $isAllProperties = false;
	private $version = 2;

	/**
	 * Property constructor.
	 *
	 * @param $iblockId
	 * @param $propCode
	 *
	 * @throws ArgumentException
	 */
	public function __construct($iblockId, $propCode = ['*'])
	{
		if (intval($iblockId) == 0)
			throw new ArgumentException('Iblock id is null', ['IBLOCK_ID' => intval($iblockId)]);

		$this->iblockId = $iblockId;

		$propTmp = $propCode;
		$first = array_shift($propTmp);
		if (intval($iblockId) && count($propCode) > 0){
			if ($first == '*'){
				$this->propCode = $this->getAllProps();
				$this->isAllProperties = true;
			} else {
				$this->propCode = $propCode;
				$this->isAllProperties = false;
			}
		}
	}

	/**
	 * @method createSingleEntity
	 * @return Entity\Base
	 */
	public function createSingleEntity()
	{
		$entityName = 'OrmProperty'.$this->iblockId;
		$fullName = '\\'.__NAMESPACE__.'\\'.$entityName;

		if (Entity\Base::isExists($fullName)){
			$entity = Entity\Base::getInstance($fullName);
		} else {

			$tableName = 'b_iblock_element_prop_s'.$this->iblockId;
			if ($this->version != 2){
				$tableName = Model\PropertyElementTable::getTableName();
			}

			$entity = Entity\Base::compileEntity(
				$entityName,
				['IBLOCK_ELEMENT_ID' => new Entity\IntegerField('IBLOCK_ELEMENT_ID')],
				['table_name' => $tableName, 'namespace' => __NAMESPACE__]
			);
		}

		return $entity;
	}

	/**
	 * @method compile
	 * @return Entity\Base
	 */
	public function compile()
	{

		$arMetaData = $this->metaData();
		$metaTmp = [];

		$metaTmp = $arMetaData;
		$first = array_shift($metaTmp);
		$this->version = $first['VERSION'];
		unset($metaTmp);
		unset($first);

		foreach ($this->propCode as $value) {
			$parseCode = explode('.', $value);

			if (array_key_exists($parseCode[0], $arMetaData)){
				$metaTmp[$parseCode[0]] = $arMetaData[$parseCode[0]];
			}

			if ($parseCode[1] == 'PROPERTY'){
				$output = implode('.', array_slice($parseCode, 2));
				$this->nextLevelProp[] = $output;
			} elseif (isset($parseCode[1]) && $parseCode[1] != 'PROPERTY') {
				$this->nextLevelField[$parseCode[0]][] = $parseCode[1];
			}
		}

		$this->entityProp = $this->createSingleEntity();

		$this->getEnumValues($this->propTypes['LIST']);

		foreach ($this->propTypes as $type => $arItems) {
			foreach ($arItems as $arProperty) {
				if ($arProperty['MULTIPLE'] == 'N'){
					if (array_key_exists($arProperty['CODE'], $metaTmp)){
						switch ($type) {
							case 'ELEMENT':
								$this->refIblockElementProperty($arProperty);
								break;
							case 'LIST':
								$this->listProperty($arProperty);
								break;
							default:
								$this->scalarProperty($arProperty);
								break;
						}
					}
				} else {
					if (array_key_exists($arProperty['CODE'], $metaTmp)){
						if ($this->version == 2){
							$this->compileMultiEntity($arProperty);
						} else {
							$this->multiV1Property($arProperty);
						}
					}
				}
			}

		}

		return $this->entityProp;
	}

	/**
	 * @method scalarProperty
	 * @param array $arProperty
	 */
	protected function scalarProperty(array $arProperty)
	{
		if ($arProperty['VERSION'] == 2){
			if ($arProperty['PROPERTY_TYPE'] == 'N'){
				$field = new Entity\IntegerField($arProperty['CODE'], array(
					'title' => $arProperty['NAME'],
					'column_name' => 'PROPERTY_'.$arProperty['ID'],
					'required' => $arProperty['IS_REQUIRED'] == 'Y' ? true : false,
				));
				$field->addFetchDataModifier(function ($value, $field, $data, $alias) use ($arProperty) {
					$result = 0;
					$val = explode('.', $value);

					if (count($val) > 1){
						if (intval($val[1]) > 0)
							$result = floatval($value);
						else
							$result = intval($value);
					} else {
						$result = intval($value);
					}

					return $result;
				});
			} else {
				$field = new Entity\StringField($arProperty['CODE'], [
					'title' => $arProperty['NAME'],
					'column_name' => 'PROPERTY_'.$arProperty['ID'],
					'required' => $arProperty['IS_REQUIRED'] == 'Y' ? true : false,
				]);
			}
		} else {
			$refName = $arProperty['CODE'].'_REF';
			$this->entityProp->addField(new Entity\ReferenceField(
				$refName,
				Model\PropertyElementTable::getEntity(),
				['=this.IBLOCK_ELEMENT_ID' => 'ref.IBLOCK_ELEMENT_ID', 'ref.IBLOCK_PROPERTY_ID' => array('?i', $arProperty['ID'])]
			));

			$field = new Entity\ExpressionField($arProperty['CODE'], '%s', $refName.'.VALUE');
		}

		switch (strtoupper($arProperty['USER_TYPE'])) {
			case 'HTML':
				$field->addFetchDataModifier(function ($value, $field, $data, $alias) use ($arProperty) {
					$result = null;
					if (strlen($value) > 0){
						$res = unserialize($value);
						$result = $res['TEXT'];
					}

					return $result;
				});
				break;
		}

		$this->entityProp->addField($field);
	}

	/**
	 * @method refIblockElementProperty
	 * @param array $arProperty
	 */
	protected function refIblockElementProperty(array $arProperty)
	{
		if ($arProperty['VERSION'] == 2){
			$singleName = $arProperty['CODE'].'_SINGLE';
			$this->entityProp->addField(new Entity\IntegerField($singleName, [
				'title' => $arProperty['NAME'],
				'column_name' => 'PROPERTY_'.$arProperty['ID'],
				'required' => $arProperty['IS_REQUIRED'] == 'Y' ? true : false,
			]));

			$chain = $this->nextLevelField[$arProperty['CODE']];

			if (intval($arProperty['LINK_IBLOCK_ID']) > 0){
				$arName = explode('_', $arProperty['CODE']);
				foreach ($arName as &$v) {
					$v = Manager::mbUcFirst($v);
				}
				$nameLink = 'Orm';
				$nameLink .= implode('', $arName);
				$nameLink .= $arProperty['LINK_IBLOCK_ID'];

				$entityLink = Element::getEntity($arProperty['LINK_IBLOCK_ID'], $this->nextLevelProp, $nameLink);

				$field = new Entity\ReferenceField(
					$arProperty['CODE'],
					$entityLink,
					['=this.'.$singleName => 'ref.ID']
				);

			} else {
				$field = new Entity\ExpressionField($arProperty['CODE'], '%s', $singleName);
			}
		} else {

			$refName = $arProperty['CODE'].'_REF';
			$this->entityProp->addField(new Entity\ReferenceField(
				$refName,
				Model\PropertyElementTable::getEntity(),
				['=this.IBLOCK_ELEMENT_ID' => 'ref.IBLOCK_ELEMENT_ID', 'ref.IBLOCK_PROPERTY_ID' => array('?i', $arProperty['ID'])]
			));

			if (intval($arProperty['LINK_IBLOCK_ID']) > 0){
				$arName = explode('_', $arProperty['CODE']);
				foreach ($arName as &$v) {
					$v = Manager::mbUcFirst($v);
				}
				$nameLink = 'Orm';
				$nameLink .= implode('', $arName);
				$nameLink .= $arProperty['LINK_IBLOCK_ID'];

				$entityLink = Element::getEntity($arProperty['LINK_IBLOCK_ID'], $this->nextLevelProp, $nameLink);

				$field = new Entity\ReferenceField(
					$arProperty['CODE'],
					$entityLink,
					['=this.'.$refName.'.VALUE' => 'ref.ID']
				);
			} else {
				$field = new Entity\ExpressionField($arProperty['CODE'], '%s', $refName.'.VALUE');
			}
		}

		$this->entityProp->addField($field);
	}

	/**
	 * @method listProperty
	 * @param array $arProperty
	 */
	protected function listProperty(array $arProperty)
	{
		$refChainVal = $this->nextLevelField[$arProperty['CODE']];

		if ($arProperty['VERSION'] == 2){
			$singleField = $arProperty['CODE'].'_SINGLE';
			$this->entityProp->addField(new Entity\IntegerField($singleField, [
				'title' => $arProperty['NAME'],
				'column_name' => 'PROPERTY_'.$arProperty['ID'],
				'required' => $arProperty['IS_REQUIRED'] == 'Y' ? true : false,
			]));

//	    	$refChainVal = false;
			if (empty($refChainVal)){
				$field = new Entity\ExpressionField($arProperty['CODE'], '%s', $singleField);
			} else {
				$enumFieldName = $arProperty['CODE'].'_ENUM';

				$field = new Entity\ReferenceField(
					$arProperty['CODE'],
					PropertyEnumerationTable::getEntity(),
					['=this.'.$singleField => 'ref.ID']
				);
				$this->entityProp->addField($field);
				$field = new Entity\ExpressionField($enumFieldName, '%s', $singleField);

				$this->resetQueryParams(['select', 'filter'], $arProperty['CODE'], 'PROPERTY.'.$enumFieldName);
			}
		} else {
			// TODO вынести в метод
			$refName = $arProperty['CODE'].'_REF';
			$this->entityProp->addField(new Entity\ReferenceField(
				$refName,
				Model\PropertyElementTable::getEntity(),
				['=this.IBLOCK_ELEMENT_ID' => 'ref.IBLOCK_ELEMENT_ID', 'ref.IBLOCK_PROPERTY_ID' => array('?i', $arProperty['ID'])]
			));

			if (empty($refChainVal)){
				$field = new Entity\ExpressionField($arProperty['CODE'], '%s', $refName.'.VALUE_ENUM');
			} else {
				$field = new Entity\ReferenceField(
					$arProperty['CODE'],
					PropertyEnumerationTable::getEntity(),
					['=this.'.$refName.'.VALUE_ENUM' => 'ref.ID']
				);
			}
		}
		$field->addFetchDataModifier(
			function ($value, $field, $data, $alias) use ($arProperty) {
				$valNow = $data[$alias];

				return $this->enumValues[$arProperty['ID']][$valNow];
			}
		);

		$this->entityProp->addField($field);

	}

	/**
	 * @method getEnumValues
	 * @param array $arProps
	 */
	protected function getEnumValues($arProps = [])
	{
		$DataCache = Data\Cache::createInstance();
		$TagCache = new Data\TaggedCache();
		$cacheDir = '/ab/IBlock/enum';
		$connect = \Bitrix\Main\Application::getConnection();

		foreach ($arProps as $value) {
			$propId = $value['ID'];
			$cacheId = self::$enumCacheTag;

//			$TagCache->clearByTag($cacheId);

			if ($DataCache->initCache(self::$cachePropTime, $cacheId, $cacheDir)){
				$resultEnum = $DataCache->getVars();
			} else {
				$DataCache->startDataCache();
				$TagCache->startTagCache($cacheDir);

				$enumSql = "SELECT BPE.ID, BPE.VALUE, BPE.XML_ID
							FROM ".PropertyEnumerationTable::getTableName()." BPE
							WHERE BPE.PROPERTY_ID = '".$propId."'";

				$obEnums = $connect->query($enumSql);
				while ($enum = $obEnums->fetch()) {
					$resultEnum[$enum['ID']] = $enum;
				}

				$TagCache->registerTag($cacheId);
				$TagCache->endTagCache();
				$DataCache->endDataCache($resultEnum);
			}
			$this->enumValues[$propId] = $resultEnum;
		}
	}

	/**
	 * @method compileMultiEntity
	 * @param array $arProperty
	 */
	public function compileMultiEntity(array $arProperty)
	{
		$utmClassName = 'PropertyMulti'.$arProperty['ID'];
		foreach (explode('_', $arProperty['CODE']) as $value) {
			$utmClassName .= Manager::mbUcFirst($value);
		}
		$utmClassNameFull = __NAMESPACE__.'\\'.$utmClassName.'Table';

		if (class_exists($utmClassNameFull)){
//			Entity\Base::destroy($utmClassNameFull);
			$utmEntity = Entity\Base::getInstance($utmClassNameFull);
		} else {
			$utmEntity = Entity\Base::compileEntity(
				$utmClassName,
				Model\PropertyUtmTable::getMap(),
				array(
					'table_name' => Model\PropertyUtmTable::getEntity($this->iblockId)->getDBTableName(),
					'namespace' => __NAMESPACE__,
				)
			);
		}

		$utmEntity->addField(new Entity\ReferenceField(
			'PROP_UTM',
			$this->entityProp,
			['=this.IBLOCK_PROPERTY_ID' => array('?i', $arProperty['ID']), '=this.IBLOCK_ELEMENT_ID' => 'ref.IBLOCK_ELEMENT_ID']
		));

		$chainField = $this->nextLevelField[$arProperty['CODE']];
		if (empty($chainField)){
			$cacheField = new Entity\TextField($arProperty['CODE'], [
				'title' => $arProperty['NAME'],
				'column_name' => 'PROPERTY_'.$arProperty['ID'],
				'required' => $arProperty['IS_REQUIRED'] == 'Y' ? true : false,
				'serialized' => true,
			]);
			$this->entityProp->addField($cacheField);
		} else {

			$field = new Entity\ReferenceField(
				$arProperty['CODE'],
				$utmEntity,
				array(
					'=this.IBLOCK_ELEMENT_ID' => 'ref.IBLOCK_ELEMENT_ID',
					'ref.IBLOCK_PROPERTY_ID' => array('?i', $arProperty['ID']),
				),
				array('join_type' => 'LEFT')
			);

			$this->entityProp->addField($field);

			$mField = new Entity\TextField($arProperty['CODE'].'_MULTI_VALUE', [
				'title' => $arProperty['NAME'],
				'column_name' => 'PROPERTY_'.$arProperty['ID'],
				'required' => $arProperty['IS_REQUIRED'] == 'Y' ? true : false,
				'serialized' => true,
			]);
			$this->entityProp->addField($mField);

			$this->resetQueryParams(['select'], $arProperty['CODE'], 'PROPERTY.'.$arProperty['CODE'].'_MULTI_VALUE');

		}
	}

	public function compileMultiEntityRevers(array $arProperty)
	{
		$utmClassName = 'PropertyMulti'.$arProperty['ID'];
		foreach (explode('_', $arProperty['CODE']) as $value) {
			$utmClassName .= Manager::mbUcFirst($value);
		}
		$utmClassNameFull = __NAMESPACE__.'\\'.$utmClassName.'Table';

		if (class_exists($utmClassNameFull)){
//			Entity\Base::destroy($utmClassNameFull);
			$utmEntity = Entity\Base::getInstance($utmClassNameFull);
		} else {
			$utmEntity = Entity\Base::compileEntity(
				$utmClassName,
				Model\PropertyUtmTable::getMap(),
				array(
					'table_name' => Model\PropertyUtmTable::getEntity($this->iblockId)->getDBTableName(),
					'namespace' => __NAMESPACE__,
				)
			);
		}

		$utmEntity->addField(new Entity\ReferenceField(
			'PROP_UTM',
			$this->entityProp,
			['=this.IBLOCK_PROPERTY_ID' => array('?i', $arProperty['ID']), '=this.IBLOCK_ELEMENT_ID' => 'ref.IBLOCK_ELEMENT_ID']
		));

		$chainField = $this->nextLevelField[$arProperty['CODE']];
		if (empty($chainField)){
			$chainField = 'VALUE';
		}

		$aliasField = new Entity\ExpressionField(
			$arProperty['CODE'].'_SINGLE',
			'%s',
			$utmEntity->getFullName().':'.'PROP_UTM.'.$chainField[0],
			array('data_type' => get_class($utmEntity->getField($chainField[0])))
		);
		$this->entityProp->addField($aliasField);

		$this->resetQueryParams(['filter'], $arProperty['CODE'], 'PROPERTY.'.$arProperty['CODE'].'_SINGLE');

		$mField = new Entity\TextField($arProperty['CODE'].'_MULTI_VALUE', [
			'title' => $arProperty['NAME'],
			'column_name' => 'PROPERTY_'.$arProperty['ID'],
			'required' => $arProperty['IS_REQUIRED'] == 'Y' ? true : false,
			'serialized' => true,
		]);
		$this->entityProp->addField($mField);

		$this->resetQueryParams(['select'], $arProperty['CODE'], 'PROPERTY.'.$arProperty['CODE'].'_MULTI_VALUE');
	}

	/**
	 * @method multiV1Property
	 * @param $arProperty
	 */
	protected function multiV1Property($arProperty)
	{
		$refName = $arProperty['CODE'].'_REF';
		$this->entityProp->addField(new Entity\ReferenceField(
			$refName,
			Model\PropertyElementTable::getEntity(),
			['=this.IBLOCK_ELEMENT_ID' => 'ref.IBLOCK_ELEMENT_ID', 'ref.IBLOCK_PROPERTY_ID' => array('?i', $arProperty['ID'])]
		));
		$field = new Entity\ExpressionField($arProperty['CODE'], '%s', $refName.'.VALUE');
		$this->entityProp->addField($field);
	}

	/**
	 * @method getParamsInQuery
	 * @param $type
	 * @param $arProperty
	 *
	 * @return mixed
	 */
	private function getParamsInQuery($type, $arProperty)
	{
		$arParams = $this->getQueryParams()->get($type);
		PR($arParams);
		foreach ($arParams as $k => $val) {
			if (preg_match('#PROPERTY.('.$arProperty['CODE'].')$#i', $val)){
				$modifierPropertyKey = $k;
				break;
			}
		}

		return $arParams[$modifierPropertyKey];
	}

	/**
	 * @method metaData
	 * @return array
	 * @throws ArgumentException
	 */
	public function metaData()
	{
		$iBlock = (int)$this->iblockId;

		if (intval($iBlock) == 0)
			throw new ArgumentException('Iblock id is null', ['IBLOCK_ID' => intval($this->iblockId)]);

		$metaData = [];

		$DataCache = Data\Cache::createInstance();
		$TagCache = new Data\TaggedCache();

		$cacheId = self::$cachePropId.$iBlock;
		$cacheTime = self::$cachePropTime * 30;
		$cacheDir = self::$cachePropDir;
//		$TagCache->clearByTag($cacheId);

		if ($DataCache->initCache($cacheTime, $cacheId, $cacheDir) && self::$usePropCache){
			$metaData = $DataCache->getVars();
		} else {
			$DataCache->startDataCache();
			$TagCache->startTagCache($cacheDir);

			$obProps = \Bitrix\Iblock\PropertyTable::getList([
				'select' => [
					'ID', 'IBLOCK_ID', 'CODE', 'PROPERTY_TYPE', 'MULTIPLE', 'ACTIVE',
					'LINK_IBLOCK_ID', 'IS_REQUIRED', 'USER_TYPE', 'NAME', 'VERSION',
				],
//				'select'=>['*'],
				'filter' => ['IBLOCK_ID' => $iBlock],
			]);
			while ($prop = $obProps->fetch()) {
				$metaData[$prop['CODE']] = $prop;
			}

			$TagCache->registerTag($cacheId);
			$TagCache->endTagCache();
			$DataCache->endDataCache($metaData);
		}

		$this->propTypes = [];

		foreach ($metaData as $code => $prop) {
			switch ($prop['PROPERTY_TYPE']) {
				case 'L':
					$this->propTypes['LIST'][] = $prop;
					break;
				case 'E':
					$this->propTypes['ELEMENT'][] = $prop;
					break;
				default:
					$this->propTypes['SCALAR'][] = $prop;
					break;
			}
		}

		return $metaData;
	}

	/**
	 * @method getAllProps
	 * @return array
	 */
	public function getAllProps()
	{
		$result = [];
		foreach ($this->metaData() as $code => $item) {
			$result[$code] = $code;
		}

		return $result;
	}

	/**
	 * @method getMetaData - get param metaData
	 * @return array|null
	 */
	public function getMetaData()
	{
		if (is_null($this->metaData)){
			$this->metaData = $this->metaData();
		}

		return $this->metaData;
	}

	/**
	 * @method getEntityProp - get param entityProp
	 * @return Entity\Base
	 */
	public function getEntityProp()
	{
		return $this->entityProp;
	}

	/**
	 * @method setEntityProp - set param EntityProp
	 * @param Entity\Base $entityProp
	 */
	public function setEntityProp($entityProp)
	{
		$this->entityProp = $entityProp;
	}

	/**
	 * @method getModifierQuery - get param modifierQuery
	 * @return array
	 */
	public function getModifierQuery()
	{
		return $this->modifierQuery;
	}

	/**
	 * @method setModifierQuery - set param ModifierQuery
	 * @param array $modifierQuery
	 */
	public function setModifierQuery($modifierQuery)
	{
		$this->modifierQuery = $modifierQuery;
	}

	/**
	 * @method getQueryParams
	 * @return Dictionary
	 */
	public function getQueryParams()
	{
		return $this->queryParams;
	}

	/**
	 * @param mixed $queryParams
	 *
	 * @return Property
	 */
	public function setQueryParams($queryParams)
	{
		$this->queryParams = new Dictionary($queryParams);

		return $this;
	}

	/**
	 * @method addModifierQuery
	 * @param $type
	 * @param $k
	 * @param $param
	 */
	public function addModifierQuery($type, $k, $param)
	{
		$this->modifierQuery[$type][$k] = $param;
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
	 * @return Property
	 */
	public function setVersion($version)
	{
		$this->version = $version;

		return $this;
	}


	public function resetQueryParams($types, $propCode, $resetValue)
	{
		if (!is_array($types))
			$types = [$types];

		foreach ($types as $type) {
			$params = $this->getQueryParams()->get($type);
			switch ($type) {
				case 'group':
				case 'select':
					foreach ($params as $k => $value) {
						if (preg_match('#PROPERTY.('.$propCode.')$#i', $value)){
							$modifierPropertyKey = $k;
							if ($params[$modifierPropertyKey]){
								$params[$modifierPropertyKey] = $resetValue;
								foreach ($params as $kk => $val) {
									$this->addModifierQuery($type, $kk, $val);
								}
							}
							break;
						}
					}
					break;
				case 'order':
				case 'filter':
					foreach ($params as $code => $value) {
						if (preg_match('#PROPERTY.('.$propCode.')#i', $code)){
							$filterPropertyKey = $code;
							$filterVal = $params[$filterPropertyKey];
							unset($params[$filterPropertyKey]);
							$params[$resetValue] = $filterVal;
							foreach ($params as $k => $val) {
								$this->addModifierQuery('filter', $k, $val);
							}
							break;
						}
					}

					break;
			}
		}
	}

	/**
	 * @method getCachePropId - get param cachePropId
	 * @return string
	 */
	public static function getCachePropId()
	{
		return self::$cachePropId;
	}

	/**
	 * @method getCachePropDir - get param cachePropDir
	 * @return string
	 */
	public static function getCachePropDir()
	{
		return self::$cachePropDir;
	}

	/**
	 * @method getEnumCacheTag - get param enumCacheTag
	 * @return string
	 */
	public static function getEnumCacheTag()
	{
		return self::$enumCacheTag;
	}


}