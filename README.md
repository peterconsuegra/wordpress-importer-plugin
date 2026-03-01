# WordPress Importer for Laravel (Pete Panel)

> One-click WordPress migration and backup importer for Laravel-based
> hosting environments.

**WordPress Importer for Pete Panel** allows you to import full
WordPress websites (files + database) into a Laravel-powered Docker
hosting stack using resumable uploads or direct server-path imports.

Perfect for agencies, SaaS founders, and developers managing large
WordPress migrations inside a modern Laravel infrastructure.

------------------------------------------------------------------------

## 🚀 Why This Plugin?

Migrating WordPress sites manually is slow, risky, and error-prone.

This importer provides:

-   ✅ One-click WordPress migration
-   ✅ Resumable large file uploads (chunked)
-   ✅ Support for very large archives
-   ✅ Direct server-path imports
-   ✅ Live import progress tracking
-   ✅ Seamless integration with Laravel 10.x
-   ✅ Built for Docker-based WordPress hosting

If you're searching for:

-   wordpress migration tool
-   wordpress importer laravel
-   wordpress backup restore laravel
-   laravel wordpress hosting panel
-   docker wordpress migration

This plugin was built for you.

------------------------------------------------------------------------

## 🔥 Core Features

### 1️⃣ Resumable Chunk Uploads

Upload massive WordPress backups safely.\
If the connection drops, uploads automatically resume.

Built using Resumable.js with safe server-side assembly.

------------------------------------------------------------------------

### 2️⃣ Import from Server Path

Already have the archive on disk or a mounted volume?

Simply provide the absolute path:

    /var/www/html/backups/mysite.zip

No upload required.

------------------------------------------------------------------------

### 3️⃣ Full WordPress Environment Import

-   WordPress core files
-   Plugins & themes
-   Media uploads
-   Full database dump

Ready to deploy inside Pete Panel instantly.

------------------------------------------------------------------------

### 4️⃣ Real-Time Import Status

Long-running imports are handled as background jobs with:

    GET /wordpress-importer/status/{id}

Response example:

``` json
{
  "status": "running",
  "progress": 40,
  "message": "Extracting archive..."
}
```

Terminal states:

-   succeeded
-   failed

------------------------------------------------------------------------

## 📦 Installation

``` bash
composer require peteconsuegra/wordpress-importer-plugin
```

Laravel 10.x required.

If manual provider registration is needed:

``` php
Pete\WordPressImporter\WordPressImporterServiceProvider::class,
```

------------------------------------------------------------------------

## 🌐 Registered Routes

  Method   Endpoint                                 Description
  -------- ---------------------------------------- ----------------
  GET      /wordpress-importer                      Import UI
  POST     /wordpress-importer                      Enqueue import
  GET      /wordpress-importer/status/{id}          Check status
  POST     /wordpress-importer/upload-chunk         Upload chunk
  DELETE   /wordpress-importer/upload-chunk/abort   Abort upload

All routes use the `web` middleware with CSRF protection.

------------------------------------------------------------------------

## 🛡 Security

-   CSRF protected
-   Designed for authenticated dashboards
-   Supports policy-based authorization (recommended)

------------------------------------------------------------------------

## 🧠 Designed for Hybrid WordPress + Laravel Stacks

This importer is part of the Pete Panel ecosystem, enabling:

-   WordPress for SEO & marketing
-   Laravel for SaaS & APIs
-   Docker for consistent deployments

Build hybrid applications faster.

------------------------------------------------------------------------

## 🔗 Related Projects

-   WordPress + Laravel Plugin\
    https://github.com/peterconsuegra/wordpress-plus-laravel-plugin

-   Laravel Manager Plugin\
    https://github.com/peterconsuegra/laravel-manager-plugin

-   Pete Panel Official Site\
    https://deploypete.com

------------------------------------------------------------------------

## 📄 License

MIT (package)\
Pete Panel is a commercial product.

------------------------------------------------------------------------

## ⭐ Keywords

wordpress importer laravel\
wordpress migration docker\
wordpress backup restore laravel\
laravel wordpress hosting\
wordpress panel laravel\
docker wordpress migration\
wordpress clone tool\
wordpress import large file

------------------------------------------------------------------------

### Build faster. Migrate smarter. Deploy WordPress inside Laravel.
