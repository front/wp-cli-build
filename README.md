WP-CLI Build
==================

Use [WP-CLI](http://wp-cli.org/) to version your plugins, themes and core! And of course: Git friendly.

Quick links: [Installing](#installing) | [Using](#using) | [Contributing](#contributing)

## Installing
Installing this package requires WP-CLI v0.23.0 or greater. Update to the latest stable release with `wp cli update`.

Once you've done so, you can install this package with `wp package install https://github.com/front/wp-cli-build.git`.

You need WP installed to get started, so if you don't already have an existing site:
`wp core download` and install. 

With that done, generate your barebones build file:
`wp build-generate`

For options, see `wp build-generate --help`

## Use the build file

`wp build`

This parses the yaml build file and will process core, plugins and themes.


## Contributing

We appreciate you taking the initiative to contribute to this project.

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
