WP-CLI Build
==================

Version your plugins, themes and core of your WordPress sites! 

**WP-Cli Build** helps you to start your WP site in an organized way and simplifies its plugins maintenance: you don't need to versionate code that isn't maintain for you like WordPress plugins and themes. Also it could help you to rebuild your site from a hack or something else that had compromised the plugins. 
```sh
$ wp build
```

## Getting Started
### Prerequistes
This package requires [WP-CLI](https://make.wordpress.org/cli/handbook/installing/) v0.23.0 or greater. You can check WP-CLI version with `$ wp --version` and update to the latest stable release with `$ wp cli update`. 

### Installing
You could install **WP-Cli Build** from our git repo:
```sh
$ wp package install https://github.com/front/wp-cli-build.git
```

## Quick Start
You need WP installed to get started, so if you don't already have an existing site: `wp core download and install`.

To generate your **build.yml** file with your WP site core configuration and the list of used public plugins, run
```sh
$ wp build-generate
```
It will also rewrite your **.gitignore** file with the plugins paths which shouldn’t be versinoated. 

For more options, see `$ wp build-generate --help`

## Using build.yml
You can run `$ wp build` to install the WordPress core of your site, 3rd party plugins and themes.

### Updating build.yml
When you add a new plugin to your WP site, you should run `$ wp build generate` to update **build.yml** and **.gitignore** files.

For more options run `$ wp --help build`

### Rebuild
Adding `--rebuild` option to `$ wp build` command forces all plugins to be deleted and downloaded again. It helps you to make sure plugins remain original.  

## Contributing
We appreciate you taking the initiative to contribute to this project!

Contributing isn’t limited to just code. We encourage you to contribute in the way that best fits your abilities, by writing tutorials, giving a demo at your local meetup, helping other users with their support questions, or revising our documentation.

### Reporting a bug

Think you’ve found a bug? We’d love for you to help us get it fixed.

Before you create a new issue, you should [search existing issues](https://github.com/front/wp-cli-build/issues?q=label%3Abug%20) to see if there’s an existing resolution to it, or if it’s already been fixed in a newer version.

Once you’ve done a bit of searching and discovered there isn’t an open or fixed issue for your bug, please [create a new issue](https://github.com/front/wp-cli-build/issues/new) with the following:

1. What you were doing (e.g. "When I run `wp build` ...").
2. What you saw (e.g. "I see a fatal about a class being undefined.").
3. What you expected to see (e.g. "I expected to see the list of posts.")

Include as much detail as you can, and clear steps to reproduce if possible.

### Creating a pull request

Want to contribute a new feature? Please first [open a new issue](https://github.com/front/wp-cli-build/issues/new) to discuss whether the feature is a good fit for the project.
