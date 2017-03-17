<?
spl_autoload_register(function ($className) {
	preg_match('/^(.*?)([\w]+)$/i', $className, $matches);
	if (count($matches) < 3) {
		return;
	}

	$filePath = implode(DIRECTORY_SEPARATOR, array(
			__DIR__,
			"lib",
			str_replace('\\', DIRECTORY_SEPARATOR, trim($matches[1], '\\')),
			str_replace('_', DIRECTORY_SEPARATOR, $matches[2]) . '.php'
	));
	$filePath = str_replace('AB\Iblock'.DIRECTORY_SEPARATOR,'',$filePath);
	$filePath = preg_replace('#AB/\Iblock\/#','',$filePath);
	$filePath = str_replace(DIRECTORY_SEPARATOR . DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR, $filePath);

	if (is_readable($filePath) && is_file($filePath)) {
		/** @noinspection PhpIncludeInspection */
		require_once $filePath;
	}
});