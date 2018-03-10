# stdinho


Every once in a while, you need to share with somebody stuff you don't currently have at hand.
So, what do you do?

...

Unless it's just a matter of sending a link, you fetch it for them. 
This involves **waiting** until your PC finishes downloading/processing.
Once you have fetched it, you send it to person who needs it. Here's it visualized:

![ineffective queue](https://user-images.githubusercontent.com/496233/37237654-bef34336-2416-11e8-9530-24b78fc95c62.gif)

I was tired of waiting.

![stream as you go](https://user-images.githubusercontent.com/496233/37237657-d7a6d65e-2416-11e8-916a-c1222e7e6145.gif)


This tool skips the waiting part, by joining fetching/processing with sending. 
You can pipe any standard stream into this tool and it immediately makes
it available via HTTP, as it goes in. You send a link to people who need the stuff. 
People click the link and immediately start downloading.

You guys like screenshots? Here is it in action:


![stdinho](https://user-images.githubusercontent.com/496233/37237663-e240866e-2416-11e8-9d03-386ae3790d1c.png)

## Install

Via [Composer](https://getcomposer.org/doc/00-intro.md)

```bash
composer global require ostrolucky/stdinho
```

## Features

* Stdin. Most universal input.
* HTTP. Most universal network output.
* Async = non-blocking. Yes, in PHP.
* Buffers to temp directory. Automatically gets rid of the stream on close.
* Detects MIME type and attaches it to HTTP response automatically. Streaming video? Browser detects it and plays it immediately.
* Shows detailed progress of stdin stream and progress of downloading by clients

## Configuration

Not much configuration so far. 
You can watch all available options by running
```bash
$ stdinho --help
``` 

Will add:

* Option to save the stream into file

## Example usage/Showcase
Simple
```bash
# You: Share a movie on your disk
$ stdinho < /file/to/a/movie.mp4
# Your friend: Watch it, with no full download required
$ firefox http://127.0.0.1:1337

# You: Share application logs in realtime
$ tail -f project/var/log/*.log|stdinho
# Your colleague: View them
$ curl 127.0.0.1:1337 

# You: Zip a folder and share it
$ zip -r - project|stdinho
# Somebody else: Save a zip
$ curl 127.0.0.1:1337 -o project.zip
```

Advanced
```bash
# You: Connect via SSH to a server, inside of it connect to MySQL server, retrieve database dump, gzip it, retrieve it
$ ssh admin@example.com "mysqldump -u root -ptoor database|gzip -c"|stdinho
# Your colleague: Retrieve the dump, extract and import directly to local dev database
$ curl 127.0.0.1:1337|gunzip|mysql -u root -ptoor database
```


## Licensing

GPLv3 license. Please see [License File](LICENSE.md) for more information.
