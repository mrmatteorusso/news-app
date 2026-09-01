<?php

declare(strict_types=1);

function e(string|int|float|null $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function mock_time(string $relative): string
{
    return (new DateTimeImmutable($relative))->format('H:i');
}

function render_story(array $story, string $variant = ''): void
{
    $classes = trim('story-card ' . $variant);
    $articleId = isset($story['article_id']) ? (int) $story['article_id'] : null;
    ?>
    <article class="<?= e($classes) ?>"<?= $articleId ? ' data-article-id="' . e($articleId) . '"' : '' ?>>
        <div class="story-card__meta-top">
            <span class="story-card__tag"><?= e($story['tag']) ?></span>
            <?php if (!empty($story['confidence'])): ?>
                <span class="confidence" title="<?= e($story['confidence_title'] ?? 'Confidence measures how complete and corroborated the available evidence is.') ?>">
                    Evidence: <?= e($story['confidence']) ?>
                </span>
            <?php endif; ?>
        </div>
        <?php if (!empty($story['topic_tags'])): ?>
            <div class="topic-tags" aria-label="Topics">
                <?php foreach ($story['topic_tags'] as $topic): ?><span><?= e($topic) ?></span><?php endforeach; ?>
            </div>
        <?php endif; ?>
        <h3><?= e($story['headline']) ?></h3>
        <p><?= e($story['summary']) ?></p>
        <?php if (!empty($story['score_breakdown'])): ?>
            <div class="score-strip" aria-label="Deterministic ranking score breakdown">
                <span class="score-strip__total">Rank <?= e(number_format((float) $story['rank_score'], 1)) ?></span>
                <?php foreach ($story['score_breakdown'] as $label => $score): ?>
                    <span><small><?= e($label) ?></small> <?= e($score) ?></span>
                <?php endforeach; ?>
            </div>
            <p class="corroboration"><strong>Corroboration:</strong> <?= e($story['corroboration']) ?></p>
            <?php if (!empty($story['related_links'])): ?>
                <p class="related-links"><strong>Related reports:</strong>
                    <?php foreach ($story['related_links'] as $related): ?>
                        <a href="<?= e($related['url']) ?>" target="_blank" rel="noreferrer noopener" data-track-link><?= e($related['name']) ?></a>
                    <?php endforeach; ?>
                </p>
            <?php endif; ?>
        <?php endif; ?>
        <?php if (!empty($story['llm_model'])): ?>
            <p class="intelligence-meta">
                <strong>Gemma selected</strong>
                <span>· <?= e($story['llm_reason_label'] ?? 'profile selection') ?> · <?= e($story['llm_model']) ?></span>
            </p>
        <?php endif; ?>
        <p class="why"><strong><?= e($story['why_label'] ?? 'Why it was chosen') ?>:</strong> <?= e($story['why']) ?></p>
        <?php if (!empty($story['llm_model']) && !empty($story['deterministic_explanation'])): ?>
            <details class="decision-trace">
                <summary>Compare deterministic basis</summary>
                <p><?= e($story['deterministic_explanation']) ?></p>
            </details>
        <?php endif; ?>
        <div class="story-card__details">
            <span><?= e($story['source']) ?></span>
            <span>Published <?= e($story['published']) ?></span>
            <span>Source updated <?= e($story['source_updated']) ?></span>
            <span data-retrieved-at>Retrieved <?= e($story['retrieved']) ?></span>
        </div>
        <a class="story-link" href="<?= e($story['url']) ?>" target="_blank" rel="noreferrer noopener" data-track-link>
            <?= e($story['link_label'] ?? 'Open source') ?> <span aria-hidden="true">→</span>
        </a>
        <?php if ($articleId): ?>
            <div class="feedback-controls" aria-label="Rate this selection">
                <span>Teach the profile:</span>
                <?php foreach (['useful' => 'Useful', 'too_minor' => 'Too minor', 'wrong_category' => 'Wrong category', 'not_useful' => 'Not useful'] as $action => $label): ?>
                    <button type="button" data-feedback-action="<?= e($action) ?>" aria-pressed="<?= ($story['feedback_action'] ?? null) === $action ? 'true' : 'false' ?>">
                        <?= e($label) ?>
                    </button>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </article>
    <?php
}

function render_section_header(array $section): void
{
    ?>
    <div class="section-heading">
        <div>
            <div class="section-heading__eyebrow">
                <span class="status-dot status-dot--<?= e($section['status']) ?>" aria-hidden="true"></span>
                <span data-section-state><?= e($section['state']) ?></span>
            </div>
            <h2><span aria-hidden="true"><?= e($section['icon']) ?></span> <?= e($section['title']) ?></h2>
            <p class="section-heading__time">
                Last successful update: <strong data-last-updated><?= e($section['updated']) ?></strong>
                <span class="separator">·</span>
                Batch <span data-batch-id><?= e($section['batch']) ?></span>
            </p>
        </div>
        <button class="button button--section" type="button" data-refresh-section aria-label="Refresh <?= e($section['title']) ?>">
            <span aria-hidden="true">↻</span> Refresh
        </button>
    </div>
    <?php
}
