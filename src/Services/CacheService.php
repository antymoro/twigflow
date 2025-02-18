<?php

namespace App\Services;

use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Contracts\Cache\ItemInterface;

class CacheService
{
    private FilesystemAdapter $cache;
    private int $defaultExpireTime;

    public function __construct()
    {
        $cacheDirectory = BASE_PATH . '/cache';

        if (!file_exists($cacheDirectory)) {
            mkdir($cacheDirectory, 0777, true);
        }

        $this->cache = new FilesystemAdapter('', 0, $cacheDirectory);
        $this->defaultExpireTime = (int) ($_ENV['CACHE_EXPIRE_TIME'] ?? 3600);
    }

    public function get(string $key, callable $callback, ?int $expiresAfter = null)
    {
        // Bypass caching if we are not in production
        if (APP_ENV !== 'production') {
            return $callback();
        }

        $expiresAfter = $expiresAfter ?? $this->defaultExpireTime;

        return $this->cache->get($key, function (ItemInterface $item) use ($callback, $expiresAfter) {
            if ($expiresAfter > 0) {
                $item->expiresAfter($expiresAfter);
            } else {
                $item->expiresAfter(null); // Never expire
            }
            return $callback();
        });
    }

    public function set(string $key, $value, ?int $expiresAfter = null): bool
    {
        $expiresAfter = $expiresAfter ?? $this->defaultExpireTime;

        $item = $this->cache->getItem($key);
        $item->set($value);

        if ($expiresAfter > 0) {
            $item->expiresAfter($expiresAfter);
        } else {
            $item->expiresAfter(null); // Never expire
        }

        return $this->cache->save($item);
    }

    public function clear(string $key): bool
    {
        return $this->cache->deleteItem($key);
    }

    public function clearAll(): bool
    {
        return $this->cache->clear();
    }
}