<?php
/**
 * Created by PhpStorm.
 * User: dremin_s
 * Date: 26.07.2016
 * Time: 12:19
 */

namespace AB\Iblock\Model;

use Bitrix\Main\Entity;
use Bitrix\Main\Loader;

Loader::includeModule('iblock');

class ElementTable extends \Bitrix\Iblock\ElementTable
{
	public static function getMap()
	{
		$map = parent::getMap();

		$map['DETAIL_PICTURE_FILE'] = new Entity\ReferenceField(
			'DETAIL_PICTURE_FILE',
			FileTable::getEntity(),
			['=this.DETAIL_PICTURE'=>'ref.ID']
		);
		$map['PREVIEW_PICTURE_FILE'] = new Entity\ReferenceField(
			'PREVIEW_PICTURE_FILE',
			FileTable::getEntity(),
			['=this.PREVIEW_PICTURE'=>'ref.ID']
		);

		return $map;
	}


}