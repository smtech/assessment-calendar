<?php

if (isset($_SESSION['toolProvider'])) {
	$_SESSION['canvasInstanceUrl'] = 'https://' . $_SESSION['toolProvider']->user->getResourceLink()->settings['custom_canvas_api_domain'];
}	
$api = new CanvasPest($_SESSION['apiUrl'], $_SESSION['apiToken']);

?>