<?php
/**
 * Created by PhpStorm.
 * User: dremin_s
 * Date: 28.07.2016
 * Time: 15:56
 */

namespace AB\Iblock\Handlers;

use AB\Iblock\Property;
use Bitrix\Main\Loader;
use Bitrix\Main\Data;

Loader::includeModule('iblock');

class HandlerIblockElement
{

	public static function resetCache(&$arFields)
	{
		$CacheTag = new Data\TaggedCache();
		$CacheTag->clearByTag(Property::getCachePropId());
		$CacheTag->clearByTag(Property::getEnumCacheTag().$arFields['ID']);
	}

	public static function resetCacheDelete($ID)
	{
		$CacheTag = new Data\TaggedCache();
		$CacheTag->clearByTag(Property::getCachePropId());
		$CacheTag->clearByTag(Property::getEnumCacheTag().$ID);
	}
}