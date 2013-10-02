<?php
require_once("reddit.php");
require_once("credentials.php");

function makeRedditComment($threadName, $newURL){
	$cred = new credentials();
	//sign in
	$reddit = new reddit($cred->redditUsername, $cred->redditPassword);
	
	//get date string. Offset for DST
	$date = date("F j, Y, g:i a", strtotime('+1 hours'));
	
	//get a cat
	$data =  file_get_contents("http://thecatapi.com/api/images/get?format=xml");
	$p = xml_parser_create();
	xml_parse_into_struct($p, $data, $vals);
	$catURL = $vals[4]['value'];
		
	//build reply
	$reply = "Hi, This video has been archived [here](".$newURL.") as the next top video on ".$date." GMT.
	
I'm a bot attempting to archive /r/videos so you can look back on them at a later date.  
The full archive can be found [here](http://www.blogify.org).  
More information about me can be found [here.](http://blogify.org/?page_id=132).  
A picture of a cat can be found [here.](".$catURL.")  
I'm open source, and my repository can be found [here.](https://github.com/aido179/blogify)";
	
	
	//make reply
	$response = $reddit->addComment($threadName, $reply);
	file_put_contents("redditlog.txt",var_export($response, true));
	echo "\nreddit post made.";
}

?>