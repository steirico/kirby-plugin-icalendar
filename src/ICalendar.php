<?php

namespace steirico\kICalendar;

use Kirby\Cms\Page;
use Kirby\Cms\Pages;
use Kirby\Cms\Response;

use Eluceo\iCal\Component\Calendar;
use Eluceo\iCal\Component\Event;

class ICalendar {

    const OPTIONS = "steirico.kirby-plugin-icalendar.";
    private $defaultOptions;
    private $optionsCache;
    private $ignoreList;

    public function __construct() {
        $this->defaultOptions = option(self::OPTIONS . "plugin.defaults");
        $this->ignoreList = option(self::OPTIONS . "plugin.ignore");
        $this->optionsCache = array();
    }

    private function resolvedTemplateOptions($template) {
        if(array_key_exists($template, $this->optionsCache)){
            return $this->optionsCache[$template];
        }

        $templateOptions = option(self::OPTIONS . $template);
        if (is_array($templateOptions)) {
            $templateOptions = array_merge($this->defaultOptions, $templateOptions);
        } else {
            $templateOptions = $this->defaultOptions;
        }

        $this->optionsCache[$template] = $templateOptions;
        return $templateOptions;
    }

    private function ignore($page) {
        if (array_key_exists($page->id(), $this->ignoreList['page'])){
            return true;
        }

        if (array_key_exists($page->intendedTemplate()->name(), $this->ignoreList['template'])){
            return true;
        }

        return false;
    }


    private function pages(Page $page, $depth = -1, $maxDepth = -1): Pages {
        $pages = new Pages();
        $depth++;

        $template = $page->intendedTemplate()->name();
        $options = $this->resolvedTemplateOptions($template);

        $subPages = $options["pages"]($page);

        if((($subPages->count() == 0) || ($maxDepth == $depth)) && (!$this->ignore($page))) {
            $pages->add($page);
        } else {
            foreach ($subPages as $subPage){
                $toAdd = $this->pages($subPage, $depth, $maxDepth);
                if($toAdd->count() != 0) {
                    $pages->add($toAdd);
                }
            }
        }

        return $pages;
    }

    public function render(string $pageId): string {
        $page = site()->page($pageId);
        if($page){
            $pageTemplate = $page->intendedTemplate()->name();
            $pageOptions = $this->resolvedTemplateOptions($pageTemplate);

            $vCalendar = new Calendar($page->slug());

            if($field = $page->{$pageOptions["calendarName"]}()) {
                $vCalendar->setName($field->value());
            }
            if($field = $page->{$pageOptions["calendarDescription"]}()) {
                $vCalendar->setDescription($field->value());
            }

            $pages = $this->pages($page);

            foreach($pages as $eventPage) {
                $template = $eventPage->intendedTemplate()->name();
                $options = $this->resolvedTemplateOptions($template);
                $timezone = new \DateTimeZone($options['timezone']);

                $vEvent = new Event();
                if($field = $eventPage->{$options["summary"]}()) {
                    $vEvent->setSummary($field->value());
                }
                if($field = $eventPage->{$options["start"]}()) {
                    $date = $field->toDate() . " " . $options['timezone'];
                    $vEvent->setDtStart(\DateTime::createFromFormat("U T", $date, $timezone));
                }
                if($field = $eventPage->{$options["end"]}()) {
                    $date = $field->toDate() . " " . $options['timezone'];
                    $vEvent->setDtEnd(\DateTime::createFromFormat("U T", $date, $timezone));

                }
                if($field = $eventPage->{$options["description"]}()) {
                    $vEvent->setDescription($field->value());
                }

                //$vEvent->setLocation($eventPage->{$options["defaultLocation"]}()->value());
                //$vEvent->setGeoLocation($eventPage->{$options["geo"]}()->value());

                $vCalendar->addComponent($vEvent);
            }

            $headers = [
                'Content-Disposition: attachment; filename="' . $page->slug() .'.ics"'
            ];
            $response = new Response($vCalendar->render(), 'text/calendar', 200, $headers, 'utf-8');

            return $response;
        } else {
            return '';
        }
    }
}
