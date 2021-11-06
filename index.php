<?php

@include_once __DIR__ . '/vendor/autoload.php';

Kirby::plugin('steirico/kirby-plugin-icalendar', [
    'options' => [
        'plugin-defaults' => [
            'start' => 'page.startDate.toDate("Y-m-d H:i T")',
            'end' => 'page.endDate.toDate("Y-m-d H:i T")',
            'summary' => 'page.title',
            'description' => 'page.description',
            'location' => '',
            'geo' => '',
            'url' => '{{page.url}}',
            'maxDepth' => 0,
            'pages' => 'page.children.listed',
            'timezone' => 'Europe/Zurich',
            'calendarName' => 'page.title',
            'calendarDescription' => 'page.description'
        ],
        'plugin-include' => [
            'page' => [
                '*' => false
            ],
            'template' => [
                '*' => true
            ]
        ]
    ],
    'routes' => [
        [
            'pattern' => [
                '(:all).ics',
                '(:all)/ics'
            ],
            'method' => 'GET',
            'action'  => function (string $id = '') {
                return (new steirico\kICalendar\ICalendar())->render($id);
            }
        ]
    ]
]);

