<?php

error_reporting(E_ALL);
ini_set('display_errors', '1');

include_once __DIR__ . '/services/DomParser.php';
include_once __DIR__ . '/pars.php';

$parser = DomParser::init($elements, DomParser::MODE_GET_ALL_BETWEEN_ELEMENTS)
  ->whereAttributeFrom('href', 1)
  ->whereAttributeTo('id', 1)
  ->run();

echo "<pre>";
print_r($parser->getResult());
echo "</pre>";

?>
