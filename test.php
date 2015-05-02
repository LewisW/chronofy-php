<?php
include('vendor/autoload.php');

$chronofy = new \Vivait\Chronofy\Chronofy('MY_KEY_HERE');

$events = $chronofy->getEvents(new DateTime('today'), new DateTime('tomorrow'));

if (count($events['events'])) {
    var_dump($events['events'][0]);
}
