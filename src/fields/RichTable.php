<?php

namespace soerenmeier\meierlabstable\fields;

use Craft;
use craft\base\ElementInterface;
use craft\ckeditor\Field as CkeField;
use craft\fields\Table;
use craft\fields\data\ColorData;
use craft\helpers\Cp;
use craft\helpers\Html;
use craft\helpers\Json;
use craft\helpers\StringHelper;
use craft\htmlfield\HtmlFieldData;
use craft\validators\ColorValidator;
use craft\validators\HandleValidator;
use craft\validators\UrlValidator;
use craft\web\assets\tablesettings\TableSettingsAsset;
use craft\web\assets\timepicker\TimepickerAsset;
use soerenmeier\meierlabstable\MeierLabsTable;
use yii\validators\EmailValidator;

/**
 * Craft's Table field with an additional "Rich text" column type, edited with
 * CKEditor.
 *
 * A rich text column uses the config of a CKEditor field selected in the column
 * settings, or bold, italic and links (with "Open in new tab?") by default.
 * The cell is stored as HTML (purified like the CKEditor field does) and
 * returned as HTML in Twig and GraphQL.
 *
 * Everything else works like Craft's Table field and the stored data has the
 * same format, so a Table field can be switched to this type and back.
 */
class RichTable extends Table
{
	public const TYPE_RICHTEXT = 'richtext';

	/**
	 * @var CkeField[] the cell editors, indexed by column id
	 */
	private array $editors = [];

	public function __clone()
	{
		// field layouts clone fields to override their handle, which the
		// editors use
		$this->editors = [];
	}

	public static function displayName(): string
	{
		return Craft::t('meierlabs-table', 'Rich Table');
	}

	public static function icon(): string
	{
		return 'table';
	}

	private static function typeOptions(): array
	{
		$options = [
			'checkbox' => Craft::t('app', 'Checkbox'),
			'color' => Craft::t('app', 'Color'),
			'date' => Craft::t('app', 'Date'),
			'select' => Craft::t('app', 'Dropdown'),
			'email' => Craft::t('app', 'Email'),
			'heading' => Craft::t('app', 'Row heading'),
			'lightswitch' => Craft::t('app', 'Lightswitch'),
			'multiline' => Craft::t('app', 'Multi-line text'),
			'number' => Craft::t('app', 'Number'),
			self::TYPE_RICHTEXT => Craft::t('meierlabs-table', 'Rich text'),
			'singleline' => Craft::t('app', 'Single-line text'),
			'time' => Craft::t('app', 'Time'),
			'url' => Craft::t('app', 'URL'),
		];

		asort($options);

		return $options;
	}

	/**
	 * The options of the column setting "Editor": the default config and all
	 * CKEditor fields, by uid.
	 */
	private static function editorOptions(): array
	{
		$options = ['' => Craft::t('meierlabs-table', 'Default: bold, italic, link')];

		foreach (Craft::$app->getFields()->getAllFields() as $field) {
			if ($field instanceof CkeField) {
				$options[$field->uid] = "$field->name ($field->handle)";
			}
		}

		return $options;
	}

	/**
	 * Same as Table::validateColumns() but with the rich text type.
	 */
	public function validateColumns(): void
	{
		$typeOptions = self::typeOptions();

		foreach ($this->columns as &$col) {
			if (!isset($typeOptions[$col['type']])) {
				$col['type'] = 'singleline';
			}

			if ($col['handle']) {
				$error = null;

				if (!preg_match('/^' . HandleValidator::$handlePattern . '$/', $col['handle'])) {
					$error = Craft::t('app', '“{handle}” isn’t a valid handle.', [
						'handle' => $col['handle'],
					]);
				} elseif (preg_match('/^col\d+$/', $col['handle'])) {
					$error = Craft::t('app', 'Column handles can’t be in the format “{format}”.', [
						'format' => 'colX',
					]);
				}

				if ($error) {
					$col['handle'] = [
						'value' => $col['handle'],
						'hasErrors' => true,
					];
					$this->addError('columns', $error);
				}
			}
		}
	}

	public function getSettingsHtml(): ?string
	{
		return $this->settingsHtml(false);
	}

	public function getReadOnlySettingsHtml(): ?string
	{
		return $this->settingsHtml(true);
	}

