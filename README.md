# TwigFlow

TwigFlow is a web application built with the Slim Framework and Twig templating engine. It fetches content from various CMS APIs (e.g., Payload CMS) and renders it using Twig templates. The application also includes a caching mechanism to reduce the number of API requests and improve performance.

## Features

- Fetches content from CMS APIs using slugs
- Renders content using Twig templates
- Caching mechanism to reduce API requests
- Supports multiple CMS services (e.g., Payload CMS, Sanity)

## Requirements

- PHP 7.4 or higher
- Composer
- MAMP (or any other local development environment)

## Installation

1. **Install the package:**

   ```sh
   composer require antymoro/twigflow:dev-main --prefer-stable
   ```

2. **Copy user editable files to the root directory:**

   ```sh
   php vendor/antymoro/twigflow/src/cli publish:resources
   ```

3. **Set up environment variables:**

   Create a `.env` file in the root directory and add the necessary environment variables:

   ```env
   API_URL=https://id.api.sanity.io/v2022-03-07
   CMS_CLIENT=sanity
   APP_ENV=development
   CACHE_EXPIRE_TIME=0
   TWIG_CACHE=false
   HOMEPAGE_SLUG=homepage

   ```

4. **Set up the web server:**

   If you are using MAMP, set the document root to the root folder of the project.

## Usage

### Start the web server

If you are using MAMP, start the Apache and MySQL servers.

### Access the application

Open your web browser and navigate to:

```
http://localhost:[port]
```

Replace `[port]` with the port number configured in MAMP.

### Clear cache

To clear the cache, send a POST request to:

```
http://localhost:[port]/clear-cache
```