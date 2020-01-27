<?php

@include_once __DIR__ . '/vendor/autoload.php';

Kirby::plugin('steirico/kirby-plugin-icalendar', [
    'options' => [
        'plugin.defaults' => [
            'start' => 'startDate',
            'end' => 'endDate',
            'summary' => 'title',
            'description' => 'description',
            'location' => '',
            'geo' => '',
            'maxDepth' => 0,
            'pages' => function($page) {
                return $page->children()->listed();
            },
            'timezone' => 'Europe/Zurich',
            'calendarName' => 'title',
            'calendarDescription' => 'description'
        ],
        'plugin.ignore' => [
            'page' => [],
            'template' => []
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
