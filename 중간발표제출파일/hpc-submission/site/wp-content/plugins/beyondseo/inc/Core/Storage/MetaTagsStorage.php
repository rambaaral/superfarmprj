<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Storage;

if (!defined('ABSPATH')) {
    exit;
}

use InvalidArgumentException;
use RankingCoach\Inc\Core\DB\DatabaseTablesManager;
use RuntimeException;
use Throwable;
use wpdb;

class MetaTagsStorage
{
    private const TYPES = ['title', 'description', 'keywords', 'social_title', 'social_description'];
    private const MAX_READ_ROWS = 100;

    private wpdb $db;

    public function __construct(?wpdb $db = null)
    {
        if ($db === null) {
            global $wpdb;
            $db = $wpdb;
        }

        if (!$db instanceof wpdb) {
            throw new RuntimeException('storage_unavailable');
        }

        $this->db = $db;
    }

    public static function decodeKeywordsTemplate(string $template): array
    {
        $data = json_decode($template, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($data) || !str_starts_with(ltrim($template), '{')) {
            throw new InvalidArgumentException('invalid_keyword_payload');
        }

        $primaryKeyword = array_key_exists('primaryKeyword', $data) ? $data['primaryKeyword'] : '';
        $additionalKeywords = array_key_exists('additionalKeywords', $data) ? $data['additionalKeywords'] : [];
        if (!is_string($primaryKeyword) || !is_array($additionalKeywords)
            || ($additionalKeywords !== [] && array_keys($additionalKeywords) !== range(0, count($additionalKeywords) - 1))) {
            throw new InvalidArgumentException('invalid_keyword_payload');
        }
        foreach ($additionalKeywords as $keyword) {
            if (!is_string($keyword)) {
                throw new InvalidArgumentException('invalid_keyword_payload');
            }
        }

        return [
            'primaryKeyword' => $primaryKeyword,
            'additionalKeywords' => $additionalKeywords,
        ];
    }

    public function read(int $postId, string $type, ?int $maxTemplateBytes = null): array
    {
        $this->validateKey($postId, $type);
        if ($maxTemplateBytes !== null && ($maxTemplateBytes < 1 || $maxTemplateBytes > 1048576)) {
            throw new InvalidArgumentException('invalid_storage_read_limit');
        }
        $table = $this->tableName();
        $template = $maxTemplateBytes === null ? 'template'
            : $this->db->prepare('CASE WHEN OCTET_LENGTH(template) <= %d THEN template ELSE NULL END AS template', $maxTemplateBytes);
        $rows = $this->fetchRows($this->db->prepare(
            "SELECT id, $template FROM `$table` WHERE post_id = %d AND type = %s ORDER BY id ASC LIMIT %d",
            $postId,
            $type,
            self::MAX_READ_ROWS + 1
        ));

        if (count($rows) > self::MAX_READ_ROWS) {
            throw new RuntimeException('storage_read_limit');
        }
        if ($maxTemplateBytes !== null) {
            foreach ($rows as $row) {
                if (!is_string($row['template'])) {
                    throw new RuntimeException('storage_read_payload_too_large');
                }
            }
        }

        return $rows;
    }

    public function save(int $postId, string $type, string $template, ?array $expectedRows = null, bool $deferInvalidation = false): ?callable
    {
        $this->validateKey($postId, $type);
        if (strlen($template) > 1048576) {
            throw new RuntimeException('storage_payload_too_large');
        }
        $this->assertSchemaReady();
        $rows = $expectedRows ?? $this->read($postId, $type);
        $this->validateSnapshot($rows);
        $table = $this->tableName();

        if ($rows === []) {
            $result = $this->execute($this->db->prepare(
                "INSERT INTO `$table` (post_id, type, template)
                    SELECT %d, %s, %s FROM DUAL
                    WHERE NOT EXISTS (SELECT 1 FROM `$table` WHERE post_id = %d AND type = %s)",
                $postId,
                $type,
                $template,
                $postId,
                $type
            ));

            if ($result === 0) {
                throw new RuntimeException('concurrent_change');
            }
            if ($result === false) {
                if (str_starts_with($this->db->last_error, 'Duplicate entry') ||
                    $this->read($postId, $type) !== []) {
                    throw new RuntimeException('concurrent_change');
                }
                throw new RuntimeException('storage_write_failed');
            }

            if ($result !== 1) {
                throw new RuntimeException('storage_unexpected_affected_rows');
            }
        } else {
            $row = $rows[0];
            $result = $this->execute($this->db->prepare(
                "UPDATE `$table` SET template = %s
                    WHERE id = %d AND post_id = %d AND type = %s AND BINARY template = BINARY %s",
                $template,
                (int) $row['id'],
                $postId,
                $type,
                $row['template']
            ));

            if ($result === false) {
                throw new RuntimeException('storage_write_failed');
            }

            if ($result === 0) {
                $currentRows = $this->read($postId, $type);
                if ($template !== $row['template'] || count($currentRows) !== 1 ||
                    (int) $currentRows[0]['id'] !== (int) $row['id'] ||
                    $currentRows[0]['template'] !== $row['template']) {
                    throw new RuntimeException('concurrent_change');
                }
            } elseif ($result !== 1) {
                throw new RuntimeException('storage_unexpected_affected_rows');
            }
        }

        $invalidate = static function () use ($postId): void {
            clean_post_cache($postId);
        };
        if ($deferInvalidation) {
            return $invalidate;
        }
        $invalidate();
        return null;
    }

