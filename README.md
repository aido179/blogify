Blogify Bot
=======

Blogify is a set of scripts that aims to automatically gather links posted to
 www.reddit.com/r/videos and archive them for later viewing, then share the blog 
 links to twitter, facebook and the reddit thread it got the video from.

Blogify was built partly for the experience, partly for fun, and partly because
 sometimes I miss the cool videos that made the top of the page if I don’t get to
 check reddit for a while.
 
I've made this repository public as an example for anybody wishing to make their own
reddit bot, and also to encourage the continued development of this bot by anybody 
interested.

Usage
=======

Please refrain from running your own version of this bot. We don't want to flood 
r/videos with bot spam. 

The files included in this repository should be put in a non-public directory on a
server and a cron tab file set up to run the bot every 6 hours or so. 

Cron
=======
The cron file is not included in this repository, but has the following format:

```
# 1 update blogify
0 0,6,12,18 * * * /web/cgi-bin/php5 "$HOME/html/autoupdate.php"
```

