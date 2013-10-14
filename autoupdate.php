<?php
/* 
version 0.1.0 
*/

/*
*Blogify auto update script for www.reddit/r/videos
*
*---This script, part of the blogify system, gathers information from reddit about the r/videos subreddit
*   and grabs the first supported video post, checks whether it is embeddable, and if so, posts it to the 
*   blog. If a non video post is encountered, it moves on to the next post.
*
*---Only one post with one video is made at a time. 
*
*---To run the program in silent mode, so that it will not make any posts to twitter facebook or reddit, 
*	pass the first command line argument as "silent".
*	eg: --> php autoupdate.php silent
*
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

//setup facebook  details. Done here to prevent session problems. Don't echo before this
$config = array();
$config['appId'] = '403424519783309';
$config['secret'] = $cred->facebookSecret;;
$facebook = new Facebook($config);

//set up log file - write is done at the end of the script
$file = 'blogifybot.log';
$date = date("F j, Y, g:i a", strtotime('+1 hours'));
$log = "\n\n".$date."\n-------\n";

//add note to log if running in silent mode (argv[1] == "silent")
if(isset($argv[1])&&$argv[1]=="silent"){
	$log=$log."mode: silent\n";
	echo "\nSILENT MODE ACTIVE\n\n";
}
else{
	$log=$log."mode: public\n";
	echo "\nPUBLIC MODE ACTIVE\n\n";
}
		
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
	if ($domain == "youtube.com" || $domain == "youtu.be"){
		$pattern =
			'%^# Match any youtube URL
			(?:https?://)?  # Optional scheme. Either http or https
			(?:www\.)?      # Optional www subdomain
			(?:             # Group host alternatives
			  youtu\.be/    # Either youtu.be,
			| youtube\.com  # or youtube.com
			  (?:           # Group path alternatives
				/embed/     # Either /embed/
			  | /v/         # or /v/
			  | .*v=        # or /watch\?v=
			  )             # End path alternatives.
			)               # End host alternatives.
			([\w-]{10,12})  # Allow 10-12 for 11 char youtube id.
			($|&).*         # if additional parameters are also in query string after video id.
			$%x'
			;//thanks to http://stackoverflow.com/questions/6556559/youtube-api-extract-video-id
		$result = preg_match($pattern, $url, $matches);
		if (false !== $result) {
			$videoID = $matches[1];
		}
		else{
			$videoID = "No_videoID_Found";
		}
		$embedSRC = "//www.youtube.com/embed/".$videoID;
		//test if youtube video is embeddable - go to next link if not
		$string_youtube = file_get_contents("http://gdata.youtube.com/feeds/api/videos?v=2&alt=jsonc&q=".$videoID,0,null,null);
		$youtube_json = (object)json_decode($string_youtube, true); 
		$embedAllowed = $youtube_json->{'data'}['items']['0']['accessControl']['embed'];
		if(!$embedAllowed == "allowed"){
			$log=$log."id:not embeddable\n";
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
	$log=$log."id:".$videoID."\n";
	
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
	
	/*
	* Publishing block:
	* Make announcements to social networks and reddit if not in silent mode.
	*/
	
	if(!(isset($argv[1])&&$argv[1]=="silent")){
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
	}//end publishing block
	
	//don't make any more posts
	break;
}

// Write the log contents to the log file, 
file_put_contents($file, $log, FILE_APPEND | LOCK_EX);

?>