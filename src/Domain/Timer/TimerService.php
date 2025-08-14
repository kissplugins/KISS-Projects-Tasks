<?php

namespace KISS\PTT\Domain\Timer;

use KISS\PTT\Integration\ACF\ACFAdapter;
use KISS\PTT\Domain\Session\SessionRepository;

/**
 * Timer orchestration: start/stop/resume with validation and invariants.
 * Handlers/controllers should call into this.
 */
class TimerService
{
    private ACFAdapter $acf;
    private SessionRepository $sessions;

    public function __construct(ACFAdapter $acf, SessionRepository $sessions)
    {
        $this->acf = $acf;
        $this->sessions = $sessions;
    }

    /** Ensure there is at most one running session globally for a user. */
    public function userHasActiveSession(int $userId): bool
    {
        // Minimal implementation: reuse existing helper via procedural function if available
        if (function_exists('ptt_get_active_session_index_for_user')) {
            return (bool) ptt_get_active_session_index_for_user($userId);
        }
        return false;
    }

    /** Start a new session on a task with an initial title. Enforces invariants. */
    public function start(int $postId, string $title): bool
    {
        // Invariant: Task must not have a parent-level running timer
        $start = $this->acf->getField('start_time', $postId);
        $stop  = $this->acf->getField('stop_time', $postId);
        if ($start && !$stop) {
            return false;
        }

        // Invariant: Task should not already have a running session
        $sessions = $this->sessions->getAll($postId);
        foreach ($sessions as $s) {
            if (!empty($s['session_start_time']) && empty($s['session_stop_time'])) {
                return false;
            }
        }

        $now = $this->acf->nowUtc();
        $row = [
            'session_title'           => $title,
            'session_start_time'      => $now,
            'session_stop_time'       => '',
            'session_manual_override' => 0,
            'session_manual_duration' => 0,
        ];
        $ok = $this->sessions->add($row, $postId);
        if ($ok) {
            do_action('ptt_session_started', $postId, $now, $title);
        }
        return $ok;
    }

    /** Stop the active (running) session for a task, if any. */
    public function stopActive(int $postId): bool
    {
        $count = $this->sessions->count($postId);
        if ($count <= 0) { return false; }
        $now = $this->acf->nowUtc();
        // Walk backward for running session
        $sessions = $this->sessions->getAll($postId);
        for ($i = $count - 1; $i >= 0; $i--) {
            $s = $sessions[$i] ?? [];
            if (!empty($s['session_start_time']) && empty($s['session_stop_time'])) {
                $ok = $this->sessions->updateSub($i, 'session_stop_time', $now, $postId);
                if ($ok) {
                    do_action('ptt_session_stopped', $postId, $now, $i);
                }
                return $ok;
            }
        }
        return false;
    }

    /** Resume a session by index if it was stopped, creating a new running segment. */
    public function resume(int $postId, int $index0, ?string $title = null): bool
    {
        $sessions = $this->sessions->getAll($postId);
        $target = $sessions[$index0] ?? null;
        if (!$target) { return false; }
        // Only resume if the target session is stopped
        if (empty($target['session_start_time']) || empty($target['session_stop_time'])) {
            return false;
        }
        $newTitle = $title ?? ($target['session_title'] ?? 'Session');
        $ok = $this->start($postId, $newTitle);
        if ($ok) {
            do_action('ptt_session_resumed', $postId, $index0, $newTitle);
        }
        return $ok;
    }
}

