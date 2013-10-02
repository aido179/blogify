<?php
require_once("reddit.php");
require_once("credentials.php");

function makeRedditComment($threadName, $newURL){

	//sign in
	$reddit = new reddit($redditUsername, $redditPassword);
	
	//get date string. Offset for DST
	$date = date("F j, Y, g:i a", strtotime('+1 hours'));
	
	//build reply
	$reply = "Hi, This video has been archived [here](".$newURL.") as the next top video on ".$date." GMT.  
I'm a bot attempting to archive /r/videos so you can look back on them at a later date. The full archive can be found [here](http://www.blogify.org). More information about me can be found [here.](http://blogify.org/?page_id=132) ^(I'm open source too!)";
	
	//make reply
	$response = $reddit->addComment($threadName, $reply);
}

?>