# Laravel Gluu Wrapper

This package provides an integration for your laravel application with Gluu server.

## Installation

To install this package you should add these lines into your `composer.json`

```json
{
  "repositories": [
    {
      "type": "vcs",
      "url": "https://github.com/kwri/LaravelGluuWrapper"
    }
  ],

  "require": {
    "kwri/laravel-gluu-wrapper": "dev-master"
  },

  "prefer-stable": true,
  "minimum-stability": "dev"
}
```

> Since this package is in its early state, you have to include `prefer-stable` and set `minimum-stability` to `true`.

After that, run `composer update` from your terminal.

To use this package, add `KWRI\LaravelGluuWrapper\ServiceProvider::class` into your service provider configuration inside `config/app.php`

If you want to use facade, add `'GluuWrapper' => KWRI\LaravelGluuWrapper\Facades\GluuWrapper::class,` into `aliases` inside your `config/app.php`

## Configuration

1. Run `php artisan vendor:publish`
2. Migrate the `access_tokens` table using `php artisan migrate`
3. Modify `config/gluu-wrapper.php` with your environment configuration
4. Set `autosave` value in the configuration into `true` if you want to save access_token data into your database automatically after every successful access token request
