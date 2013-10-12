<?php
/* 
version 0.1.0 
*/

/*
*Blogify auto update script for www.reddit/r/videos
*
*---This script, part of the blogify system, gathers information from reddit about the r/videos subreddit
*   and grabs the first youtube video post, checks whether it is embeddable, and if so, posts it to the 
*   blog. If a non video post, or non youtube post is encountered, it moves on to the next post.
*---Only one post with one video is made at a time. 
*---This file should reside in a non-publicly accessible directory, in the case of blogify, the directory
*   directly above the blogify directory. It should be run with cron on a regular basis. 
*
*Pre-requisites:
*--- Wordpress installed in the root directory of the domain. ie, ../blogify
*--- cron to run the script
*/
include_once("blogify/wp-config.php");
include_once("twitter.class.php");
include_once("redditbot.php");
include_once("credentials.php");
require_once("fb/facebook.php");

//create credentials object
//credentials object is used to abstract sensitive data that might otherwise be shared through git
$cred = new credentials();

//set up log file - write is done at the end of the file
$file = 'blogifybot.log';
$date = date("F j, Y, g:i a", strtotime('+1 hours'));
$log = "\n\n".$date."\n-------\n";


//setup facebook  details. Done here to prevent session problems
$config = array();
$config['appId'] = '403424519783309';
$config['secret'] = $cred->facebookSecret;;
$facebook = new Facebook($config);
		
//get reddit info
$string_reddit = file_get_contents("http://reddit.com/r/videos.json");
$reddit_json = json_decode($string_reddit, true);  
$reddit_children = $reddit_json['data']['children'];
//loop through each link
foreach ($reddit_children as $reddit_child){
    $title = $reddit_child['data']['title'];
	$domain = $reddit_child['data']['domain'];
	$redditThreadName = $reddit_child['data']['name'];
	$redditThreadURL = $reddit_child['data']['permalink'];
	$url = $reddit_child['data']['url'];
	
	//skip self posts
	if ($domain == "self.videos") continue;
	$log=$log."domain:".$domain."\n";
	
	//get the video id and set embed src based on domain of link
	if ($domain == "youtube.com"){
		parse_str( parse_url( $url, PHP_URL_QUERY ), $my_array_of_vars );
		$videoID = $my_array_of_vars['v']; 
		$embedSRC = "//www.youtube.com/embed/".$videoID;
		//test if youtube video is embeddable - go to next link if not
		$string_youtube = file_get_contents("http://gdata.youtube.com/feeds/api/videos?v=2&alt=jsonc&q=".$videoID,0,null,null);
		$youtube_json = (object)json_decode($string_youtube, true); 
		$embedAllowed = $youtube_json->{'data'}['items']['0']['accessControl']['embed'];
		if(!$embedAllowed == "allowed"){
			continue;
		}
	}
	else if ($domain == "youtu.be"){
		$arr = parse_url($url);
		$videoID = ltrim($arr['path'],"/");
		$embedSRC = "//www.youtube.com/embed/".$videoID;
		//test if youtube video is embeddable - go to next link if not
		$string_youtube = file_get_contents("http://gdata.youtube.com/feeds/api/videos?v=2&alt=jsonc&q=".$videoID,0,null,null);
		$youtube_json = (object)json_decode($string_youtube, true); 
		$embedAllowed = $youtube_json->{'data'}['items']['0']['accessControl']['embed'];
		if(!$embedAllowed == "allowed"){
			continue;
		}
	}
	else if ($domain == "vimeo.com"){
		$arr = parse_url($url);
		$videoID = ltrim($arr['path'],"/");
		$embedSRC = "//player.vimeo.com/video/".$videoID;
	}
	else if ($domain == "liveleak.com"){
		$arr = parse_url($url);
		$videoID = ltrim($arr['i'],"/");
		$embedSRC = "http://www.liveleak.com/ll_embed?f=".$videoID;
	}
	
	//test if this video has been posted recently - go to next link if it has
	$args = array( 'numberposts' => '15' );
	$recent_posts = wp_get_recent_posts( $args );
	echo "testing\n";
	foreach( $recent_posts as $recent ){
		if (substr($recent["post_title"], 0, strlen($title)) == $title)
		{
			continue 2;
		}
	}		
	
	//build post body using iframe plugin to avoid missing iframe bug
	$content = '<p style="text-align: center;">[iframe src="'.$embedSRC.'" width="100%"]</p><Br>Check out the original thread <a href="http://www.reddit.com/'.$redditThreadURL.'" title="Go to reddit comments">here</a>. ';

	//make wordpress post
	global $user_ID;
	$new_post = array(
	'post_title' => $title." [via reddit]",
	'post_content' => $content,
	'post_status' => 'publish',
	'post_date' => date('Y-m-d H:i:s'),
	'post_author' => $user_ID,
	'post_type' => 'post',
	'post_category' => array(0)
	);
	
	//output for command line debug, no need to remove for production
	echo $post_id = wp_insert_post($new_post);
	echo " - Post made\n Title: ".$title."\nContent: ".$content;
	
	//post to facebook
	try{
		/*
		This section is failing due to access_tokens expiring. 
		*/
		$facebook->api('/blogifyorg/links', 'POST',
							array(
							  'link' => ' http://blogify.org/?p='.$post_id,
							  'message' => $title,
							  'access_token' => $cred->facebookAccessToken
						 ));
		echo "\nFacebook post made.\n";
	}
	catch (Exception $e){
		echo "\nFacebook exception.".$e->getMessage()."\n";
	}
	
	//post to twitter
	$twitter = new Twitter($cred->consumerKey, $cred->consumerSecret, $cred->accessToken, $cred->accessTokenSecret);
	try {
		$tweet = $twitter->send($title.' http://blogify.org/?p='.$post_id);
		echo ".\ntweet sent.\n";
	} catch (TwitterException $e) {
		echo '.\nError: ' . $e->getMessage()."\n";
	}
	
	//make reddit post
	makeRedditComment($redditThreadName,  'http://blogify.org/?p='.$post_id);
	echo "\nReddit comment made.\n";
	//don't make any more posts
	break;
}

// Write the log contents to the log file, 
// using the FILE_APPEND flag to append the content to the end of the file
// and the LOCK_EX flag to prevent anyone else writing to the file at the same time
file_put_contents($file, $log, FILE_APPEND | LOCK_EX);

?>