<?php

return [
    'navigation' => [
        'title' => 'Bills of Materials',
    ],

    'form' => [
        'sections' => [
            'general' => [
                'title'  => 'General',
                'fields' => [
                    'reference'             => 'Reference',
                    'reference-placeholder' => 'eg. BOM-001',
                    'product'               => 'Product',
                    'quantity'              => 'Quantity',
                    'uom'                   => 'UOM',
                    'operation-type'        => 'Operation Type',
                    'company'               => 'Company',
                ],
            ],
            'settings' => [
                'title'  => 'Settings',
                'fields' => [
                    'type'                   => 'BOM Type',
                    'ready-to-produce'       => 'Ready to Produce',
                    'flexible-consumption'   => 'Flexible Consumption',
                    'operation-dependencies' => 'Operation Dependencies',
                ],
            ],
        ],
    ],

    'table' => [
        'columns' => [
            'reference'  => 'Reference',
            'product'    => 'Product',
            'quantity'   => 'Quantity',
            'uom'        => 'UOM',
            'type'       => 'BOM Type',
            'company'    => 'Company',
            'deleted-at' => 'Deleted At',
            'updated-at' => 'Updated At',
        ],
        'filters' => [
            'product' => 'Product',
            'type'    => 'BOM Type',
            'company' => 'Company',
        ],
        'actions' => [
            'restore' => [
                'notification' => [
                    'title' => 'Bill of material restored',
                    'body'  => 'The bill of material has been restored successfully.',
                ],
            ],
            'delete' => [
                'notification' => [
                    'title' => 'Bill of material archived',
                    'body'  => 'The bill of material has been archived successfully.',
                ],
            ],
            'force-delete' => [
                'notification' => [
                    'success' => [
                        'title' => 'Bill of material deleted',
                        'body'  => 'The bill of material has been permanently deleted.',
                    ],
                    'error' => [
                        'title' => 'Bill of material could not be deleted',
                        'body'  => 'The bill of material cannot be deleted because it is currently in use.',
                    ],
                ],
            ],
        ],
        'bulk-actions' => [
            'restore' => [
                'notification' => [
                    'title' => 'Bills of material restored',
                    'body'  => 'The selected bills of material have been restored successfully.',
                ],
            ],
            'delete' => [
                'notification' => [
                    'title' => 'Bills of material archived',
                    'body'  => 'The selected bills of material have been archived successfully.',
                ],
            ],
            'force-delete' => [
                'notification' => [
                    'success' => [
                        'title' => 'Bills of material deleted',
                        'body'  => 'The selected bills of material have been permanently deleted.',
                    ],
                    'error' => [
                        'title' => 'Bills of material could not be deleted',
                        'body'  => 'One or more selected bills of material are currently in use.',
                    ],
                ],
            ],
        ],
    ],

    'infolist' => [
        'sections' => [
            'general' => [
                'title'   => 'General Information',
                'entries' => [
                    'reference'      => 'Reference',
                    'product'        => 'Product',
                    'quantity'       => 'Quantity',
                    'uom'            => 'UOM',
                    'operation-type' => 'Operation Type',
                    'company'        => 'Company',
                ],
            ],
            'settings' => [
                'title'   => 'Settings',
                'entries' => [
                    'type'                   => 'BOM Type',
                    'ready-to-produce'       => 'Ready to Produce',
                    'flexible-consumption'   => 'Flexible Consumption',
                    'operation-dependencies' => 'Operation Dependencies',
                ],
            ],
            'record-information' => [
                'title'   => 'Record Information',
                'entries' => [
                    'created-by'   => 'Created By',
                    'created-at'   => 'Created At',
                    'last-updated' => 'Last Updated',
                ],
            ],
        ],
    ],
];
