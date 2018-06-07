<?php

/**
 * Example file on how to use the System library
 */

include_once "src/View.php";


\LabCake\View::SetOption("root", dirname(__FILE__) ."/");
\LabCake\View::SetVariable("testString", "Hello World");


$view = new \LabCake\View();
$view->Set("hello", "123");
$view->Display("examples/example1.php");