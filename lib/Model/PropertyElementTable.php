<?php namespace AB\Iblock\Model;

use Bitrix\Main\Entity;
use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

class PropertyElementTable extends Entity\DataManager
{
	public static function getTableName()
	{
		return 'b_iblock_element_property';
	}

	public static function getMap()
	{
		$map = array(
			'ID' => new Entity\IntegerField('ID', array(
					'title' => Loc::getMessage('B_IBLOCK_ELEMENT_PROPERTY_ENTITY_ID_FIELD'),
					'primary' => true,
					'autocomplete' => true,
				)
			),
			'IBLOCK_PROPERTY_ID' => new Entity\IntegerField('IBLOCK_PROPERTY_ID', array(
					'title' => Loc::getMessage('B_IBLOCK_ELEMENT_PROPERTY_ENTITY_IBLOCK_PROPERTY_ID_FIELD'),
					'required' => true,
				)
			),
			'IBLOCK_ELEMENT_ID' => new Entity\IntegerField('IBLOCK_ELEMENT_ID', array(
					'title' => Loc::getMessage('B_IBLOCK_ELEMENT_PROPERTY_ENTITY_IBLOCK_ELEMENT_ID_FIELD'),
					'required' => true,
				)
			),
			'VALUE' => new Entity\TextField('VALUE', array(
					'title' => Loc::getMessage('B_IBLOCK_ELEMENT_PROPERTY_ENTITY_VALUE_FIELD'),
					'required' => true,
				)
			),
			'VALUE_TYPE' => new Entity\StringField('VALUE_TYPE', array(
					'title' => Loc::getMessage('B_IBLOCK_ELEMENT_PROPERTY_ENTITY_VALUE_TYPE_FIELD'),
					'required' => true,
				)
			),
			'VALUE_ENUM' => new Entity\IntegerField('VALUE_ENUM', array(
					'title' => Loc::getMessage('B_IBLOCK_ELEMENT_PROPERTY_ENTITY_VALUE_ENUM_FIELD'),
				)
			),
			'VALUE_NUM' => new Entity\StringField('VALUE_NUM', array(
					'title' => Loc::getMessage('B_IBLOCK_ELEMENT_PROPERTY_ENTITY_VALUE_NUM_FIELD'),
				)
			),
			'DESCRIPTION' => new Entity\StringField('DESCRIPTION', array(
					'title' => Loc::getMessage('B_IBLOCK_ELEMENT_PROPERTY_ENTITY_DESCRIPTION_FIELD'),
				)
			),
		);

		return $map;
	}

    
	public static function validateValueType ()
	{
		return array(
			new Entity\Validator\Length(null, 4),
		);
	}

	public static function validateDescription ()
	{
		return array(
			new Entity\Validator\Length(null, 255),
		);
	}

}