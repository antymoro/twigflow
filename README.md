# PHP Twig Renderer

PHP Twig Renderer is a web application built with the Slim Framework and Twig templating engine. It fetches content from various CMS APIs (e.g., Payload CMS) and renders it using Twig templates. The application also includes a caching mechanism to reduce the number of API requests and improve performance.

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

1. **Clone the repository:**

   ```sh
   git clone https://github.com/your-username/php-twig-renderer.git
   cd php-twig-renderer
   ```

2. **Install dependencies:**

   ```sh
   composer install
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

## Deployment

To deploy the application to a production environment, follow these steps:

1. **Set up the server:**

   Ensure the server meets the requirements (PHP 7.4 or higher, Composer).

2. **Clone the repository:**

   ```sh
   git clone https://github.com/your-username/php-twig-renderer.git
   cd php-twig-renderer
   ```

3. **Install dependencies:**

   ```sh
   composer install --no-dev --optimize-autoloader
   ```

4. **Set up environment variables:**

   Create a `.env` file in the root directory and add the necessary environment variables.

5. **Set up the web server:**

   Configure the web server (e.g., Apache, Nginx) to point to the `public` directory of the project.

6. **Set up caching:**

   Ensure the server has write permissions to the cache directory (`var/cache`).


## License

This project is licensed under the MIT License. See the [LICENSE](LICENSE) file for details.

## Acknowledgements

- Slim Framework
- Twig
- Payload CMS
- Symfony Cache
