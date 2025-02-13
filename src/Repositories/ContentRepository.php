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

    public function saveJob(array $job): void
    {
        // Check if a job with the same cms_id already exists
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM jobs WHERE cms_id = :cms_id');
        $stmt->execute([':cms_id' => $job['cms_id']]);
        $count = $stmt->fetchColumn();

        if ($count == 0) {
            // Insert the new job if it doesn't already exist
            $stmt = $this->db->prepare('INSERT INTO jobs (url, type, language, slug, cms_id, status) VALUES (:url, :type, :language, :slug, :cms_id, :status)');
            $stmt->execute([
                ':url' => $job['url'],
                ':type' => $job['type'],
                ':language' => $job['language'],
                ':slug' => $job['slug'],
                ':cms_id' => $job['cms_id'],
                ':status' => $job['status'],
            ]);
        }
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
}