# VicProductExport — Shopware 6 Product Export to Excel

A Shopware 6 plugin that lets you export products to `.xlsx` directly from the admin panel. Choose exactly which fields to include, reorder the columns by drag-and-drop, and filter by status or stock.

<img width="1544" height="883" alt="Captura desde 2026-05-12 13-09-11" src="https://github.com/user-attachments/assets/62de167e-4c05-46ad-98e7-8aafbc261f38" />
<img width="1003" height="784" alt="Captura desde 2026-05-12 13-09-53" src="https://github.com/user-attachments/assets/948b8926-5467-4866-9771-390a63105a90" />

---

## Features

- **24 exportable fields** organized in groups
- **Custom fields support** — auto-detects fields added by other plugins
- **Drag-and-drop column ordering** — define the exact column order in the Excel file
- **Filters** — export only active products and/or set a minimum stock threshold
- **Styled output** — header row with color, auto-sized columns
- **Translations** — English, German, Spanish
- **Admin menu entry** under Catalogue

---

## Exportable fields

| Group | Fields |
|---|---|
| Basic | Name, SKU, Price (gross), Purchase price, Stock, Available stock, Active |
| Identity | EAN, Release date, Topseller, Shipping free |
| Relations | Manufacturer, Categories, Tags, Tax rate, Delivery time |
| Dimensions | Weight, Height, Width, Length |
| Purchase settings | Min. purchase, Max. purchase |
| Content | Description, Properties |
| Custom fields | Any custom fields registered for the `product` entity by other plugins |

---

## Requirements

- Shopware 6.7.x
- PHP 8.2+
- `phpoffice/phpspreadsheet` ^3.0 (installed via the root project's composer)

---

## Installation

**1. Copy the plugin**

```bash
cp -r VicProductExport /your-shopware-root/custom/plugins/
```

**2. Install PhpSpreadsheet**

```bash
composer require phpoffice/phpspreadsheet:"^3.0"
```

**3. Install and activate the plugin**

```bash
bin/console plugin:refresh
bin/console plugin:install --activate VicProductExport
```

**4. Build the admin**

```bash
bin/build-administration.sh
bin/console cache:clear
```

---

## Usage

1. Go to **Catalogue → Export to Excel** in the Shopware admin
2. Check the fields you want to export
3. Drag rows in the **Column order** card to arrange the columns
4. Optionally apply filters (active only, minimum stock)
5. Click **Export .xlsx** — the file downloads immediately

---

## How it works

```
Admin UI (Vue 3)
    ↓  POST /api/_action/vic-product-export/export
        { fields: [...], fieldLabels: {...}, filters: {...} }
ExportController (PHP)
    ↓  ProductRepository::search() with dynamic associations
        (only loads manufacturer, categories, tags, etc. if selected)
PhpSpreadsheet
    ↓  Builds .xlsx in memory
StreamedResponse → browser download
```

Custom fields are read from `$product->getCustomFields()` — no additional DAL associations needed.

---

## File structure

```
VicProductExport/
├── composer.json
└── src/
    ├── VicProductExport.php
    ├── Controller/
    │   └── ExportController.php
    └── Resources/
        ├── config/
        │   ├── routes.xml
        │   └── services.xml
        └── app/administration/src/
            ├── main.js
            └── module/vic-product-export/
                ├── index.js
                ├── snippet/  (en-GB, de-DE, es-ES)
                └── page/vic-product-export/
                    ├── index.js
                    ├── vic-product-export.html.twig
                    └── vic-product-export.scss
```

---

## License

MIT — [Vicmescan](https://github.com/Vicmescan)