	/**
	 * Table::settingsHtml() with the rich text type and the "Editor" column.
	 *
	 * Craft.TableFieldSettings finds the type select in the fourth column, so
	 * "Editor" has to come after it.
	 */
	private function settingsHtml(bool $readOnly): string
	{
		$columnSettings = [
			'heading' => [
				'heading' => Craft::t('app', 'Column Heading'),
				'type' => 'singleline',
				'autopopulate' => 'handle',
			],
			'handle' => [
				'heading' => Craft::t('app', 'Handle'),
				'code' => true,
				'type' => 'singleline',
			],
			'width' => [
				'heading' => Craft::t('app', 'Width'),
				'code' => true,
				'type' => 'singleline',
				'width' => 50,
			],
			'type' => [
				'heading' => Craft::t('app', 'Type'),
				'class' => 'thin',
				'type' => 'select',
				'options' => self::typeOptions(),
			],
			'ckeField' => [
				'heading' => Craft::t('meierlabs-table', 'Editor'),
				'info' => Craft::t(
					'meierlabs-table',
					'Only for rich text columns: the CKEditor field whose toolbar and config the cells use.'
				),
				'class' => 'thin',
				'type' => 'select',
				'options' => self::editorOptions(),
			],
		];

		$dropdownSettingsCols = [
			'label' => [
				'heading' => Craft::t('app', 'Option Label'),
				'type' => 'singleline',
				'autopopulate' => 'value',
				'class' => 'option-label',
			],
			'value' => [
				'heading' => Craft::t('app', 'Value'),
				'type' => 'singleline',
				'class' => 'option-value code',
			],
			'default' => [
				'heading' => Craft::t('app', 'Default?'),
				'type' => 'checkbox',
				'radioMode' => true,
				'class' => 'option-default thin',
			],
		];

		$dropdownSettingsHtml = Cp::editableTableFieldHtml([
			'label' => Craft::t('app', 'Dropdown Options'),
			'instructions' => Craft::t('app', 'Define the available options.'),
			'id' => '__ID__',
			'name' => '__NAME__',
			'addRowLabel' => Craft::t('app', 'Add an option'),
			'allowAdd' => true,
			'allowReorder' => true,
			'allowDelete' => true,
			'cols' => $dropdownSettingsCols,
			'initJs' => false,
		]);

		// Replace heading columns with singleline, for the Default Values table
		$columns = array_map(function (array $column) {
			if ($column['type'] === 'heading') {
				$column['type'] = 'singleline';
				$column['class'] = 'heading';
			}
			return $column;
		}, $this->columns);

		$view = Craft::$app->getView();

		$view->registerAssetBundle(TimepickerAsset::class);
		$view->registerAssetBundle(TableSettingsAsset::class);
		MeierLabsTable::$plugin->vite->register('src/main.js');
		$view->registerJs('new Craft.TableFieldSettings(' .
			Json::encode($view->namespaceInputName('columns')) . ', ' .
			Json::encode($view->namespaceInputName('defaults')) . ', ' .
			Json::encode($columns) . ', ' .
			Json::encode($this->defaults ?? []) . ', ' .
			Json::encode($columnSettings) . ', ' .
			Json::encode($dropdownSettingsHtml) . ', ' .
			Json::encode($dropdownSettingsCols) . ', ' .
			Json::encode($this->staticRows) . ', ' .
			');');

		$columnsField = $view->renderTemplate('_components/fieldtypes/Table/columntable.twig', [
			'cols' => $columnSettings,
			'rows' => $this->columns,
			'errors' => $this->getErrors('columns'),
			'readOnly' => $readOnly,
		]);

		$defaultsField = Cp::editableTableFieldHtml([
			'label' => Craft::t('app', 'Default Values'),
			'instructions' => Craft::t('app', 'Define the default values for the field.'),
			'id' => 'defaults',
			'name' => 'defaults',
			'allowAdd' => true,
			'allowReorder' => true,
			'allowDelete' => true,
			'cols' => $columns,
			'rows' => array_map(function (array $row) {
				// make sure the row has a UUID
				$row['rowId'] ??= StringHelper::uuid();
				return $row;
			}, $this->defaults ?? []),
			'initJs' => false,
			'static' => $readOnly,
			'includeRowId' => true,
		]);

		return $view->renderTemplate('_components/fieldtypes/Table/settings.twig', [
			'field' => $this,
			// the class disables the "Editor" setting of other column types
			'columnsField' => Html::tag('div', $columnsField, ['class' => 'ml-rich-table-columns']),
			'defaultsField' => $defaultsField,
			'readOnly' => $readOnly,
		]);
	}

