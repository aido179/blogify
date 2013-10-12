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
	$reply = "This video link has been archived [here](".$newURL.") as the next top video on ".$date." GMT.
	
I archive the best of /r/videos so you can catch up after a weekend, look back on them at a later date, or if reddit goes down.
[Full Archive](http://www.blogify.org) - [About the bot](http://blogify.org/?page_id=132) - [Github repo](https://github.com/aido179/blogify)

Some people downvote this bot, I'm sorry if I irritate you. Here is a [cat](".$catURL.").";
	
	
	//make reply
	$response = $reddit->addComment($threadName, $reply);
	file_put_contents("redditlog.txt",var_export($response, true));
}

?>