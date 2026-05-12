import template from './vic-product-export.html.twig';
import './vic-product-export.scss';

const { Component } = Shopware;

Component.register('vic-product-export-page', {
    template,

    inject: ['repositoryFactory'],

    data() {
        return {
            isLoading: false,
            isLoadingCustomFields: false,
            exportSuccess: false,
            customFieldSets: [],
            dragIndex: null,

            fieldGroups: [
                { key: 'basic',      labelKey: 'vic-product-export.groups.basic' },
                { key: 'identity',   labelKey: 'vic-product-export.groups.identity' },
                { key: 'relations',  labelKey: 'vic-product-export.groups.relations' },
                { key: 'dimensions', labelKey: 'vic-product-export.groups.dimensions' },
                { key: 'purchase',   labelKey: 'vic-product-export.groups.purchase' },
                { key: 'content',    labelKey: 'vic-product-export.groups.content' },
            ],

            availableFields: [
                { id: 'name',            labelKey: 'vic-product-export.fields.name',            group: 'basic' },
                { id: 'productNumber',   labelKey: 'vic-product-export.fields.productNumber',   group: 'basic' },
                { id: 'price',           labelKey: 'vic-product-export.fields.price',           group: 'basic' },
                { id: 'purchasePrice',   labelKey: 'vic-product-export.fields.purchasePrice',   group: 'basic' },
                { id: 'stock',           labelKey: 'vic-product-export.fields.stock',           group: 'basic' },
                { id: 'availableStock',  labelKey: 'vic-product-export.fields.availableStock',  group: 'basic' },
                { id: 'active',          labelKey: 'vic-product-export.fields.active',          group: 'basic' },
                { id: 'ean',             labelKey: 'vic-product-export.fields.ean',             group: 'identity' },
                { id: 'releaseDate',     labelKey: 'vic-product-export.fields.releaseDate',     group: 'identity' },
                { id: 'markAsTopseller', labelKey: 'vic-product-export.fields.markAsTopseller', group: 'identity' },
                { id: 'shippingFree',    labelKey: 'vic-product-export.fields.shippingFree',    group: 'identity' },
                { id: 'manufacturer',    labelKey: 'vic-product-export.fields.manufacturer',    group: 'relations' },
                { id: 'categories',      labelKey: 'vic-product-export.fields.categories',      group: 'relations' },
                { id: 'tags',            labelKey: 'vic-product-export.fields.tags',            group: 'relations' },
                { id: 'tax',             labelKey: 'vic-product-export.fields.tax',             group: 'relations' },
                { id: 'deliveryTime',    labelKey: 'vic-product-export.fields.deliveryTime',    group: 'relations' },
                { id: 'weight',          labelKey: 'vic-product-export.fields.weight',          group: 'dimensions' },
                { id: 'height',          labelKey: 'vic-product-export.fields.height',          group: 'dimensions' },
                { id: 'width',           labelKey: 'vic-product-export.fields.width',           group: 'dimensions' },
                { id: 'length',          labelKey: 'vic-product-export.fields.length',          group: 'dimensions' },
                { id: 'minPurchase',     labelKey: 'vic-product-export.fields.minPurchase',     group: 'purchase' },
                { id: 'maxPurchase',     labelKey: 'vic-product-export.fields.maxPurchase',     group: 'purchase' },
                { id: 'description',     labelKey: 'vic-product-export.fields.description',     group: 'content' },
                { id: 'properties',      labelKey: 'vic-product-export.fields.properties',      group: 'content' },
            ],

            selectedFields: ['name', 'productNumber', 'price', 'stock', 'active'],

            filters: {
                onlyActive: false,
                minStock: 0,
            },
        };
    },

    computed: {
        canExport() {
            return this.selectedFields.length > 0 && !this.isLoading;
        },

        fieldsInGroup() {
            return (groupKey) => this.availableFields.filter(f => f.group === groupKey);
        },

        // Map of fieldId -> display label (standard + custom fields)
        allFieldLabels() {
            const map = {};
            for (const f of this.availableFields) {
                map[f.id] = this.$tc(f.labelKey);
            }
            for (const set of this.customFieldSets) {
                for (const f of set.fields) {
                    map[`custom:${f.key}`] = f.label;
                }
            }
            return map;
        },
    },

    created() {
        this.loadCustomFields();
    },

    methods: {
        async loadCustomFields() {
            this.isLoadingCustomFields = true;
            try {
                const repo = this.repositoryFactory.create('custom_field_set');
                const criteria = new Shopware.Data.Criteria();
                criteria.addFilter(Shopware.Data.Criteria.equals('relations.entityName', 'product'));
                criteria.addAssociation('customFields');
                criteria.setLimit(100);

                const result = await repo.search(criteria, Shopware.Context.api);

                this.customFieldSets = result
                    .map(set => ({
                        id: set.id,
                        label: set.config?.label?.[Shopware.Context.app?.fallbackLocale]
                            || set.config?.label?.['en-GB']
                            || set.name,
                        fields: Object.values(set.customFields?.getElements() ?? {}).map(f => ({
                            key: f.name,
                            label: f.config?.label?.[Shopware.Context.app?.fallbackLocale]
                                || f.config?.label?.['en-GB']
                                || f.name,
                            type: f.type,
                        })),
                    }))
                    .filter(set => set.fields.length > 0);
            } catch (e) {
                // Custom fields section won't display — non-critical
            } finally {
                this.isLoadingCustomFields = false;
            }
        },

        isFieldSelected(fieldId) {
            return this.selectedFields.includes(fieldId);
        },

        onFieldChange(fieldId, checked) {
            if (checked && !this.selectedFields.includes(fieldId)) {
                this.selectedFields.push(fieldId);
            } else if (!checked) {
                this.selectedFields = this.selectedFields.filter(f => f !== fieldId);
            }
        },

        selectAll() {
            const allIds = [
                ...this.availableFields.map(f => f.id),
                ...this.customFieldSets.flatMap(s => s.fields.map(f => `custom:${f.key}`)),
            ];
            this.selectedFields = allIds;
        },

        selectNone() {
            this.selectedFields = [];
        },

        getFieldLabel(fieldId) {
            return this.allFieldLabels[fieldId] || fieldId;
        },

        // Drag-and-drop reordering
        onDragStart(index) {
            this.dragIndex = index;
        },

        onDragOver(event, index) {
            event.preventDefault();
            if (this.dragIndex === null || this.dragIndex === index) return;
            const updated = [...this.selectedFields];
            const [moved] = updated.splice(this.dragIndex, 1);
            updated.splice(index, 0, moved);
            this.selectedFields = updated;
            this.dragIndex = index;
        },

        onDragEnd() {
            this.dragIndex = null;
        },

        async onExport() {
            if (!this.canExport) return;
            this.isLoading = true;
            this.exportSuccess = false;

            try {
                const token = Shopware.Context.api.authToken?.access;

                // Send custom field labels so the backend can use them as column headers
                const fieldLabels = {};
                for (const fieldId of this.selectedFields) {
                    if (fieldId.startsWith('custom:')) {
                        fieldLabels[fieldId] = this.allFieldLabels[fieldId] || fieldId;
                    }
                }

                const response = await fetch('/api/_action/vic-product-export/export', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': `Bearer ${token}`,
                    },
                    body: JSON.stringify({
                        fields: this.selectedFields,
                        fieldLabels,
                        filters: this.filters,
                    }),
                });

                if (!response.ok) {
                    throw new Error(`Server error: ${response.status}`);
                }

                const blob = await response.blob();
                const url = URL.createObjectURL(blob);
                const date = new Date().toISOString().slice(0, 10);
                const link = document.createElement('a');
                link.href = url;
                link.download = `products_${date}.xlsx`;
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                URL.revokeObjectURL(url);

                this.exportSuccess = true;
            } catch (error) {
                console.error('[VicProductExport] Export failed:', error);
            } finally {
                this.isLoading = false;
            }
        },
    },
});
