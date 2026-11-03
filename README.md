# Meier Labs Table for Craft CMS

## Overview

A **Rich Table** field: Craft's Table field with an additional column type
**Rich text**, edited with CKEditor.

- Each rich text column uses the toolbar and config of a CKEditor field you
  select in the column settings (column "Editor"), or by default bold, italic
  and links (to entries, assets or URLs, with "Open in new tab?").
- Cells are purified like the CKEditor field does it and stored as HTML in the
  same format as a Table field, so an existing Table field can be switched to
  Rich Table (and back) without losing data.
- Works inside Matrix entries. Static rows, min/max rows and default values
  come from the Table field.
- Nested entries (`createEntry`) are removed from the cell toolbar, and plugins
  that extend every CKEditor input don't show up in cells.

## Templates and GraphQL

A rich text cell returns its HTML, with reference tags (links to entries and
assets) parsed into URLs:

```twig
{% for row in entry.priceTable %}
	<td>{{ row.service }}</td>
	<td>{{ row.description }}</td>
{% endfor %}
```

```graphql
{
	entry(slug: "example") {
		... on page_Entry {
			priceTable {
				service
				description # "<p>With <strong>bold</strong> text</p>"
			}
		}
	}
}
```

In PHP the cell is the CKEditor field's value (`craft\htmlfield\HtmlFieldData`),
so `getRawContent()`, `getPlainText()` and `getMarkdown()` work too.

## Requirements

Craft CMS 5 and CKEditor 5.

## Installation

```bash
composer require soerenmeier/meierlabs-table -w && php craft plugin/install meierlabs-table
```
