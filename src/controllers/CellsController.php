<?php

namespace soerenmeier\meierlabstable\controllers;

use Craft;
use craft\web\Controller;
use soerenmeier\meierlabstable\fields\RichTable;
use yii\web\BadRequestHttpException;
use yii\web\Response;

/**
 * Renders the CKEditor input of a rich text cell for a row that was added in
 * the browser.
 */
class CellsController extends Controller
{
	public function actionRender(): Response
	{
		$this->requireCpRequest();
		$this->requirePostRequest();
		$this->requireAcceptsJson();
		$this->requirePermission('accessCp');

		$fieldId = (int)$this->request->getRequiredBodyParam('fieldId');
		$colId = $this->request->getRequiredBodyParam('colId');
		// the full input name of the cell, e.g. fields[table][3][col1]
		$name = $this->request->getRequiredBodyParam('name');
		$elementId = $this->request->getBodyParam('elementId');
		$siteId = $this->request->getBodyParam('siteId');

		$field = Craft::$app->getFields()->getFieldById($fieldId);
		if (!$field instanceof RichTable) {
			throw new BadRequestHttpException('Not a rich table field');
		}
		$column = $field->columns[$colId] ?? null;
		if (($column['type'] ?? null) !== RichTable::TYPE_RICHTEXT) {
			throw new BadRequestHttpException('Not a rich text column');
		}
		// Matrix entries have names like fields[ctn][entries][uid:abc-def]
		if (!is_string($name) || !preg_match('/^[^\s"\'<>&\[\]]+(\[[^\s"\'<>&\[\]]+\])*$/', $name)) {
			throw new BadRequestHttpException('Invalid name');
		}

		// the element only decides which link options CKEditor offers
		$element = null;
		if ($elementId) {
			$element = Craft::$app->getElements()->getElementById(
				(int)$elementId,
				siteId: $siteId ? (int)$siteId : null,
				criteria: ['drafts' => null, 'provisionalDrafts' => null, 'status' => null],
			);
			if ($element && !$element->canView(Craft::$app->getUser()->getIdentity())) {
				$element = null;
			}
		}

		$view = $this->getView();
		$html = $field->cellInputHtml($colId, null, $element, $name);

		return $this->asJson([
			'html' => $html,
			'headHtml' => $view->getHeadHtml(),
			'bodyHtml' => $view->getBodyHtml(),
		]);
	}
}
