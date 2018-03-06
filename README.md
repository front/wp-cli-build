![WP-CLI Build](https://wputvikling.no/wp-cli-build.png)

**WP-CLI Build** helps you to start your WP site in an organized way and simplifies maintenance: you don't need to version code that you don't maintain yourself like WP core, themes or 3rd party plugins. This makes it easy to have [auto updates](https://github.com/front/wp-cli-build/wiki/Auto-update-your-website), without messing up your Git setup. **WP-CLI Build** is also useful for [rebuilding your site after a hack](https://github.com/front/wp-cli-build/wiki/Rebuild-from-an-attack). 
```sh
$ wp build
```
For more background, check out [A Git Friendly Way to Handle WordPress Updates – Slides from  WordCamp Oslo 2018](https://www.slideshare.net/frontkomnorway/a-git-friendly-way-to-handle-wordpress-updates-wordcamp-oslo-2018-89758006)

## Getting Started
### Prerequistes
This package requires [WP-CLI](https://make.wordpress.org/cli/handbook/installing/) v1.5 or greater. You can check WP-CLI version with `$ wp --version` and update to the latest stable release with `$ wp cli update`. 

### Installing
Install **WP-CLI Build** from our git repo:
```sh
$ wp package install front/wp-cli-build
```

**Note:** The WP-CLI package installer will fail silently if your memory limit is too low. To see if installation was successful, run `$ wp package list`. If empty, locate your php.ini and increase the memory_limit.

## Quick Start
You need WP installed to get started, so if you don't already have an existing site: `wp core download and install`.

The ***build*** file is the base of **WP-CLI Build** and will contain your WP site core configuration and the list of used public plugins and themes. Last version of **WP-CLI Build** will generate a *`build.json`* file but we still support *`build.yml`*, so you can use both. 

To generate the ***build*** file you should run:
```sh
$ wp build-generate
```
It will also rewrite your ***.gitignore*** to make sure only custom plugins and themes are indexed. Bellow, you can see a sample of the *WP-CLI BUILD BLOCK* added to ***.gitignore***:
```
# START WP-CLI BUILD BLOCK
# ------------------------------------------------------------
# This block is auto generated every time you run 'wp build-generate'
# Rules: Exclude everything from Git except for your custom plugins and themes (that is: those that are not on wordpress.org)
# ------------------------------------------------------------
/*
!.gitignore
!build.json
!wp-content
wp-content/*
!wp-content/plugins
wp-content/plugins/*
!wp-content/themes
wp-content/themes/*
# ------------------------------------------------------------
# Your custom themes/plugins
# Added automagically by WP-CLI Build (wp build-generate)
# ------------------------------------------------------------
!wp-content/plugins/custom-plugin-slug/
!wp-content/themes/custom-theme-slug/
# ------------------------------------------------------------
# END WP-CLI BUILD BLOCK
```

***Note:** Only active plugins and themes will be listed in **build** file and **gitignore**, unless you specify `--all` argument*.

For more options, see `$ wp build-generate --help`

## Using *build* file
You can run `$ wp build` to install the WordPress core of your site, 3rd party plugins and themes. It parses your ***build*** file, and works its magic!

A sample of a ***build.json*** file:

```
{
    "core": {
        "download": {
            "version": "~4.9.4",
            "locale": "en_US"
        }
    },
    "plugins": {
        "advanced-custom-fields": {
            "version": "*"
        },
        "timber-library": {
            "version": "^1.7.0"
        },
        "wordpress-seo": {
            "version": "*"
        }
    },
    "themes": {
        "twentyseventeen": {
            "version": "1.4"
        }
    }
}
```

A sample of a ***build.yml*** file:
```
core:
    download:
        version: "~4.9.4"
        locale: en_US
plugins:
    advanced-custom-fields
        version: "*"
    timber-library:
        version: "^1.7.0"
    wordpress-seo:
        version: "*"
themes:
    twentyseventeen:
        version: 1.4
```

Notice that you can use `~`, `*` and `^` operators when you don't want to refer a fixed version. 

### Updating *build* and *.gitignore*
When you add a new plugin to your WP site, you should run `$ wp build-generate` to update ***build*** and ***.gitignore*** files.

For more options run `$ wp --help build-generate` and `$ wp --help build`

### Clean install
Adding `--clean` option to `$ wp build` command forces all plugins to be deleted and downloaded again. It helps you make sure plugins are not corrupted.  

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
