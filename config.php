<?php

(new Aerys\Host)
    ->use(function(Aerys\Request $req, Aerys\Response $resp) {
        $resp->end("<h1>It Works!</h1>");
    });