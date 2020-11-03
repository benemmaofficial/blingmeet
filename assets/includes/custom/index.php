<?php

$actual_link = (isset($_SERVER['HTTPS']) ? "https" : "http") . "://".$_SERVER['HTTP_HOST'];	
header('Location: '.$actual_link);
return true;
