<?php

namespace steirico\kICalendar;

use Kirby\Cms\Page;
use Kirby\Cms\Pages;
use Kirby\Cms\Response;
use Kirby\Toolkit\Str;

use Eluceo\iCal\Component\Calendar;
use Eluceo\iCal\Component\Event;
use Eluceo\iCal\Property\Event\Geo;

class ICalendar {

    const OPTIONS = "steirico.kirby-plugin-icalendar.";
    private $defaultOptions;
    private $optionsCache;
    private $includeList;

    public function __construct() {
        $this->defaultOptions = option(self::OPTIONS . "plugin-defaults");
        $this->includeList = option(self::OPTIONS . "plugin-include");
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

    private function includePage($page) {
        $pageId = $page->id();
        $templateName = $page->intendedTemplate()->name();

        if (array_key_exists($pageId, $this->includeList['page'])){
            return $this->includeList['page'][$pageId];
        }

        if (array_key_exists($templateName, $this->includeList['template'])){
            return $this->includeList['template'][$templateName];
        }
        

        if (array_key_exists('*', $this->includeList['page'])){
            if ($this->includeList['page']['*']) {
                return true;
            }
        }

        if (array_key_exists('*', $this->includeList['template'])){
            return $this->includeList['template']['*'];
        }

        return true;
    }


    private function pages(Page $page, $pageData, $depth = -1, $maxDepth = -1): Pages {
        $pages = new Pages();
        $depth++;

        if($maxDepth == $depth) {
            if($this->includePage($page)) {
                $pages->add($page);
            }

            return $pages;
        }

        $template = $page->intendedTemplate()->name();
        $options = $this->resolvedTemplateOptions($template);

        $subPages = Str::query($options['pages'], $pageData);

        if(is_a($subPages, "Kirby\Cms\Pages")) {
            if($subPages->count() == 0 && $this->includePage($page)){
                $pages->add($page);
            } else {
                foreach ($subPages as $subPage){
                    $pageData['page'] = $subPage;
                    $toAdd = $this->pages($subPage, $pageData, $depth, $maxDepth);
                    if($toAdd->count() != 0) {
                        $pages->add($toAdd);
                    }
                }
            }
        } else if($this->includePage($page)) {
            $pages->add($page);
        }

        return $pages;
    }

    private function evaluateProperty($property, $pageOptions, $pageData) {
        $ql = $pageOptions[$property];
        if(strpos($ql, '{{') !== false && strpos($ql, '}}') !== false) {
            $p = Str::template($pageOptions[$property], $pageData);
        } else {
            $p = Str::query($pageOptions[$property], $pageData);
        }

        if(is_string($p)) {
            return $p;
        } else if(is_a($p, "Kirby\Cms\Field")) {
            return $p->value();
        } else {
            return false;
        }
    }

    public function render(string $pageId): string {
        $page = site()->page($pageId);
        if($page){
            $pageTemplate = $page->intendedTemplate()->name();
            $pageOptions = $this->resolvedTemplateOptions($pageTemplate);

            $vCalendar = new Calendar($page->slug());
            $kirby = kirby();
            $pageData = [
                'kirby' => $kirby,
                'site'  => site(),
                'page'  => $page,
                'users' => $kirby->users(),
                'user'  => $kirby->user()
            ];

            $p = $this->evaluateProperty("calendarName", $pageOptions, $pageData);
            if($p !== false) {
                $vCalendar->setName($p);
            }
            $p = $this->evaluateProperty("calendarDescription", $pageOptions, $pageData);
            if($p !== false) {
                $vCalendar->setDescription($p);
            }

            $pages = $this->pages($page, $pageData, 0, $pageOptions['maxDepth']);

            foreach($pages as $eventPage) {
                $template = $eventPage->intendedTemplate()->name();
                $options = $this->resolvedTemplateOptions($template);
                $timezone = new \DateTimeZone($options['timezone']);

                $pageData['page'] = $eventPage;

                $vEvent = new Event();

                $p = $this->evaluateProperty("summary", $pageOptions, $pageData);
                if($p !== false) {
                    $vEvent->setSummary($p);
                }

                $p = $this->evaluateProperty("start", $pageOptions, $pageData);
                if($p !== false) {
                    $date = $p . " " . $options['timezone'];
                    $vEvent->setDtStart(\DateTime::createFromFormat("Y-m-d H:i T", $date, $timezone));
                }

                $p = $this->evaluateProperty("end", $pageOptions, $pageData);
                if($p !== false) {
                    $date = $p . " " . $options['timezone'];
                    $vEvent->setDtEnd(\DateTime::createFromFormat("Y-m-d H:i T", $date, $timezone));

                }

                $p = $this->evaluateProperty("description", $pageOptions, $pageData);
                if($p !== false) {
                    $vEvent->setDescription($p);
                }

                $p = $this->evaluateProperty("location", $pageOptions, $pageData);
                if($p !== false) {
                    $vEvent->setLocation($p);
                }

                $p = $this->evaluateProperty("geo", $pageOptions, $pageData);
                if($p !== false) {
                    $parts = explode(';', $p);
                    $geo = new Geo((float) $parts[0], (float) $parts[1]);
                    $vEvent->setGeoLocation($geo);
                }

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
