# OdSentry plugin for Shopware 5.5

## What is Sentry
[Sentry](https://sentry.io) is a modern error tracking platform. You can log and trace errors in Sentry and collect directly feedback from user.

![Detail](https://drive.google.com/uc?export=view&id=0B_KpXXAo-7I-aWo5Mi1DWkxqNzg)

Sentry can:

* show error traces
* notify on Slack, Jira, GitHub, GitLab, HipChat, etc.
* use business rules (error must occure more than twice in 24h)
* do reporting
* track error occurrences with releases
* be easily self-hosted with docker

## What this plugin does

This plugin gives you the possibility to collect errors that occured in Shopware to a custom Sentry project.

* collects PHP errors in frontend and backend (can be switched on/off)
* collects JS errors in frontend (can be switched on/off)
* collect user feedback in the frontend if a PHP error occured (can be switched on/off)

![Configuration](https://drive.google.com/uc?export=view&id=0B_KpXXAo-7I-ZkxqLUFTZ1UxNnc)

## User Feedback

If you enable it and a catchable error occurs, the user will be asked to provide additional feedback:

![User Feedback](https://drive.google.com/uc?export=view&id=0B_KpXXAo-7I-Q29RMHZzZkd1T0k)

## Installation

**Requires PHP >= 7.1 !!**

### Load plugin

#### Composer (Shopware 5.5+)

* Install via composer `composer require onedrop/shopware-sentry`

#### Git Version

* Checkout plugin in `git clone https://github.com/1drop/shopware-sentry.git custom/plugins/OdSentry`
* Install dependencies `composer install`

#### Shopware plugin store

Plugin can be ordered for free in your plugin manager or in [Shopware plugin store](https://store.shopware.com/ods3018122618489f/sentry.html)

### Install plugin

#### CLI

* Install plugin `php ./bin/console sw:plugin:install OdSentry`
* Get plugin config  `php ./bin/console sw:plugin:config:list OdSentry` (based on `custom/plugins/OdSentry/Resources/config.xml`)
* Set plugin config e.g. `php ./bin/console sw:plugin:config:set OdSentry sentryLogPhp true`
* Activate plugin `php ./bin/console sw:plugin:activate OdSentry`
* (optional) Clear cache `php ./bin/console sw:cache:clear`

#### Web

* Install the plugin with the "Plugin Manager"
* Configure the plugin

### Skip Exceptions

Since 1.1.1 it is possible to skip exceptions for capture add following snippet to our config.php
````php
'sentry' => [
    'skip_capture' => [
        CommandNotFoundException::class,
        Enlight_Controller_Exception::class,
        MyCustomException::class
    ]
]
````

## Authors

* Hans Höchtl <hhoechtl[at]1drop.de>
* Soner Sayakci <s.sayakci[at]gmail.com>
