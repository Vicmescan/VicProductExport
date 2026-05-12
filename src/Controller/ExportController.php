<?php declare(strict_types=1);

namespace Vic\ProductExport\Controller;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: ['_routeScope' => ['api']])]
class ExportController
{
    public function __construct(
        private readonly EntityRepository $productRepository,
    ) {}

    #[Route(
        path: '/api/_action/vic-product-export/export',
        name: 'api.action.vic_product_export.export',
        methods: ['POST']
    )]
    public function export(Request $request, Context $context): StreamedResponse
    {
        $body = json_decode($request->getContent(), true) ?? [];
        $fields = $body['fields'] ?? ['name', 'productNumber'];
        $filters = $body['filters'] ?? [];
        $fieldLabels = $body['fieldLabels'] ?? [];

        $criteria = $this->buildCriteria($fields, $filters);
        $products = $this->productRepository->search($criteria, $context);

        $spreadsheet = $this->buildSpreadsheet($fields, $fieldLabels, $products->getElements());

        return new StreamedResponse(
            function () use ($spreadsheet): void {
                $writer = new Xlsx($spreadsheet);
                $writer->save('php://output');
            },
            200,
            [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="products_export.xlsx"',
                'Cache-Control' => 'max-age=0',
                'Pragma' => 'public',
            ]
        );
    }

    private function buildCriteria(array $fields, array $filters): Criteria
    {
        $criteria = new Criteria();
        $criteria->setLimit(10000);

        if (in_array('manufacturer', $fields, true)) {
            $criteria->addAssociation('manufacturer');
        }
        if (in_array('categories', $fields, true)) {
            $criteria->addAssociation('categories');
        }
        if (in_array('properties', $fields, true)) {
            $criteria->addAssociation('properties.group');
        }
        if (in_array('tags', $fields, true)) {
            $criteria->addAssociation('tags');
        }
        if (in_array('tax', $fields, true)) {
            $criteria->addAssociation('tax');
        }
        if (in_array('deliveryTime', $fields, true)) {
            $criteria->addAssociation('deliveryTime');
        }

        if (!empty($filters['onlyActive'])) {
            $criteria->addFilter(new EqualsFilter('active', true));
        }
        if (isset($filters['minStock']) && $filters['minStock'] > 0) {
            $criteria->addFilter(new RangeFilter('stock', [RangeFilter::GTE => (int) $filters['minStock']]));
        }
        if (!empty($filters['categoryId'])) {
            $criteria->addFilter(new EqualsFilter('categories.id', $filters['categoryId']));
        }

        return $criteria;
    }

    private function buildSpreadsheet(array $fields, array $fieldLabels, array $products): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Products');

        $builtinLabels = $this->getFieldLabels();

        // Header row
        $col = 1;
        foreach ($fields as $field) {
            $label = $fieldLabels[$field] ?? $builtinLabels[$field] ?? $field;
            $sheet->getCell([$col, 1])->setValue($label);
            $col++;
        }

        // Style header
        $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($fields));
        $sheet->getStyle("A1:{$lastCol}1")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => '2E7D32']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        // Data rows
        $row = 2;
        foreach ($products as $product) {
            $col = 1;
            foreach ($fields as $field) {
                $sheet->getCell([$col, $row])->setValue($this->getFieldValue($product, $field));
                $col++;
            }
            $row++;
        }

        // Auto-size columns
        foreach (range(1, count($fields)) as $colIndex) {
            $sheet->getColumnDimensionByColumn($colIndex)->setAutoSize(true);
        }

        return $spreadsheet;
    }

    private function getFieldValue(mixed $product, string $field): mixed
    {
        // Custom fields from plugins (prefix: "custom:")
        if (str_starts_with($field, 'custom:')) {
            $key = substr($field, 7);
            $customFields = $product->getCustomFields() ?? [];
            $value = $customFields[$key] ?? '';
            return is_array($value) ? implode(', ', $value) : (string) $value;
        }

        return match ($field) {
            // Basic
            'name'            => (string) ($product->getName() ?? ''),
            'productNumber'   => (string) ($product->getProductNumber() ?? ''),
            'price'           => $this->formatPriceCollection($product->getPrice()),
            'purchasePrice'   => $this->formatPriceCollection($product->getPurchasePrices()),
            'stock'           => (int) $product->getStock(),
            'availableStock'  => (int) $product->getAvailableStock(),
            'active'          => $product->getActive() ? 'Yes' : 'No',
            // Identity
            'ean'             => (string) ($product->getEan() ?? ''),
            'releaseDate'     => $product->getReleaseDate()?->format('Y-m-d') ?? '',
            'markAsTopseller' => $product->getMarkAsTopseller() ? 'Yes' : 'No',
            'shippingFree'    => $product->getShippingFree() ? 'Yes' : 'No',
            // Relations
            'manufacturer'    => (string) ($product->getManufacturer()?->getName() ?? ''),
            'categories'      => $this->formatCollection($product->getCategories()),
            'tags'            => $this->formatCollection($product->getTags()),
            'tax'             => $product->getTax() !== null ? (float) $product->getTax()->getTaxRate() : '',
            'deliveryTime'    => (string) ($product->getDeliveryTime()?->getName() ?? ''),
            // Dimensions
            'weight'          => $product->getWeight() !== null ? (float) $product->getWeight() : '',
            'height'          => $product->getHeight() !== null ? (float) $product->getHeight() : '',
            'width'           => $product->getWidth() !== null ? (float) $product->getWidth() : '',
            'length'          => $product->getLength() !== null ? (float) $product->getLength() : '',
            // Purchase config
            'minPurchase'     => $product->getMinPurchase() !== null ? (int) $product->getMinPurchase() : '',
            'maxPurchase'     => $product->getMaxPurchase() !== null ? (int) $product->getMaxPurchase() : '',
            // Content
            'description'     => (string) strip_tags($product->getDescription() ?? ''),
            'properties'      => $this->formatProperties($product),
            default           => '',
        };
    }

    private function formatPriceCollection(mixed $prices): float|string
    {
        if (!$prices || $prices->count() === 0) {
            return '';
        }
        $first = $prices->first();

        return $first ? round($first->getGross(), 2) : '';
    }

    private function formatCollection(mixed $collection): string
    {
        if (!$collection) {
            return '';
        }
        $names = [];
        foreach ($collection->getElements() as $entity) {
            $name = $entity->getName();
            if ($name !== null && $name !== '') {
                $names[] = $name;
            }
        }

        return implode(', ', $names);
    }

    private function formatProperties(mixed $product): string
    {
        $properties = $product->getProperties();
        if (!$properties) {
            return '';
        }
        $parts = [];
        foreach ($properties->getElements() as $property) {
            $groupName = $property->getGroup()?->getName() ?? '';
            $valueName = $property->getName() ?? '';
            if ($groupName !== '' && $valueName !== '') {
                $parts[] = "{$groupName}: {$valueName}";
            }
        }

        return implode(', ', $parts);
    }

    private function getFieldLabels(): array
    {
        return [
            'name'            => 'Name',
            'productNumber'   => 'SKU',
            'price'           => 'Price (gross)',
            'purchasePrice'   => 'Purchase price',
            'stock'           => 'Stock',
            'availableStock'  => 'Available stock',
            'active'          => 'Active',
            'ean'             => 'EAN',
            'releaseDate'     => 'Release date',
            'markAsTopseller' => 'Topseller',
            'shippingFree'    => 'Shipping free',
            'manufacturer'    => 'Manufacturer',
            'categories'      => 'Categories',
            'tags'            => 'Tags',
            'tax'             => 'Tax rate (%)',
            'deliveryTime'    => 'Delivery time',
            'weight'          => 'Weight (kg)',
            'height'          => 'Height (cm)',
            'width'           => 'Width (cm)',
            'length'          => 'Length (cm)',
            'minPurchase'     => 'Min. purchase',
            'maxPurchase'     => 'Max. purchase',
            'description'     => 'Description',
            'properties'      => 'Properties',
        ];
    }
}
