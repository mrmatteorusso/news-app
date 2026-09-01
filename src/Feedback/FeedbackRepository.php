<?php

declare(strict_types=1);

final class FeedbackRepository
{
    private const ACTIONS = ['useful', 'not_useful', 'too_minor', 'wrong_category'];

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function save(int $articleId, string $section, string $action, ?string $note = null): array
    {
        if (!in_array($action, self::ACTIONS, true)) {
            throw new InvalidArgumentException('Unsupported feedback action.');
        }
        $exists = $this->pdo->prepare(
            'SELECT 1 FROM article_sections WHERE article_id = :article_id AND section = :section'
        );
        $exists->execute([':article_id' => $articleId, ':section' => $section]);
        if ($exists->fetchColumn() === false) {
            throw new InvalidArgumentException('The article does not belong to this section.');
        }
        $statement = $this->pdo->prepare(
            'INSERT INTO feedback_events (article_id, section, action, note, created_at)
             VALUES (:article_id, :section, :action, :note, :created_at)'
        );
        $statement->execute([
            ':article_id' => $articleId,
            ':section' => $section,
            ':action' => $action,
            ':note' => $note === null ? null : mb_substr(trim($note), 0, 500),
            ':created_at' => gmdate('Y-m-d H:i:s'),
        ]);
        return ['id' => (int) $this->pdo->lastInsertId(), 'action' => $action];
    }

    public function context(string $section, int $limit): array
    {
        $statement = $this->pdo->prepare(
            'SELECT fe.action, fe.note, a.title, s.name AS source_name
             FROM feedback_events fe
             INNER JOIN articles a ON a.id = fe.article_id
             INNER JOIN sources s ON s.id = a.source_id
             WHERE fe.section = :section
             ORDER BY fe.id DESC
             LIMIT :limit'
        );
        $statement->bindValue(':section', $section);
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->execute();
        return $statement->fetchAll();
    }
}
