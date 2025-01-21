<?php

namespace App\Services;

use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Contracts\Cache\ItemInterface;

class CacheService {
    private FilesystemAdapter $cache;

    public function __construct() {
        $this->cache = new FilesystemAdapter();
    }

    public function get(string $key, callable $callback, int $expiresAfter = 3600) {
        return $this->cache->get($key, function (ItemInterface $item) use ($callback, $expiresAfter) {
            $item->expiresAfter($expiresAfter);
            return $callback();
        });
    }

    public function clear(string $key): bool {
        return $this->cache->deleteItem($key);
    }

    public function clearAll(): bool {
        return $this->cache->clear();
    }
}