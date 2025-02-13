<?php

namespace App\Repositories;

use PDO;

class ContentRepository
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function saveContent(array $content): void
    {
        $stmt = $this->db->prepare('INSERT INTO documents (title, slug, type, content, language, cms_id, url) VALUES (:title, :slug, :type, :content, :language, :cms_id, :url)');
        $stmt->execute([
            ':title' => 'palceholder',
            ':slug' => $content['slug'],
            ':type' => $content['type'],
            ':content' => $content['content'],
            ':language' => $content['language'],
            ':cms_id' => $content['cms_id'],
            ':url' => $content['url'],
        ]);
    }


    public function searchContent(string $query): array
    {
        $stmt = $this->db->prepare('SELECT * FROM content WHERE content LIKE :query');
        $stmt->execute([':query' => '%' . $query . '%']);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}