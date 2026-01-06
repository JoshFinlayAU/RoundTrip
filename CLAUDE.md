# RoundTrip
### Modern, clean, fast alternative to SmokePing

## Backend

Database: TimescaleDB
Poller: Laravel Artisan worker
Library: Laravel

## Frontend

Served from Laravel
Live UI written in Next.js which connects to a web socket backend for streaming telemetry
Graphing with "smoke" like effect using D3.js to similar smokeping style smoke effects

## Instructions

Confirm any critical decisions with me before making them
You have free access to install any software you need to.
The underlying OS is Debian

You'll keep your code base in /opt/roundtrip

We should use the latest fping with JSON support (needs to be the latest fping 5.5 release, may need to build from source but if so include it in the install/build script instructions)

## Important

Ensure that it is possible to push this to github and allow anyone to clone it, run an install/setup script and it all magically work for them.

## VERY important

Avoid, at all cost, making it look like AI has written this.

Avoid:

 - Excessive git commits - keep it worded like an engineer
 - Do not use emoji's ever
 - Keep readme/doco precise and to the point
 - Include the occasional typo in comments or readme, but only 1 or 2 just to appear human.
