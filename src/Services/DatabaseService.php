<?php

namespace App\Services;

use PDO;

class DatabaseService
{
    private ?PDO $db = null;

    public function getConnection(): ?PDO
    {
        if ($this->db === null) {
            $host = $_ENV['DB_HOST'] ?? null;
            $dbname = $_ENV['DB_NAME'] ?? null;
            $username = $_ENV['DB_USERNAME'] ?? null;
            $password = $_ENV['DB_PASSWORD'] ?? null;

            if (!$host || !$dbname || !$username || !$password) {
                // Required environment variables are missing – skip initialization
                return null;
            }

            $dsn = "mysql:host=$host;dbname=$dbname";
            $this->db = new PDO($dsn, $username, $password);
        }
        return $this->db;
    }

    public function saveContent(array $content): void
    {
        $stmt = $this->getConnection()->prepare('INSERT INTO content (title, slug, type, content) VALUES (:title, :slug, :type, :content)');
        $stmt->execute([
            ':title' => $content['title'],
            ':slug' => $content['slug'],
            ':type' => $content['type'],
            ':content' => $content['content'],
        ]);
    }

    public function searchContent(string $query): array
    {
        $stmt = $this->getConnection()->prepare('SELECT * FROM content WHERE content LIKE :query');
        $stmt->execute([':query' => '%' . $query . '%']);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}