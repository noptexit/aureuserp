<?php

return [
    'label' => 'Produce All',

    'modal' => [
        'consumption-warning' => [
            'heading'     => 'Immediate Transfer?',
            'description' => 'Some products are not fully consumed. Do you want to validate the manufacturing order with the current quantities?',

            'form' => [
                'product'    => 'Product',
                'to-consume' => 'To Consume',
                'consumed'   => 'Consumed',
                'uom'        => 'Unit of Measure',
            ],

            'actions' => [
                'validate' => [
                    'label' => 'Validate',
                ],

                'set-quantities' => [
                    'label' => 'Set Quantities',
                ],
            ],
        ],
    ],

    'notification' => [
        'success' => [
            'title' => 'Manufacturing order completed',
            'body'  => 'The manufacturing order has been completed successfully.',
        ],
    ],
];