    public function delete(int $postId, string $type): void
    {
        $this->validateKey($postId, $type);
        $this->assertSchemaReady();
        $rows = $this->read($postId, $type);
        $this->validateSnapshot($rows);

        if ($rows === []) {
            return;
        }

        $table = $this->tableName();
        $row = $rows[0];
        $result = $this->execute($this->db->prepare(
            "DELETE FROM `$table` WHERE id = %d AND post_id = %d AND type = %s AND BINARY template = BINARY %s",
            (int) $row['id'],
            $postId,
            $type,
            $row['template']
        ));

        if ($result === false) {
            throw new RuntimeException('storage_write_failed');
        }
        if ($result === 0) {
            throw new RuntimeException('concurrent_change');
        }
        if ($result !== 1) {
            throw new RuntimeException('storage_unexpected_affected_rows');
        }

        clean_post_cache($postId);
    }

    public function deleteAll(int $postId): void
    {
        $this->validatePostId($postId);
        $this->assertSchemaReady();
        $table = $this->tableName();
        $rows = $this->fetchRows($this->db->prepare(
            "SELECT COUNT(*) AS row_count FROM `$table` WHERE post_id = %d",
            $postId
        ));

        if (count($rows) !== 1 || !isset($rows[0]['row_count']) || !is_numeric($rows[0]['row_count'])) {
            throw new RuntimeException('storage_read_failed');
        }

        $expectedCount = (int) $rows[0]['row_count'];
        $result = $this->execute($this->db->prepare("DELETE FROM `$table` WHERE post_id = %d", $postId));

        if ($result === false) {
            throw new RuntimeException('storage_write_failed');
        }
        if ($result !== $expectedCount) {
            throw new RuntimeException('storage_unexpected_affected_rows');
        }

        clean_post_cache($postId);
    }

    private function validateKey(int $postId, string $type): void
    {
        $this->validatePostId($postId);
        if (!in_array($type, self::TYPES, true)) {
            throw new InvalidArgumentException('invalid_tag_type');
        }
    }

    private function validatePostId(int $postId): void
    {
        if ($postId <= 0) {
            throw new InvalidArgumentException('invalid_post_id');
        }
    }

    private function validateSnapshot(array $rows): void
    {
        if (count($rows) > 1) {
            throw new RuntimeException('duplicate_rows');
        }
        if ($rows === []) {
            return;
        }
        if (!isset($rows[0]) || !is_array($rows[0]) ||
            !isset($rows[0]['id'], $rows[0]['template']) ||
            (!is_int($rows[0]['id']) && !is_string($rows[0]['id'])) ||
            !ctype_digit((string) $rows[0]['id']) || (int) $rows[0]['id'] <= 0 ||
            !is_string($rows[0]['template'])) {
            throw new InvalidArgumentException('invalid_snapshot');
        }
    }

    private function assertSchemaReady(): void
    {
        $table = $this->tableName();
        $columns = $this->fetchRows("SHOW COLUMNS FROM `$table`");
        $fields = array_column($columns, 'Field');
        foreach (['id', 'post_id', 'type', 'template'] as $field) {
            if (!in_array($field, $fields, true)) {
                throw new RuntimeException('schema_not_ready');
            }
        }

        $indexes = [];
        foreach ($this->fetchRows("SHOW INDEX FROM `$table`") as $index) {
            if ((int) $index['Non_unique'] === 0) {
                $indexes[$index['Key_name']][(int) $index['Seq_in_index']] = $index;
            }
        }

        foreach ($indexes as $index) {
            ksort($index);
            $index = array_values($index);
            if (count($index) === 2 && $index[0]['Column_name'] === 'post_id' &&
                $index[1]['Column_name'] === 'type' &&
                $index[0]['Sub_part'] === null && $index[1]['Sub_part'] === null) {
                return;
            }
        }

        throw new RuntimeException('schema_not_ready');
    }

    private function tableName(): string
    {
        return str_replace('`', '``', $this->db->prefix . DatabaseTablesManager::DATABASE_MOD_METATAGS);
    }

    private function fetchRows(string $sql): array
    {
        $previous = $this->db->suppress_errors(true);
        try {
            $rows = $this->db->get_results($sql, ARRAY_A);
            if (!is_array($rows) || $this->db->last_error !== '') {
                throw new RuntimeException('storage_read_failed');
            }
            return $rows;
        } catch (Throwable $e) {
            throw new RuntimeException('storage_read_failed');
        } finally {
            $this->db->suppress_errors($previous);
        }
    }

    private function execute(string $sql): int|false
    {
        $previous = $this->db->suppress_errors(true);
        try {
            $result = $this->db->query($sql);
        } catch (Throwable $e) {
            throw new RuntimeException('storage_write_failed');
        } finally {
            $this->db->suppress_errors($previous);
        }

        if ($result === false || $this->db->last_error !== '') {
            return false;
        }
        if (!is_int($result)) {
            throw new RuntimeException('storage_unexpected_affected_rows');
        }
        return $result;
    }
}
