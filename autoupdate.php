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

//get reddit info
$string_reddit = file_get_contents("http://reddit.com/r/videos.json");
$reddit_json = json_decode($string_reddit, true);  
$reddit_children = $reddit_json['data']['children'];
//loop through each link
foreach ($reddit_children as $reddit_child){
    $title = $reddit_child['data']['title'];
	$domain = $reddit_child['data']['domain'];
	$redditThreadName = $reddit_child['data']['name'];
	
	//skip self posts
	if ($domain == "self.videos") continue;
	
	//get the video url and the youtube id 
	$url = $reddit_child['data']['url'];
	parse_str( parse_url( $url, PHP_URL_QUERY ), $my_array_of_vars );
	$videoID = $my_array_of_vars['v']; 
	
	//get youtube info 
	$string_youtube = file_get_contents("http://gdata.youtube.com/feeds/api/videos?v=2&alt=jsonc&q=".$videoID,0,null,null);
	$youtube_json = (object)json_decode($string_youtube, true); 
	
	
	
	//build post body using iframe plugin to avoid missing iframe bug
	$content = '<p style="text-align: center;">[iframe src="//www.youtube.com/embed/'.$videoID.'" width="100%"]</p><Br>Check out the original thread <a href="http://www.reddit.com/r/videos/comments/'.$redditThreadName.'" title="Go to reddit comments">here</a>. ';
	
	//test if embeddable
	$embedAllowed = $youtube_json->{'data'}['items']['0']['accessControl']['embed'];
	if($embedAllowed == "allowed"){
		//test if the same video has been posted recently
		$args = array( 'numberposts' => '15' );
		$recent_posts = wp_get_recent_posts( $args );
		echo "testing\n";
		foreach( $recent_posts as $recent ){
			//echo $recent["post_title"] ." == ".$title."\n";
			if (substr($recent["post_title"], 0, strlen($title)) == $title)
			{
				continue 2;
			}
		}
		
		//make the post
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
		
		//post to twitter
		
		$twitter = new Twitter($consumerKey, $consumerSecret, $accessToken, $accessTokenSecret);
		try {
			$tweet = $twitter->send($title.' http://blogify.org/?p='.$post_id);
			echo ".\n\ntweet sent.";
		} catch (TwitterException $e) {
			echo '.\n\nError: ' . $e->getMessage();
		}
		
		//make reddit post
		makeRedditComment($redditThreadName,  'http://blogify.org/?p='.$post_id);
		
		//don't make any more posts
		break;
	}
}
/*
Copyright (c) 2013 Aidan Breen

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in
all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
THE SOFTWARE.
*/

?>