<?php

namespace App\Repositories;

use PDO;
use Exception;

class ContentRepository
{
    private ?PDO $db;

    public function __construct($db = null)
    {
        $this->db = $db;
    }

    public function getDocumentByCmsId(string $cmsId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM documents WHERE cms_id = :cms_id');
        $stmt->execute([':cms_id' => $cmsId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function saveJob(array $job): void
    {
        $stmt = $this->db->prepare('INSERT INTO jobs (title, url, type, language, slug, cms_id, status) VALUES (:title, :url, :type, :language, :slug, :cms_id, :status)');
        $stmt->execute([
            ':title' => $job['title'],
            ':url' => $job['url'],
            ':type' => $job['type'],
            ':language' => $job['language'],
            ':slug' => $job['slug'],
            ':cms_id' => $job['cms_id'],
            ':status' => $job['status'],
        ]);
    }

    public function updateJobStatus(int $jobId, string $status): void
    {
        $stmt = $this->db->prepare('UPDATE jobs SET status = :status WHERE id = :id');
        $stmt->execute([
            ':status' => $status,
            ':id' => $jobId,
        ]);
    }

    public function getPendingJobs($limit = 5): array
    {
        $stmt = $this->db->query('SELECT * FROM jobs WHERE status = "pending" LIMIT ' . $limit);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function searchContent(string $query, ?string $language = null): array
    {
        if ($language) {
            $stmt = $this->db->prepare('
                SELECT *, 
                CASE 
                    WHEN title LIKE :query THEN 2 
                    ELSE 1 
                END AS relevance 
                FROM documents 
                WHERE (title LIKE :query OR content LIKE :query) AND language = :language 
                ORDER BY relevance DESC
            ');
            $stmt->execute([
                ':query' => '%' . $query . '%',
                ':language' => $language,
            ]);
        } else {
            $stmt = $this->db->prepare('
                SELECT *, 
                CASE 
                    WHEN title LIKE :query THEN 2 
                    ELSE 1 
                END AS relevance 
                FROM documents 
                WHERE title LIKE :query OR content LIKE :query 
                ORDER BY relevance DESC
            ');
            $stmt->execute([
                ':query' => '%' . $query . '%',
            ]);
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}