	/**
	 * The CKEditor field that edits and purifies the cells of a rich text
	 * column: a copy of the selected CKEditor field, or the default config.
	 */
	public function cellEditor(string $colId): CkeField
	{
		if (isset($this->editors[$colId])) {
			return $this->editors[$colId];
		}

		$column = $this->columns[$colId] ?? [];
		$uid = $column['ckeField'] ?? null;
		$source = $uid ? Craft::$app->getFields()->getFieldByUid($uid) : null;

		if ($source instanceof CkeField) {
			$editor = clone $source;
		} else {
			$editor = new CkeField([
				'toolbar' => ['bold', 'italic', 'link'],
				'advancedLinkFields' => ['target'],
			]);
		}

		// The textarea of the editor gets the table's handle inside the cell's
		// namespace, see normalizeCells(). CKEditor looks its handle up in the
		// element's field layout, where the table is.
		$editor->handle = $this->handle;
		$editor->name = ($column['heading'] ?? '') ?: $this->name;
		$editor->showWordCount = false;
		// nested entries can't live inside a cell
		$editor->toolbar = array_values(array_filter(
			$editor->toolbar,
			fn ($item) => !is_string($item) || !str_starts_with($item, 'createEntry')
		));

		return $this->editors[$colId] = $editor;
	}

	public function normalizeValue(mixed $value, ?ElementInterface $element): mixed
	{
		return $this->normalizeCells(parent::normalizeValue($value, $element), $element);
	}

	public function normalizeValueFromRequest(mixed $value, ?ElementInterface $element): mixed
	{
		return $this->normalizeCells(parent::normalizeValueFromRequest($value, $element), $element);
	}

	/**
	 * Turns rich text cells into the CKEditor field's value (HtmlFieldData),
	 * which prints the HTML with parsed reference tags.
	 */
	private function normalizeCells(?array $value, ?ElementInterface $element): ?array
	{
		if (!$value) {
			return $value;
		}

		foreach ($this->richTextColumns() as $colId => $col) {
			$editor = $this->cellEditor($colId);

			foreach ($value as &$row) {
				$cell = $row[$colId] ?? null;
				// from the request: the editor's textarea
				if (is_array($cell)) {
					$cell = $cell[$this->handle] ?? null;
				}
				if (!$cell instanceof HtmlFieldData) {
					$cell = $editor->normalizeValue(is_string($cell) ? $cell : null, $element);
				}

				$row[$colId] = $cell;
				if ($col['handle']) {
					$row[$col['handle']] = $cell;
				}
			}
			unset($row);
		}

		return $value;
	}

	public function serializeValue(mixed $value, ?ElementInterface $element): mixed
	{
		return parent::serializeValue($this->serializeCells($value, $element), $element);
	}

	public function serializeValueForDb(mixed $value, ElementInterface $element): mixed
	{
		return parent::serializeValueForDb($this->serializeCells($value, $element), $element);
	}

	/**
	 * Turns rich text cells into HTML strings, purified by the cell editor.
	 */
	private function serializeCells(mixed $value, ?ElementInterface $element): mixed
	{
		if (!is_array($value)) {
			return $value;
		}

		foreach ($this->richTextColumns() as $colId => $col) {
			$editor = $this->cellEditor($colId);

			foreach ($value as &$row) {
				if (is_array($row) && isset($row[$colId])) {
					$row[$colId] = $editor->serializeValue($row[$colId], $element);
				}
			}
			unset($row);
		}

		return $value;
	}

	protected function searchKeywords(mixed $value, ElementInterface $element): string
	{
		if (is_array($value)) {
			foreach (array_keys($this->richTextColumns()) as $colId) {
				foreach ($value as &$row) {
					if (($row[$colId] ?? null) instanceof HtmlFieldData) {
						$row[$colId] = $row[$colId]->getPlainText();
					}
				}
				unset($row);
			}
		}

		return parent::searchKeywords($value, $element);
	}

	protected function inputHtml(mixed $value, ?ElementInterface $element, bool $inline): string
	{
		$view = Craft::$app->getView();
		$view->registerAssetBundle(TimepickerAsset::class);
		MeierLabsTable::$plugin->vite->register('src/main.js');

		return $this->tableHtml($value, $element, false);
	}

	public function getStaticHtml(mixed $value, ElementInterface $element): string
	{
		MeierLabsTable::$plugin->vite->register('src/main.js');

		return $this->tableHtml($value, $element, true);
	}

