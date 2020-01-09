<?php

require_once('lib/ImageResizer.php');

$file='4.jpg'; /* test */

$img=new ImageResizer($file);
$img->path_file="./files/images/thumbs/";
$img->name_file=time().".jpg";
//$img->setModeGif(true);
$img->setMode('crop_resize');
$img->setCropMode(4);
$img->setSize(150,150);
$img->getThumbSrc(); 