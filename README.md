# stdinho


Every once in a while, you need to share with somebody stuff you don't currently have at hand.
So, what do you do?

...

Unless it's just a matter of sending a link, you fetch it for them. 
This involves **waiting** until your PC finishes downloading/processing.
Once you have fetched it, you send it to person who needs it. 
Also, sending it sometimes means to first upload it somewhere, so other person can download it from there.
This uploading involves again unnecessary **waiting**. I was tired of these waitings...

Here's this pain and its removal visualized:

![stdinho animation](https://user-images.githubusercontent.com/496233/47866950-750db900-de00-11e8-8631-d25d723128f5.gif)


This tool skips the waiting part, by merging fetching + processing + sending. 
You can pipe any standard stream into this tool and it immediately makes
it available via HTTP, as it goes in.

Here is it in action:


![stdinho](https://user-images.githubusercontent.com/496233/37237663-e240866e-2416-11e8-9d03-386ae3790d1c.png)

## Install

Via [Composer](https://getcomposer.org/doc/00-intro.md)

```bash
composer global require ostrolucky/stdinho
```

## Features

* Stdin. Most universal input.
* HTTP. Most universal network output.
* Async = not being limited to just one HTTP client.
* Cross-platform. Linux/MacOS/Windows.
* Buffers to temp before client is connected, so no time between is wasted
* Shows detailed progress of stdin stream and progress of downloading by clients
* Detects MIME type and attaches it to HTTP response automatically. Streaming video? Browser detects it and plays it immediately.

## Configuration

Just supply stdin or provide file via `--file` option to stdinho and that's all. 
There is option to specify IP/Port on which stdinho should listen at as well.

`--file` acts as a [tee](https://en.wikipedia.org/wiki/Tee_(command)) if both stdin and --file is provided 

You can watch all available options by running. 
```bash
$ stdinho --help
``` 

## Example use cases
<details><summary>Video streaming</summary>
<p>

```bash
# Server
$ stdinho 0.0.0.0:1337 < /file/to/a/movie.mp4
# Client
$ firefox http://127.0.0.1:1337
```

</p>
</details>
<details><summary>Simple one-way chat</summary>
<p>

```bash
# Server
# Server (broadcaster)
$ { while read a; do echo $a; done }|bin/stdinho 127.0.0.1:1337
# Client
curl 127.0.0.1:1337
```

</p>
</details>
<details><summary>Share application logs in realtime</summary>
<p>

```bash
# Server
$ tail -f project/var/log/*.log|stdinho 0.0.0.0:1337
# Client
$ curl 127.0.0.1:1337 
```

</p>
</details>
<details><summary>Stream a folder, including compression</summary>
<p>

```bash
# Server
$ zip -qr - project|stdinho 0.0.0.0:1337 -f project.zip
# Client
$ curl 127.0.0.1:1337 -o project.zip # Saves it to project.zip
```

</p>
</details>
<details><summary>Dump remote database and stream it to different database on the fly via middle man</summary>
<p>

```bash
# Server
$ ssh admin@example.com "mysqldump -u root -ptoor database|gzip -c"|stdinho 0.0.0.0:1337 -f "$(date).sql.gz" # also saves the backup locally
# Client
$ curl 127.0.0.1:1337|gunzip|mysql -u root -ptoor database # Import it directly to local DB
```

</p>
</details>
<details><summary>Use case from GIF in this README</summary>
<p>

```bash
#   There is bad connectivity between A (public server) and B (user connected to network via special VPN), 
#   but good connectivity between A and C (on same local network as A, but not public). 
#   However, B and C are behind NAT in separate networks, so there is no direct connection between them.
#   Here D is introduced, which is public server having good connection to both C and B, but no connection to A. 
#   In final, download stream goes like this: A -> C -> D -> B which bypasses connection problem between A and B and NAT issue at the same time
#   This problem is basically animation shown in introduction of this README.
# C:
$ ssh -NR \*:1337:localhost:1337 D #Reverse tunnel. Note: GatewayPorts cannot be set to "no" in D's sshd_config
$ curl http://A.com/big_file.tar.gz|stdinho 0.0.0.0:1337
# B:
$ curl D:1337 -o big_file.tar.gz
```

</p>
</details>

## Licensing

GPLv3 license. Please see [License File](LICENSE.md) for more information.

## See also


- [inetd](https://debian-administration.org/article/371/A_web_server_in_a_shell_script) - Really old general purpose server, it's preinstalled on BSDs. I don't recommend to use it though if you don't have time to play around ;) You will have to write config for it, simple bash script and write HTTP headers by hand. And you will have no monitoring at hand.
- [websocketd](https://github.com/joewalnes/websocketd) If you need to serve output of a program over websockets instead of HTTP