	/**
	 * The CKEditor input of a rich text cell.
	 *
	 * @param string $namespace the input name of the cell relative to the
	 * current namespace, e.g. `table[0][col1]`
	 */
	public function cellInputHtml(
		string $colId,
		mixed $value,
		?ElementInterface $element,
		string $namespace,
	): string {
		$editor = $this->cellEditor($colId);

		// inputHtml() instead of getInputHtml(): plugins that extend every
		// CKEditor input (EVENT_DEFINE_INPUT_HTML) don't belong in a cell
		$render = fn () => $this->inputHtml($value, $element, false);

		return Craft::$app->getView()->namespaceInputs(
			fn () => $render->call($editor),
			$namespace
		);
	}

	/**
	 * Table::_getInputHtml(), with rich text cells rendered by cellInputHtml().
	 */
	private function tableHtml(mixed $value, ?ElementInterface $element, bool $static): string
	{
		if (empty($this->columns)) {
			return '';
		}

		// Translate the column headings and dropdown option labels,
		// and configure number columns with the active formatting locale
		$columns = [];
		$locale = Craft::$app->getFormattingLocale()->id;

		foreach ($this->columns as $colId => $column) {
			if (!empty($column['heading'])) {
				$column['heading'] = Craft::t('site', $column['heading']);
			}
			if (!empty($column['options'])) {
				array_walk($column['options'], function (&$option) {
					$option['label'] = Craft::t('site', $option['label']);
				});
			}
			if ($column['type'] === 'number') {
				$column['locale'] = $locale;
			}
			$columns[$colId] = $column;
		}

		$value = is_array($value) ? array_values($value) : [];

		// Make sure the value contains at least the minimum number of rows
		if ($this->minRows) {
			for ($i = count($value); $i < $this->minRows; $i++) {
				$value[] = [];
			}
		}

		// Explicitly set each cell value to an array with a 'value' key
		$checkForErrors = $element && $element->hasErrors($this->handle);
		foreach ($value as $rowId => &$row) {
			foreach ($columns as $colId => $col) {
				if ($col['type'] === self::TYPE_RICHTEXT) {
					$cell = $row[$colId] ?? null;
					$row[$colId] = [
						'value' => $static
							? Html::tag('div', (string)$cell, ['class' => 'ml-rich-table-static'])
							: $this->cellInputHtml($colId, $cell, $element, "$this->handle[$rowId][$colId]"),
						'hasErrors' => false,
					];
				} elseif (isset($row[$colId])) {
					$hasErrors = $checkForErrors && !$this->validateCell($col['type'], $row[$colId]);
					$row[$colId] = [
						'value' => match ($col['type']) {
							'heading' => Html::encode($row[$colId]),
							default => $row[$colId],
						},
						'hasErrors' => $hasErrors,
					];
				}
			}
		}
		unset($row);

		$html = Craft::$app->getView()->renderTemplate('meierlabs-table/input.twig', [
			'id' => $this->getInputId(),
			'name' => $this->handle,
			'cols' => $columns,
			'rows' => $value,
			'minRows' => $this->minRows,
			'maxRows' => $this->maxRows,
			'static' => $static,
			'staticRows' => $this->staticRows,
			'allowAdd' => true,
			'allowDelete' => true,
			'allowReorder' => true,
			'addRowLabel' => Craft::t('site', $this->addRowLabel),
			'describedBy' => $this->describedBy,
			'includeRowId' => $this->staticRows,
		]);

		return Html::tag('div', $html, [
			'class' => 'ml-rich-table',
			'data' => [
				'field-id' => $this->id,
				'element-id' => $element?->id,
				'site-id' => $element?->siteId,
			],
		]);
	}

	/**
	 * Whether a cell is valid, for the error marker of the cell (Table's
	 * validator is private).
	 */
	private function validateCell(string $type, mixed $value): bool
	{
		if ($value === null || $value === '') {
			return true;
		}

		$validator = match ($type) {
			'color' => new ColorValidator(),
			'url' => new UrlValidator(),
			'email' => new EmailValidator(),
			default => null,
		};

		if (!$validator) {
			return true;
		}

		return $validator->validate($value instanceof ColorData ? $value->getHex() : $value);
	}

	/**
	 * @return array the rich text columns, indexed by column id
	 */
	private function richTextColumns(): array
	{
		return array_filter(
			$this->columns,
			fn (array $col) => ($col['type'] ?? null) === self::TYPE_RICHTEXT
		);
	}
}
