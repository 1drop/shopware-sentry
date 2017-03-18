# OdSentry plugin for Shopware 5.2

This plugin logs backend errors (PHP) and frontend errors (JS) to 
a configurable Sentry server.


This plugin is WIP


TODOs:
* raven-js should be loaded before the jquery plugins, but after the libraries
* monolog default cause shopware BE to crash
* validate that the secret key (DSN) is not exposed to JS (regex)
* Tests
