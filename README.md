# stdinho

[![Latest Version on Packagist][ico-version]][link-packagist]
[![Software License][ico-license]](LICENSE.md)
[![Build Status][ico-build]][link-build]


`stdinho` is small command-line tool that creates TCP server, accepts any STDOUT as its STDIN and whoever connects to the server will get this data served as HTTP response.

It was written from frustration of having to share remote resources with my under-priviliged colleagues on semi-regular basis.
This typically involves downloading file, uploading file, sending the link, waiting until target finishes downloading file, deleting file. In each stage of the process you would normally have to wait.

![stdinho animation](https://user-images.githubusercontent.com/496233/47866950-750db900-de00-11e8-8631-d25d723128f5.gif)

## Install

Via [Composer](https://getcomposer.org/doc/00-intro.md)

```bash
composer global require ostrolucky/stdinho
```

Or grab executable PHAR file from [Releases](https://github.com/ostrolucky/stdinho/releases)

## Usage

As simple as just piping some data in:
```bash
echo hello world|stdinho
```

For all options see
```bash
stdinho --help
```

## Features

* async. Yep, in PHP. No restriction on clients downloading simultaneusly
* buffers to temp file before client is connected, so no time or data in between is lost
* cross-platform: Linux/MacOS/Windows
* detects MIME type and attaches it to HTTP response
* neat progress bar showing status of buffering and client downloads.

## Examples
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
<details><summary>Tail application logs in realtime</summary>
<p>

```bash
# Server
$ tail -f project/var/log/*.log|stdinho 0.0.0.0:1337
# Client
$ curl 127.0.0.1:1337 

# Bonus: gzip transfer encoding (server)
$ tail -f project/var/*.log|gzip -c|stdinho 0.0.0.0:1337 --http-headers='["Content-Type: text/plain", "Content-Encoding: gzip", "Transfer-Encoding: gzip"]'
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

[ico-version]: https://img.shields.io/packagist/v/ostrolucky/stdinho.svg?style=flat-square
[ico-license]: https://img.shields.io/badge/license-GPL-brightgreen.svg?style=flat-square
[ico-build]: https://github.com/ostrolucky/stdinho/actions/workflows/continuous-integration.yml/badge.svg

[link-packagist]: https://packagist.org/packages/ostrolucky/stdinho
[link-build]: https://github.com/ostrolucky/stdinho/actions/workflows/continuous-integration.yml