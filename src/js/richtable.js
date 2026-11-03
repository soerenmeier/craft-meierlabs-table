// Rows added in the browser get a plain textarea from Craft.EditableTable for
// rich text cells. Swap it for the CKEditor input rendered by the server.
const $ = window.jQuery;

async function renderCell(td, $wrapper) {
	const $textarea = $(td).children('textarea');
	const name = $textarea.attr('name');
	const colId = name?.match(/\[([^\[\]]+)\]$/)?.[1];
	if (!colId) return;

	$textarea.addClass('hidden');
	const $spinner = $('<div class="spinner"></div>').appendTo(td);

	try {
		const { data } = await Craft.sendActionRequest(
			'POST',
			'meierlabs-table/cells/render',
			{
				data: {
					fieldId: $wrapper.data('field-id'),
					elementId: $wrapper.data('element-id'),
					siteId: $wrapper.data('site-id'),
					colId,
					name,
				},
			},
		);
		$(td).html(data.html);
		await Craft.appendHeadHtml(data.headHtml);
		await Craft.appendBodyHtml(data.bodyHtml);
	} catch (e) {
		console.error('rich table: could not render the cell', e);
		$spinner.remove();
		$textarea.removeClass('hidden');
	}
}

export function initRichTable() {
	Garnish.on(Craft.EditableTable, 'addRow', ev => {
		const $wrapper = ev.target.$table.closest('.ml-rich-table');
		if (!$wrapper.length) return;

		ev.$tr.children('td.richtext-cell').each(function () {
			renderCell(this, $wrapper);
		});
	});
}
