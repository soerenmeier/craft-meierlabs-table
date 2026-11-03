<?php

namespace soerenmeier\meierlabstable;

use Craft;
use craft\base\Plugin;
use craft\events\RegisterComponentTypesEvent;
use craft\services\Fields;
use soerenmeier\meierlabstable\fields\RichTable;
use soerenmeier\meierlabstable\services\ViteManifestService;
use yii\base\Event;

/**
 * @property-read ViteManifestService $vite
 */
class MeierLabsTable extends Plugin
{
	public static $plugin;

	public string $schemaVersion = '1.0.0';

	public function init(): void
	{
		parent::init();
		self::$plugin = $this;

		// Register services
		$this->setComponents([
			'vite' => ViteManifestService::class,
		]);

		// Defer most setup tasks until Craft is fully initialized
		Craft::$app->onInit($this->attachEventHandlers(...));
	}

	private function attachEventHandlers(): void
	{
		Event::on(
			Fields::class,
			Fields::EVENT_REGISTER_FIELD_TYPES,
			function (RegisterComponentTypesEvent $event) {
				$event->types[] = RichTable::class;
			}
		);
	}
}
