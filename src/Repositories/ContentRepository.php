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
        $stmt = $this->db->prepare('INSERT INTO content (title, slug, type, content) VALUES (:title, :slug, :type, :content)');
        $stmt->execute([
            ':title' => $content['title'],
            ':slug' => $content['slug'],
            ':type' => $content['type'],
            ':content' => $content['content'],
        ]);
    }

    public function searchContent(string $query): array
    {
        $stmt = $this->db->prepare('SELECT * FROM content WHERE content LIKE :query');
        $stmt->execute([':query' => '%' . $query . '%']);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}