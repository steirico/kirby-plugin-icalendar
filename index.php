<?php

@include_once __DIR__ . '/vendor/autoload.php';

Kirby::plugin('steirico/kirby-plugin-icalendar', [
    'options' => [
        'plugin-defaults' => [
            'start' => 'page.startDate',
            'end' => 'page.endDate',
            'summary' => 'page.title',
            'description' => 'page.description',
            'location' => '',
            'geo' => '',
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

