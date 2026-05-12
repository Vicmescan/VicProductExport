import enGB from './snippet/en-GB.json';
import deDE from './snippet/de-DE.json';
import esES from './snippet/es-ES.json';

import './page/vic-product-export';

Shopware.Locale.extend('en-GB', enGB);
Shopware.Locale.extend('de-DE', deDE);
Shopware.Locale.extend('es-ES', esES);

Shopware.Module.register('vic-product-export', {
    type: 'plugin',
    name: 'vic-product-export',
    title: 'vic-product-export.general.title',
    description: 'vic-product-export.general.description',
    color: '#2E7D32',
    icon: 'regular-file-download',

    routes: {
        index: {
            component: 'vic-product-export-page',
            path: 'index',
        },
    },

    navigation: [
        {
            label: 'vic-product-export.general.menuLabel',
            color: '#2E7D32',
            icon: 'regular-file-download',
            path: 'vic.product.export.index',
            parent: 'sw-catalogue',
            position: 100,
        },
    ],
});
