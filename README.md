# OdSentry plugin for Shopware 5.2

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

### Git Version
* Checkout plugin in `/custom/plugins/OdSentry`
* Install the plugin with the "Plugin Manager"
* Configure the plugin

### Shopware plugin store

This plugin will be available shortly in the Shopware plugin store.
