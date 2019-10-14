# Deprecated in Neos 5 <br /> Please use Fusion error page rendering instead

# Neos error pages

[![Latest Stable Version](https://poser.pugx.org/breadlesscode/neos-error-pages/v/stable)](https://packagist.org/packages/breadlesscode/neos-error-pages)
[![Downloads](https://img.shields.io/packagist/dt/breadlesscode/neos-error-pages.svg)](https://packagist.org/packages/breadlesscode/neos-error-pages)
[![License](https://img.shields.io/github/license/breadlesscode/neos-error-pages.svg)](LICENSE)
[![GitHub stars](https://img.shields.io/github/stars/breadlesscode/neos-error-pages.svg?style=social&label=Stars)](https://github.com/breadlesscode/neos-error-pages/stargazers)
[![GitHub watchers](https://img.shields.io/github/watchers/breadlesscode/neos-error-pages.svg?style=social&label=Watch)](https://github.com/breadlesscode/neos-error-pages/subscription)

This package provides multiple error pages for your Neos CMS site.
You can add an error page for each subfolder; this package shows the nearest
error page from the entry point.

## Installation

Most of the time you have to make small adjustments to a package (e.g., the
configuration in `Settings.yaml`). Because of that, it is important to add the
corresponding package to the composer from your theme package. Mostly this is the
site package located under `Packages/Sites/`. To install it correctly go to your
theme package (e.g.`Packages/Sites/Foo.Bar`) and run following command:

```bash
composer require breadlesscode/neos-error-pages --no-update
```

The `--no-update` command prevent the automatic update of the dependencies.
After the package was added to your theme `composer.json`, go back to the root
of the Neos installation and run `composer update`. Your desired package is now
installed correctly.

## Usage

1. Configure the node type
2. Configure the fusion prototype
3. Add one error page to your site root

### `Breadlesscode.ErrorPages:Page`

The node type `Breadlesscode.ErrorPages:Page`does inherit from `Neos.Neos:Document`.
There are no child nodes defined, so if you want to add content elements to your
error page, you have to add a ContentCollection to the node type.

```
'Breadlesscode.ErrorPages:Page':
  childNodes:
    main:
      type: 'Neos.Neos:ContentCollection'
```

To overwrite the document with your custom document you can do it like this:

```fusion
prototype(Breadlesscode.ErrorPages:Page) >
prototype(Breadlesscode.ErrorPages:Page) < prototype(Vendor.Foo:Page.Document)
```

### Fusion path

`errorPages` is the fusion path which gets rendered if an error page gets shown.
Make sure it has the same look as when you go directly to the page.

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.

## Contributors
 - [Breadlesscode](https://github.com/breadlesscode)
 - [Jonnitto](https://github.com/jonnitto)
