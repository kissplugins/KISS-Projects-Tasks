<?php

namespace KISS\PTT\Domain\Session;

use KISS\PTT\Integration\ACF\ACFAdapter;

/**
 * Repository for reading/writing task sessions stored in ACF repeater fields.
 * This is a minimal-read access layer to avoid full repeater hydration.
 */
class SessionRepository
{
    public const REPEATER = 'sessions';

    private ACFAdapter $acf;

    public function __construct(ACFAdapter $acf)
    {
        $this->acf = $acf;
    }

    /** Get all sessions for a task (full read). Prefer targeted helpers when possible. */
    public function getAll(int $postId): array
    {
        $sessions = $this->acf->getField(self::REPEATER, $postId);
        return is_array($sessions) ? $sessions : [];
    }

    /** Count of sessions for bounds checks. */
    public function count(int $postId): int
    {
        $sessions = $this->acf->getField(self::REPEATER, $postId);
        return is_array($sessions) ? count($sessions) : 0;
    }

    /** Add a new session row. Returns true on success. */
    public function add(array $row, int $postId): bool
    {
        return $this->acf->addRow(self::REPEATER, $row, $postId);
    }

    /** Update a sub field for a session row (0-based index -> ACF 1-based). */
    public function updateSub(int $index0, string $fieldName, $value, int $postId): bool
    {
        $selector = [ self::REPEATER, $index0 + 1, $fieldName ];
        return $this->acf->updateSubField($selector, $value, $postId);
    }

    /** Delete a session row by 0-based index. */
    public function delete(int $index0, int $postId): bool
    {
        return $this->acf->deleteRow(self::REPEATER, $index0 + 1, $postId);
    }
}

