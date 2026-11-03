<?php

namespace soerenmeier\meierlabstable\assetbundles;

use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;

class Assets extends AssetBundle
{
	public function init(): void
	{
		parent::init();

		// Define the path that your JS files are located
		$this->sourcePath = '@soerenmeier/meierlabstable/dist';

		$this->depends = [
			CpAsset::class,
		];
	}
}
